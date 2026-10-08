<?php
/**
 * Background Batch Processor and Retry Engine.
 *
 * @package WPCalibrate\CatalogSyncMeta
 */

namespace WPCalibrate\CatalogSyncMeta\Queue;

use WPCalibrate\CatalogSyncMeta\Logging\Logger;
use WPCalibrate\CatalogSyncMeta\Settings\SettingsRegistry;
use WPCalibrate\CatalogSyncMeta\Sync\DirectSyncService;

defined( 'ABSPATH' ) || exit;

/**
 * Class BatchProcessor
 */
class BatchProcessor {

	/**
	 * Lock transient key to prevent concurrent batch runners.
	 *
	 * @var string
	 */
	public const BATCH_LOCK_KEY = 'wpcalibrate_csm_batch_lock';

	/**
	 * Process a batch of pending products from queue.
	 *
	 * @return void
	 */
	public static function handle_batch_job(): void {
		$settings = SettingsRegistry::get_all();

		// Check if sync is paused or auto sync is disabled.
		if ( ! empty( $settings['is_sync_paused'] ) || empty( $settings['enable_auto_sync'] ) ) {
			Logger::info( 'Batch processing skipped: synchronization is paused or disabled.' );
			return;
		}

		// Concurrency protection lock.
		if ( get_transient( self::BATCH_LOCK_KEY ) ) {
			Logger::info( 'Batch processing already running, skipping overlapping execution.' );
			return;
		}

		set_transient( self::BATCH_LOCK_KEY, 1, 5 * MINUTE_IN_SECONDS );

		try {
			$batch_size = (int) ( $settings['batch_size'] ?? 50 );
			$queue      = get_option( QueueManager::PENDING_QUEUE_OPTION, array() );

			if ( empty( $queue ) || ! is_array( $queue ) ) {
				delete_transient( self::BATCH_LOCK_KEY );
				return;
			}

			// Slice next batch.
			$chunk     = array_slice( $queue, 0, $batch_size );
			$remaining = array_slice( $queue, $batch_size );

			// Update queue with remaining items.
			update_option( QueueManager::PENDING_QUEUE_OPTION, $remaining, false );

			Logger::info( sprintf( 'Processing batch of %d products (Remaining: %d).', count( $chunk ), count( $remaining ) ) );

			// Execute synchronization.
			$result = DirectSyncService::sync_products( $chunk );

			// If remaining items exist, schedule next batch with throttle delay.
			if ( ! empty( $remaining ) ) {
				$throttle_ms = (int) ( $settings['throttle_delay_ms'] ?? 100 );
				$delay_sec   = (int) ceil( $throttle_ms / 1000 );
				QueueManager::schedule_batch_processing_if_needed( $delay_sec );
			}
		} catch ( \Throwable $e ) {
			Logger::error( 'Fatal error in batch execution: ' . $e->getMessage() );
		} finally {
			delete_transient( self::BATCH_LOCK_KEY );
		}
	}

	/**
	 * Process a single product in background.
	 *
	 * @param int $product_id Product ID.
	 * @return void
	 */
	public static function handle_single_job( int $product_id ): void {
		if ( $product_id <= 0 ) {
			return;
		}

		Logger::info( sprintf( 'Executing background sync for single product #%d.', $product_id ) );
		DirectSyncService::sync_products( array( $product_id ) );
	}
}
