<?php
/**
 * Uninstaller for WPCalibrate Catalog Sync for Meta.
 *
 * @package WPCalibrate\CatalogSyncMeta
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Retrieve settings to check if data deletion was explicitly configured.
$settings = get_option( 'wpcalibrate_csm_settings', array() );

if ( empty( $settings['delete_data_on_uninstall'] ) ) {
	// Administrator elected to preserve configuration and sync history for reinstall.
	return;
}

global $wpdb;

// 1. Drop plugin custom tables.
$table_events = $wpdb->prefix . 'wpcalibrate_csm_sync_events';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$table_events}" );

// 2. Delete plugin options.
$options_to_delete = array(
	'wpcalibrate_csm_settings',
	'wpcalibrate_csm_db_version',
	'wpcalibrate_csm_pending_queue',
	'wpcalibrate_csm_reconcile_state',
);

foreach ( $options_to_delete as $option ) {
	delete_option( $option );
}

// 3. Delete plugin-specific product metadata.
$meta_keys = array(
	'_wpcalibrate_csm_sync_status',
	'_wpcalibrate_csm_catalog_id',
	'_wpcalibrate_csm_last_sync_time',
	'_wpcalibrate_csm_last_error',
	'_wpcalibrate_csm_exclude',
);

$format_strings = implode( ', ', array_fill( 0, count( $meta_keys ), '%s' ) );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ($format_strings)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$meta_keys
	)
);

// 4. Unschedule all Action Scheduler tasks belonging to our group.
if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'wpcalibrate_csm_process_batch', array(), 'wpcalibrate-catalog-sync-meta' );
	as_unschedule_all_actions( 'wpcalibrate_csm_cron_reconcile', array(), 'wpcalibrate-catalog-sync-meta' );
	as_unschedule_all_actions( 'wpcalibrate_csm_cron_prune_events', array(), 'wpcalibrate-catalog-sync-meta' );
	as_unschedule_all_actions( 'wpcalibrate_csm_sync_single_product', array(), 'wpcalibrate-catalog-sync-meta' );
}

// 5. Delete transient locks.
delete_transient( 'wpcalibrate_csm_reconcile_lock' );
delete_transient( 'wpcalibrate_csm_batch_lock' );
delete_transient( 'wpcalibrate_csm_activated' );
