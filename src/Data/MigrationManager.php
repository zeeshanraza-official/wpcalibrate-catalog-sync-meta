<?php
/**
 * Database Migration Manager.
 *
 * @package WPCalibrate\CatalogSyncMeta
 */

namespace WPCalibrate\CatalogSyncMeta\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Class MigrationManager
 */
class MigrationManager {

	/**
	 * Database schema version option name.
	 *
	 * @var string
	 */
	public const DB_VERSION_OPTION = 'wpcalibrate_csm_db_version';

	/**
	 * Current database schema version.
	 *
	 * @var string
	 */
	public const CURRENT_DB_VERSION = '1.0.0';

	/**
	 * Run migrations if needed.
	 *
	 * @return void
	 */
	public static function maybe_migrate(): void {
		$installed_version = get_option( self::DB_VERSION_OPTION, '0.0.0' );

		if ( version_compare( $installed_version, self::CURRENT_DB_VERSION, '<' ) ) {
			self::install_tables();
			update_option( self::DB_VERSION_OPTION, self::CURRENT_DB_VERSION, false );
		}
	}

	/**
	 * Install or update database tables using dbDelta.
	 *
	 * @return void
	 */
	public static function install_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'wpcalibrate_csm_sync_events';

		// Custom table for structured sync event and diagnostic history.
		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			product_id bigint(20) unsigned NOT NULL,
			variation_id bigint(20) unsigned NOT NULL DEFAULT 0,
			event_type varchar(32) NOT NULL DEFAULT 'sync',
			status varchar(20) NOT NULL DEFAULT 'pending',
			error_code varchar(64) DEFAULT NULL,
			error_message text DEFAULT NULL,
			payload_summary text DEFAULT NULL,
			retry_count int(11) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY product_variation (product_id, variation_id),
			KEY status_created (status, created_at),
			KEY event_type (event_type)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Drop plugin tables on uninstall if explicit data deletion was requested.
	 *
	 * @return void
	 */
	public static function drop_tables(): void {
		global $wpdb;
		$table_name = $wpdb->prefix . 'wpcalibrate_csm_sync_events';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
		delete_option( self::DB_VERSION_OPTION );
	}
}
