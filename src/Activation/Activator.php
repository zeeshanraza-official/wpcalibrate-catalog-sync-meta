<?php
/**
 * Plugin Activator.
 *
 * @package WPCalibrate\CatalogSyncMeta
 */

namespace WPCalibrate\CatalogSyncMeta\Activation;

use WPCalibrate\CatalogSyncMeta\Compatibility\CompatibilityChecker;
use WPCalibrate\CatalogSyncMeta\Data\MigrationManager;
use WPCalibrate\CatalogSyncMeta\Settings\SettingsRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Class Activator
 */
class Activator {

	/**
	 * Run activation routines.
	 *
	 * @return void
	 */
	public static function activate(): void {
		// Verify minimum PHP requirement.
		if ( version_compare( PHP_VERSION, WPCALIBRATE_CSM_MIN_PHP, '<' ) ) {
			deactivate_plugins( WPCALIBRATE_CSM_BASENAME );
			wp_die(
				sprintf(
					/* translators: 1: Required PHP version, 2: Current PHP version */
					esc_html__( 'WPCalibrate Catalog Sync for Meta requires PHP version %1$s or higher. Your server is running PHP version %2$s.', 'wpcalibrate-catalog-sync-meta' ),
					esc_html( WPCALIBRATE_CSM_MIN_PHP ),
					esc_html( PHP_VERSION )
				)
			);
		}

		// Initialize default settings if not already present.
		$existing = get_option( SettingsRegistry::OPTION_NAME, null );
		if ( null === $existing ) {
			update_option( SettingsRegistry::OPTION_NAME, SettingsRegistry::get_defaults(), false );
		}

		// Run database migrations for custom event table.
		MigrationManager::maybe_migrate();

		// Schedule background recurring routines via Action Scheduler or WP-Cron.
		self::schedule_recurring_jobs();

		// Set transient to show welcome notice if needed.
		set_transient( 'wpcalibrate_csm_activated', 1, 30 );
	}

	/**
	 * Schedule background recurring maintenance jobs.
	 *
	 * @return void
	 */
	public static function schedule_recurring_jobs(): void {
		if ( function_exists( 'as_schedule_recurring_action' ) ) {
			// Schedule daily reconciliation if not already scheduled.
			if ( false === as_next_scheduled_action( 'wpcalibrate_csm_cron_reconcile', array(), 'wpcalibrate-catalog-sync-meta' ) ) {
				as_schedule_recurring_action(
					time() + HOUR_IN_SECONDS,
					DAY_IN_SECONDS,
					'wpcalibrate_csm_cron_reconcile',
					array(),
					'wpcalibrate-catalog-sync-meta'
				);
			}

			// Schedule daily log/event pruning.
			if ( false === as_next_scheduled_action( 'wpcalibrate_csm_cron_prune_events', array(), 'wpcalibrate-catalog-sync-meta' ) ) {
				as_schedule_recurring_action(
					time() + ( 2 * HOUR_IN_SECONDS ),
					DAY_IN_SECONDS,
					'wpcalibrate_csm_cron_prune_events',
					array(),
					'wpcalibrate-catalog-sync-meta'
				);
			}
		}
	}
}
