<?php
/**
 * System Diagnostics Reporter.
 *
 * @package WPCalibrate\CatalogSyncMeta
 */

namespace WPCalibrate\CatalogSyncMeta\Logging;

use WPCalibrate\CatalogSyncMeta\Api\ConnectionHealth;
use WPCalibrate\CatalogSyncMeta\Compatibility\CompatibilityChecker;
use WPCalibrate\CatalogSyncMeta\Data\MigrationManager;
use WPCalibrate\CatalogSyncMeta\Data\SyncEventRepository;
use WPCalibrate\CatalogSyncMeta\Queue\QueueManager;
use WPCalibrate\CatalogSyncMeta\Security\SecurityHelper;
use WPCalibrate\CatalogSyncMeta\Settings\SettingsRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Class DiagnosticsReporter
 */
class DiagnosticsReporter {

	/**
	 * Compile a structured, sanitized system report.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_report_data(): array {
		global $wp_version, $wpdb;

		$settings = SettingsRegistry::get_all();
		$counts   = SyncEventRepository::get_status_counts();
		$health   = ConnectionHealth::get_status_presentation();

		$data = array(
			'environment' => array(
				'plugin_version'      => WPCALIBRATE_CSM_VERSION,
				'php_version'         => PHP_VERSION,
				'wordpress_version'   => $wp_version,
				'woocommerce_version' => defined( 'WC_VERSION' ) ? WC_VERSION : 'Not Active',
				'hpos_enabled'        => CompatibilityChecker::is_hpos_enabled() ? 'Yes' : 'No',
				'action_scheduler'    => CompatibilityChecker::has_action_scheduler() ? 'Available' : 'Missing',
				'db_schema_version'   => get_option( MigrationManager::DB_VERSION_OPTION, 'None' ),
				'wp_cron_disabled'    => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ? 'Yes' : 'No',
				'memory_limit'        => ini_get( 'memory_limit' ),
				'max_execution_time'  => ini_get( 'max_execution_time' ),
			),
			'meta_connection' => array(
				'status'              => $settings['meta_connection_status'],
				'status_label'        => $health['label'],
				'app_id_configured'   => ! empty( $settings['meta_app_id'] ) ? 'Configured' : 'Not Set',
				'app_secret'          => ! empty( $settings['meta_app_secret'] ) ? SecurityHelper::mask_secret( $settings['meta_app_secret'] ) : 'Not Set',
				'access_token'        => ! empty( $settings['meta_access_token'] ) ? SecurityHelper::mask_secret( $settings['meta_access_token'] ) : 'Not Set',
				'business_id'         => $settings['meta_business_id'] ?: 'Not Set',
				'catalog_id'          => $settings['meta_catalog_id'] ?: 'Not Set',
				'last_check'          => $settings['meta_last_connection_check'] ? wp_date( 'Y-m-d H:i:s', $settings['meta_last_connection_check'] ) : 'Never',
			),
			'sync_status' => array(
				'auto_sync_enabled'   => ! empty( $settings['enable_auto_sync'] ) ? 'Yes' : 'No',
				'sync_paused'         => ! empty( $settings['is_sync_paused'] ) ? 'Yes (PAUSED)' : 'No',
				'batch_size'          => $settings['batch_size'],
				'throttle_ms'         => $settings['throttle_delay_ms'],
				'max_retries'         => $settings['max_retries'],
				'reconcile_interval'  => $settings['reconciliation_interval'],
				'last_reconciliation' => $settings['last_reconciliation_time'] ? wp_date( 'Y-m-d H:i:s', $settings['last_reconciliation_time'] ) : 'Never',
				'last_incremental'    => $settings['last_incremental_sync'] ? wp_date( 'Y-m-d H:i:s', $settings['last_incremental_sync'] ) : 'Never',
				'queue_size'          => QueueManager::get_queue_size(),
				'synced_events'       => $counts['synced'],
				'failed_events'       => $counts['failed'],
				'pending_events'      => $counts['pending'],
			),
			'feed' => array(
				'feed_enabled'        => ! empty( $settings['enable_feed'] ) ? 'Yes' : 'No',
				'feed_last_generated' => $settings['feed_last_generated'] ? wp_date( 'Y-m-d H:i:s', $settings['feed_last_generated'] ) : 'Never',
				'feed_item_count'     => $settings['feed_item_count'],
				'feed_token'          => SecurityHelper::mask_secret( $settings['feed_token'] ),
			),
			'licensing' => array(
				'status'              => $settings['license_status'],
				'license_key'         => ! empty( $settings['license_key'] ) ? SecurityHelper::mask_secret( $settings['license_key'] ) : 'Not Configured',
			),
		);

		return SecurityHelper::redact_sensitive_data( $data );
	}

	/**
	 * Format report as clean plain-text markdown for easy copying.
	 *
	 * @return string
	 */
	public static function get_formatted_text_report(): string {
		$report = self::get_report_data();
		$lines  = array();

		$lines[] = '### WPCalibrate Catalog Sync for Meta - System Diagnostics Report ###';
		$lines[] = 'Generated: ' . gmdate( 'Y-m-d H:i:s' ) . ' UTC';
		$lines[] = '';

		foreach ( $report as $section => $values ) {
			$lines[] = '== ' . strtoupper( str_replace( '_', ' ', $section ) ) . ' ==';
			foreach ( $values as $k => $v ) {
				$lines[] = sprintf( '%-24s : %s', $k, is_array( $v ) ? wp_json_encode( $v ) : (string) $v );
			}
			$lines[] = '';
		}

		return implode( "\n", $lines );
	}
}
