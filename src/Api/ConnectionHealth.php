<?php
/**
 * Connection Health Check Service.
 *
 * @package WPCalibrate\CatalogSyncMeta
 */

namespace WPCalibrate\CatalogSyncMeta\Api;

use WP_Error;
use WPCalibrate\CatalogSyncMeta\Settings\SettingsRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Class ConnectionHealth
 */
class ConnectionHealth {

	/**
	 * Connection status constants.
	 */
	public const STATUS_NOT_CONFIGURED       = 'not_configured';
	public const STATUS_CONNECTED            = 'connected';
	public const STATUS_TOKEN_EXPIRED         = 'token_expired';
	public const STATUS_PERMISSION_DENIED    = 'permission_denied';
	public const STATUS_CATALOG_UNAVAILABLE  = 'catalog_unavailable';
	public const STATUS_API_UNREACHABLE      = 'api_unreachable';
	public const STATUS_RATE_LIMITED         = 'rate_limited';

	/**
	 * Run a live connection health check against the Meta API.
	 *
	 * @return array{status: string, message: string, catalog_info: array|null}
	 */
	public static function check_health(): array {
		$token      = (string) SettingsRegistry::get( 'meta_access_token', '' );
		$catalog_id = (string) SettingsRegistry::get( 'meta_catalog_id', '' );

		if ( empty( $token ) || empty( $catalog_id ) ) {
			self::save_status( self::STATUS_NOT_CONFIGURED );
			return array(
				'status'       => self::STATUS_NOT_CONFIGURED,
				'message'      => __( 'Meta access token or Catalog ID is not configured.', 'wpcalibrate-catalog-sync-meta' ),
				'catalog_info' => null,
			);
		}

		// Perform real lightweight probe request against the catalog endpoint.
		$endpoint = '/' . rawurlencode( $catalog_id );
		$params   = array(
			'fields' => 'id,name,vertical,product_count',
		);

		$response = MetaApiClient::request( $endpoint, 'GET', $params, $token );

		if ( is_wp_error( $response ) ) {
			$error_data     = $response->get_error_data();
			$classification = $error_data['classification'] ?? 'unknown';

			$status = match ( $classification ) {
				'token_expired'     => self::STATUS_TOKEN_EXPIRED,
				'permission_denied' => self::STATUS_PERMISSION_DENIED,
				'invalid_catalog'   => self::STATUS_CATALOG_UNAVAILABLE,
				'rate_limited'      => self::STATUS_RATE_LIMITED,
				default             => self::STATUS_API_UNREACHABLE,
			};

			self::save_status( $status );

			return array(
				'status'       => $status,
				'message'      => $response->get_error_message(),
				'catalog_info' => null,
			);
		}

		if ( ! empty( $response['id'] ) ) {
			self::save_status( self::STATUS_CONNECTED );
			return array(
				'status'       => self::STATUS_CONNECTED,
				'message'      => sprintf(
					/* translators: 1: Catalog Name, 2: Catalog ID */
					__( 'Successfully connected to catalog "%1$s" (ID: %2$s).', 'wpcalibrate-catalog-sync-meta' ),
					esc_html( $response['name'] ?? '' ),
					esc_html( $response['id'] )
				),
				'catalog_info' => $response,
			);
		}

		self::save_status( self::STATUS_API_UNREACHABLE );
		return array(
			'status'       => self::STATUS_API_UNREACHABLE,
			'message'      => __( 'Unexpected response structure from Meta API.', 'wpcalibrate-catalog-sync-meta' ),
			'catalog_info' => null,
		);
	}

	/**
	 * Save verified status into settings.
	 *
	 * @param string $status Health status code.
	 * @return void
	 */
	private static function save_status( string $status ): void {
		SettingsRegistry::set( 'meta_connection_status', $status );
		SettingsRegistry::set( 'meta_last_connection_check', time() );
	}

	/**
	 * Get human-readable label and CSS class for current status.
	 *
	 * @param string|null $status Optional status code.
	 * @return array{label: string, badge_class: string}
	 */
	public static function get_status_presentation( ?string $status = null ): array {
		$code = $status ?: (string) SettingsRegistry::get( 'meta_connection_status', self::STATUS_NOT_CONFIGURED );

		return match ( $code ) {
			self::STATUS_CONNECTED => array(
				'label'       => __( 'Connected & Verified', 'wpcalibrate-catalog-sync-meta' ),
				'badge_class' => 'notice-success',
			),
			self::STATUS_TOKEN_EXPIRED => array(
				'label'       => __( 'Token Expired / Invalid', 'wpcalibrate-catalog-sync-meta' ),
				'badge_class' => 'notice-error',
			),
			self::STATUS_PERMISSION_DENIED => array(
				'label'       => __( 'Insufficient Permissions', 'wpcalibrate-catalog-sync-meta' ),
				'badge_class' => 'notice-warning',
			),
			self::STATUS_CATALOG_UNAVAILABLE => array(
				'label'       => __( 'Catalog Unavailable', 'wpcalibrate-catalog-sync-meta' ),
				'badge_class' => 'notice-error',
			),
			self::STATUS_API_UNREACHABLE => array(
				'label'       => __( 'Meta API Unreachable', 'wpcalibrate-catalog-sync-meta' ),
				'badge_class' => 'notice-error',
			),
			self::STATUS_RATE_LIMITED => array(
				'label'       => __( 'Rate Limited', 'wpcalibrate-catalog-sync-meta' ),
				'badge_class' => 'notice-warning',
			),
			default => array(
				'label'       => __( 'Not Connected / Unconfigured', 'wpcalibrate-catalog-sync-meta' ),
				'badge_class' => 'notice-info',
			),
		};
	}
}
