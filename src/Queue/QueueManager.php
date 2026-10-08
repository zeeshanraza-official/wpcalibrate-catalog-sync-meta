<?php
/**
 * Action Scheduler Queue Manager.
 *
 * @package WPCalibrate\CatalogSyncMeta
 */

namespace WPCalibrate\CatalogSyncMeta\Queue;

use WPCalibrate\CatalogSyncMeta\Settings\SettingsRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Class QueueManager
 */
class QueueManager {

	/**
	 * Unique Action Scheduler action group name.
	 *
	 * @var string
	 */
	public const ACTION_GROUP = 'wpcalibrate-catalog-sync-meta';

	/**
	 * Hook name for processing product sync batches.
	 *
	 * @var string
	 */
	public const HOOK_PROCESS_BATCH = 'wpcalibrate_csm_process_batch';

	/**
	 * Hook name for single product immediate background sync.
	 *
	 * @var string
	 */
	public const HOOK_SYNC_SINGLE = 'wpcalibrate_csm_sync_single_product';

	/**
	 * Hook name for full catalog reconciliation.
	 *
	 * @var string
	 */
	public const HOOK_RECONCILE = 'wpcalibrate_csm_cron_reconcile';

	/**
	 * Hook name for log and event pruning.
	 *
	 * @var string
	 */
	public const HOOK_PRUNE_EVENTS = 'wpcalibrate_csm_cron_prune_events';

	/**
	 * Option key for coalesced pending product queue.
	 *
	 * @var string
	 */
	public const PENDING_QUEUE_OPTION = 'wpcalibrate_csm_pending_queue';

	/**
	 * Register queue action handlers.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( self::HOOK_PROCESS_BATCH, array( BatchProcessor::class, 'handle_batch_job' ) );
		add_action( self::HOOK_SYNC_SINGLE, array( BatchProcessor::class, 'handle_single_job' ), 10, 1 );
		add_action( self::HOOK_RECONCILE, array( '\WPCalibrate\CatalogSyncMeta\Sync\ReconciliationService', 'run_reconciliation' ) );
		add_action( self::HOOK_PRUNE_EVENTS, array( '\WPCalibrate\CatalogSyncMeta\Data\SyncEventRepository', 'prune_old_events' ) );
	}

	/**
	 * Enqueue a product for background sync, coalescing duplicates.
	 *
	 * @param int $product_id Product ID.
	 * @return bool True if queued.
	 */
	public static function enqueue_product( int $product_id ): bool {
		if ( $product_id <= 0 ) {
			return false;
		}

		// Check if sync is paused or disabled.
		$settings = SettingsRegistry::get_all();
		if ( ! empty( $settings['is_sync_paused'] ) || empty( $settings['enable_auto_sync'] ) ) {
			// Mark product as pending locally, but do not schedule remote processing.
			update_post_meta( $product_id, '_wpcalibrate_csm_sync_status', 'pending' );
			return false;
		}

		// Mark product status as pending immediately.
		update_post_meta( $product_id, '_wpcalibrate_csm_sync_status', 'pending' );

		// Coalesce into pending queue option.
		$queue = get_option( self::PENDING_QUEUE_OPTION, array() );
		if ( ! is_array( $queue ) ) {
			$queue = array();
		}

		if ( ! in_array( $product_id, $queue, true ) ) {
			$queue[] = $product_id;
			update_option( self::PENDING_QUEUE_OPTION, $queue, false );
		}

		// Ensure batch action is scheduled without creating duplicates.
		self::schedule_batch_processing_if_needed();

		return true;
	}

	/**
	 * Enqueue an array of product IDs for background sync.
	 *
	 * @param array<int> $product_ids Product IDs.
	 * @return int Number of products added.
	 */
	public static function enqueue_products( array $product_ids ): int {
		if ( empty( $product_ids ) ) {
			return 0;
		}

		$queue = get_option( self::PENDING_QUEUE_OPTION, array() );
		if ( ! is_array( $queue ) ) {
			$queue = array();
		}

		$added = 0;
		foreach ( $product_ids as $pid ) {
			$pid = absint( $pid );
			if ( $pid > 0 && ! in_array( $pid, $queue, true ) ) {
				$queue[] = $pid;
				update_post_meta( $pid, '_wpcalibrate_csm_sync_status', 'pending' );
				$added++;
			}
		}

		if ( $added > 0 ) {
			update_option( self::PENDING_QUEUE_OPTION, $queue, false );
			self::schedule_batch_processing_if_needed();
		}

		return $added;
	}

	/**
	 * Schedule a batch processing job if one is not already scheduled or running.
	 *
	 * @param int $delay_seconds Delay before batch starts (e.g., for throttling or backoff).
	 * @return void
	 */
	public static function schedule_batch_processing_if_needed( int $delay_seconds = 0 ): void {
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			// Fallback to WP-Cron if Action Scheduler is not loaded.
			if ( ! wp_next_scheduled( self::HOOK_PROCESS_BATCH ) ) {
				wp_schedule_single_event( time() + max( 1, $delay_seconds ), self::HOOK_PROCESS_BATCH );
			}
			return;
		}

		// Prevent duplicate scheduled actions in the same group.
		$existing = as_next_scheduled_action( self::HOOK_PROCESS_BATCH, array(), self::ACTION_GROUP );
		if ( false === $existing ) {
			if ( $delay_seconds > 0 ) {
				as_schedule_single_action( time() + $delay_seconds, self::HOOK_PROCESS_BATCH, array(), self::ACTION_GROUP );
			} else {
				as_enqueue_async_action( self::HOOK_PROCESS_BATCH, array(), self::ACTION_GROUP );
			}
		}
	}

	/**
	 * Get the number of pending items in queue.
	 *
	 * @return int
	 */
	public static function get_queue_size(): int {
		$queue = get_option( self::PENDING_QUEUE_OPTION, array() );
		return is_array( $queue ) ? count( $queue ) : 0;
	}

	/**
	 * Clear the pending queue.
	 *
	 * @return void
	 */
	public static function clear_queue(): void {
		delete_option( self::PENDING_QUEUE_OPTION );
	}
}
