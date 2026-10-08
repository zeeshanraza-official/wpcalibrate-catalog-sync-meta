<?php
/**
 * Plugin Deactivator.
 *
 * @package WPCalibrate\CatalogSyncMeta
 */

namespace WPCalibrate\CatalogSyncMeta\Activation;

defined( 'ABSPATH' ) || exit;

/**
 * Class Deactivator
 */
class Deactivator {

	/**
	 * Run deactivation routines.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		// Clear scheduled actions in our group.
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'wpcalibrate_csm_cron_reconcile', array(), 'wpcalibrate-catalog-sync-meta' );
			as_unschedule_all_actions( 'wpcalibrate_csm_cron_prune_events', array(), 'wpcalibrate-catalog-sync-meta' );
			as_unschedule_all_actions( 'wpcalibrate_csm_process_batch', array(), 'wpcalibrate-catalog-sync-meta' );
			as_unschedule_all_actions( 'wpcalibrate_csm_sync_single_product', array(), 'wpcalibrate-catalog-sync-meta' );
		}

		// Clear transient locks.
		delete_transient( 'wpcalibrate_csm_reconcile_lock' );
		delete_transient( 'wpcalibrate_csm_batch_lock' );
	}
}
