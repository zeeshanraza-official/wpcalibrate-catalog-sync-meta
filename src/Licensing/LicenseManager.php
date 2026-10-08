<?php
/**
 * Modular Licensing Service.
 *
 * @package WPCalibrate\CatalogSyncMeta
 */

namespace WPCalibrate\CatalogSyncMeta\Licensing;

use WP_Error;
use WPCalibrate\CatalogSyncMeta\Logging\Logger;
use WPCalibrate\CatalogSyncMeta\Security\SecurityHelper;
use WPCalibrate\CatalogSyncMeta\Settings\SettingsRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Class LicenseManager
 */
class LicenseManager {

	/**
	 * License statuses.
	 */
	public const STATUS_UNCONFIGURED = 'unconfigured';
	public const STATUS_INACTIVE     = 'inactive';
	public const STATUS_VALID        = 'valid';
	public const STATUS_EXPIRED      = 'expired';
	public const STATUS_INVALID      = 'invalid';

	/**
	 * Check whether the external licensing provider endpoint is configured.
	 *
	 * @return bool
	 */
	public static function is_provider_configured(): bool {
		/**
		 * Filter the licensing provider API endpoint URL.
		 *
		 * @param string $endpoint Provider endpoint URL.
		 */
		$endpoint = apply_filters( 'wpcalibrate_csm_license_api_endpoint', '' );
		return ! empty( $endpoint );
	}

	/**
	 * Attempt license activation.
	 *
	 * @param string $license_key License key string.
	 * @return bool|WP_Error
	 */
	public static function activate( string $license_key ): bool|WP_Error {
		$clean_key = sanitize_text_field( trim( $license_key ) );
		if ( empty( $clean_key ) ) {
			return new WP_Error( 'empty_key', __( 'Please provide a valid license key.', 'wpcalibrate-catalog-sync-meta' ) );
		}

		SettingsRegistry::set( 'license_key', $clean_key );

		if ( ! self::is_provider_configured() ) {
			SettingsRegistry::set( 'license_status', self::STATUS_UNCONFIGURED );
			Logger::info( 'License activation requested but provider is unconfigured.' );
			return new WP_Error(
				'licensing_unconfigured',
				__( 'BLOCKED BY EXTERNAL CONFIGURATION: WPCalibrate licensing server endpoint has not yet been configured. The licensing manager is ready to connect once provider credentials are provided.', 'wpcalibrate-catalog-sync-meta' )
			);
		}

		// Production provider integration hooks here when endpoint supplied.
		return new WP_Error( 'not_implemented', __( 'Licensing endpoint not reachable.', 'wpcalibrate-catalog-sync-meta' ) );
	}

	/**
	 * Deactivate license key.
	 *
	 * @return bool|WP_Error
	 */
	public static function deactivate(): bool|WP_Error {
		SettingsRegistry::set( 'license_key', '' );
		SettingsRegistry::set( 'license_status', self::STATUS_UNCONFIGURED );
		SettingsRegistry::set( 'license_expiry', '' );

		Logger::info( 'License key cleared.' );
		return true;
	}

	/**
	 * Get masked license key for display.
	 *
	 * @return string
	 */
	public static function get_masked_key(): string {
		$key = (string) SettingsRegistry::get( 'license_key', '' );
		return ! empty( $key ) ? SecurityHelper::mask_secret( $key, 4, 4 ) : '';
	}

	/**
	 * Get current status.
	 *
	 * @return string
	 */
	public static function get_status(): string {
		return (string) SettingsRegistry::get( 'license_status', self::STATUS_UNCONFIGURED );
	}
}
