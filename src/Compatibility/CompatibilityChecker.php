<?php
/**
 * Compatibility and Dependency Checker.
 *
 * @package WPCalibrate\CatalogSyncMeta
 */

namespace WPCalibrate\CatalogSyncMeta\Compatibility;

defined( 'ABSPATH' ) || exit;

/**
 * Class CompatibilityChecker
 */
class CompatibilityChecker {

	/**
	 * Errors list.
	 *
	 * @var array<string>
	 */
	private static array $errors = array();

	/**
	 * Check if all system and dependency requirements are met.
	 *
	 * @return bool
	 */
	public static function is_compatible(): bool {
		self::$errors = array();

		// Check PHP version.
		if ( version_compare( PHP_VERSION, WPCALIBRATE_CSM_MIN_PHP, '<' ) ) {
			self::$errors[] = sprintf(
				/* translators: 1: Required PHP version, 2: Current PHP version */
				__( 'WPCalibrate Catalog Sync for Meta requires PHP version %1$s or higher. Your server is running PHP version %2$s.', 'wpcalibrate-catalog-sync-meta' ),
				WPCALIBRATE_CSM_MIN_PHP,
				PHP_VERSION
			);
		}

		// Check WordPress version.
		global $wp_version;
		if ( ! empty( $wp_version ) && version_compare( $wp_version, WPCALIBRATE_CSM_MIN_WP, '<' ) ) {
			self::$errors[] = sprintf(
				/* translators: 1: Required WordPress version, 2: Current WordPress version */
				__( 'WPCalibrate Catalog Sync for Meta requires WordPress version %1$s or higher. Your site is running WordPress version %2$s.', 'wpcalibrate-catalog-sync-meta' ),
				WPCALIBRATE_CSM_MIN_WP,
				$wp_version
			);
		}

		// Check WooCommerce active.
		if ( ! class_exists( 'WooCommerce' ) ) {
			self::$errors[] = __( 'WPCalibrate Catalog Sync for Meta requires WooCommerce to be installed and active.', 'wpcalibrate-catalog-sync-meta' );
		} elseif ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, WPCALIBRATE_CSM_MIN_WC, '<' ) ) {
			self::$errors[] = sprintf(
				/* translators: 1: Required WooCommerce version, 2: Current WooCommerce version */
				__( 'WPCalibrate Catalog Sync for Meta requires WooCommerce version %1$s or higher. Your store is running WooCommerce version %2$s.', 'wpcalibrate-catalog-sync-meta' ),
				WPCALIBRATE_CSM_MIN_WC,
				WC_VERSION
			);
		}

		return empty( self::$errors );
	}

	/**
	 * Get compatibility errors.
	 *
	 * @return array<string>
	 */
	public static function get_errors(): array {
		return self::$errors;
	}

	/**
	 * Render admin notices if requirements are unmet.
	 *
	 * @return void
	 */
	public static function render_admin_notices(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		foreach ( self::$errors as $error ) {
			printf(
				'<div class="notice notice-error"><p><strong>%s</strong>: %s</p></div>',
				esc_html__( 'WPCalibrate Catalog Sync for Meta Notice', 'wpcalibrate-catalog-sync-meta' ),
				esc_html( $error )
			);
		}
	}

	/**
	 * Check if Action Scheduler is available.
	 *
	 * @return bool
	 */
	public static function has_action_scheduler(): bool {
		return function_exists( 'as_enqueue_async_action' ) && function_exists( 'as_schedule_recurring_action' );
	}

	/**
	 * Check if High-Performance Order Storage (HPOS) is active in WooCommerce.
	 *
	 * @return bool
	 */
	public static function is_hpos_enabled(): bool {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && method_exists( '\Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled' ) ) {
			return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		}
		return false;
	}
}
