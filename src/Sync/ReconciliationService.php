<?php
/**
 * Full Catalog Reconciliation Service.
 *
 * @package WPCalibrate\CatalogSyncMeta
 */

namespace WPCalibrate\CatalogSyncMeta\Sync;

use WPCalibrate\CatalogSyncMeta\Catalog\EligibilityEngine;
use WPCalibrate\CatalogSyncMeta\Logging\Logger;
use WPCalibrate\CatalogSyncMeta\Queue\QueueManager;
use WPCalibrate\CatalogSyncMeta\Settings\SettingsRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Class ReconciliationService
 */
class ReconciliationService {

	/**
	 * Lock transient key to prevent concurrent reconciliations.
	 *
	 * @var string
	 */
	public const LOCK_KEY = 'wpcalibrate_csm_reconcile_lock';

	/**
	 * State option name for resumable pagination.
	 *
	 * @var string
	 */
	public const STATE_OPTION = 'wpcalibrate_csm_reconcile_state';

	/**
	 * Start a full reconciliation job if not already active.
	 *
	 * @return bool True if started, false if already running or locked.
	 */
	public static function start_full_reconciliation(): bool {
		if ( self::is_running() ) {
			Logger::warning( 'Cannot start full reconciliation: an operation is already in progress.' );
			return false;
		}

		// Set concurrency lock for 15 minutes.
		set_transient( self::LOCK_KEY, 1, 15 * MINUTE_IN_SECONDS );

		$state = array(
			'status'          => 'running',
			'page'            => 1,
			'batch_size'      => (int) SettingsRegistry::get( 'batch_size', 50 ),
			'total_processed' => 0,
			'total_queued'    => 0,
			'started_at'      => time(),
			'last_updated'    => time(),
		);

		update_option( self::STATE_OPTION, $state, false );
		Logger::info( 'Starting full catalog reconciliation job.' );

		// Process first step.
		self::process_step();

		return true;
	}

	/**
	 * Execute a single paginated step of reconciliation.
	 *
	 * @return void
	 */
	public static function process_step(): void {
		$state = get_option( self::STATE_OPTION, array() );
		if ( empty( $state ) || 'running' !== ( $state['status'] ?? '' ) ) {
			delete_transient( self::LOCK_KEY );
			return;
		}

		$page       = (int) ( $state['page'] ?? 1 );
		$batch_size = (int) ( $state['batch_size'] ?? 50 );

		// Paginated query for products avoiding high memory load.
		$args = array(
			'limit'    => $batch_size,
			'page'     => $page,
			'status'   => array( 'publish', 'draft', 'trash' ),
			'type'     => array( 'simple', 'variable' ),
			'return'   => 'ids',
			'orderby'  => 'ID',
			'order'    => 'ASC',
		);

		$product_ids = function_exists( 'wc_get_products' ) ? wc_get_products( $args ) : array();

		if ( empty( $product_ids ) ) {
			// Reconciliation complete!
			self::complete_reconciliation( $state );
			return;
		}

		$to_queue = array();

		foreach ( $product_ids as $pid ) {
			$product = wc_get_product( $pid );
			if ( ! $product ) {
				continue;
			}

			$status_meta = get_post_meta( $pid, DirectSyncService::META_SYNC_STATUS, true );
			$is_eligible = EligibilityEngine::is_eligible( $product );

			if ( $is_eligible ) {
				// Queue if not synced, failed, or pending.
				if ( 'synced' !== $status_meta || empty( $status_meta ) ) {
					$to_queue[] = $pid;
				}
			} else {
				// If ineligible but was previously marked synced, queue to remove.
				if ( 'synced' === $status_meta ) {
					$to_queue[] = $pid;
				}
			}
		}

		if ( ! empty( $to_queue ) ) {
			QueueManager::enqueue_products( $to_queue );
			$state['total_queued'] = ( $state['total_queued'] ?? 0 ) + count( $to_queue );
		}

		$state['total_processed'] = ( $state['total_processed'] ?? 0 ) + count( $product_ids );
		$state['page']            = $page + 1;
		$state['last_updated']    = time();

		update_option( self::STATE_OPTION, $state, false );

		// Refresh lock.
		set_transient( self::LOCK_KEY, 1, 15 * MINUTE_IN_SECONDS );

		// If Action Scheduler is available, schedule next page; otherwise loop safely.
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( 'wpcalibrate_csm_reconcile_step', array(), QueueManager::ACTION_GROUP );
		} else {
			// Schedule WP-Cron fallback.
			wp_schedule_single_event( time() + 2, 'wpcalibrate_csm_reconcile_step' );
		}
	}

	/**
	 * Mark reconciliation as complete and cleanup locks.
	 *
	 * @param array<string, mixed> $state Current state.
	 * @return void
	 */
	private static function complete_reconciliation( array $state ): void {
		$state['status']       = 'completed';
		$state['completed_at'] = time();

		update_option( self::STATE_OPTION, $state, false );
		SettingsRegistry::set( 'last_reconciliation_time', time() );
		delete_transient( self::LOCK_KEY );

		Logger::info(
			sprintf(
				'Full catalog reconciliation finished. Processed %d products; queued %d items.',
				$state['total_processed'] ?? 0,
				$state['total_queued'] ?? 0
			)
		);
	}

	/**
	 * Run reconciliation (invoked by Action Scheduler cron).
	 *
	 * @return void
	 */
	public static function run_reconciliation(): void {
		self::start_full_reconciliation();
	}

	/**
	 * Check if a reconciliation job is currently running.
	 *
	 * @return bool
	 */
	public static function is_running(): bool {
		$locked = (bool) get_transient( self::LOCK_KEY );
		if ( ! $locked ) {
			return false;
		}

		$state = get_option( self::STATE_OPTION, array() );
		return ( 'running' === ( $state['status'] ?? '' ) );
	}

	/**
	 * Get current reconciliation state.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_current_state(): array {
		return get_option( self::STATE_OPTION, array() );
	}
}
