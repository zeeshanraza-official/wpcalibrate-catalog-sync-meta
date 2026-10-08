<?php
/**
 * Meta OAuth and Connection Manager.
 *
 * @package WPCalibrate\CatalogSyncMeta
 */

namespace WPCalibrate\CatalogSyncMeta\Integrations;

use WP_Error;
use WPCalibrate\CatalogSyncMeta\Api\ConnectionHealth;
use WPCalibrate\CatalogSyncMeta\Api\MetaApiClient;
use WPCalibrate\CatalogSyncMeta\Logging\Logger;
use WPCalibrate\CatalogSyncMeta\Settings\SettingsRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Class MetaOAuthManager
 */
class MetaOAuthManager {

	/**
	 * Required Meta Graph API permissions for product catalog synchronization.
	 *
	 * @var array<string>
	 */
	public const REQUIRED_PERMISSIONS = array(
		'catalog_management',
		'business_management',
	);

	/**
	 * Check whether production OAuth credentials are fully configured.
	 *
	 * @return bool
	 */
	public static function is_oauth_configured(): bool {
		$app_id     = (string) SettingsRegistry::get( 'meta_app_id', '' );
		$app_secret = (string) SettingsRegistry::get( 'meta_app_secret', '' );

		return ! empty( $app_id ) && ! empty( $app_secret );
	}

	/**
	 * Get clear reason why production OAuth is blocked if not configured.
	 *
	 * @return string
	 */
	public static function get_oauth_block_reason(): string {
		if ( ! self::is_oauth_configured() ) {
			return __( 'BLOCKED BY EXTERNAL CONFIGURATION: Production Meta App ID and App Secret have not yet been supplied. Direct 1-click OAuth requires registered Meta App credentials with approved catalog_management and business_management permissions.', 'wpcalibrate-catalog-sync-meta' );
		}
		return '';
	}

	/**
	 * Get the OAuth redirect URI for this WordPress installation.
	 *
	 * @return string
	 */
	public static function get_redirect_uri(): string {
		return admin_url( 'admin.php?page=wpcalibrate-catalog-sync-meta&tab=connection&action=oauth_callback' );
	}

	/**
	 * Generate OAuth authorization URL.
	 *
	 * @return string|WP_Error URL string or WP_Error if credentials missing.
	 */
	public static function get_authorization_url(): string|WP_Error {
		if ( ! self::is_oauth_configured() ) {
			return new WP_Error(
				'oauth_not_configured',
				self::get_oauth_block_reason()
			);
		}

		$app_id       = (string) SettingsRegistry::get( 'meta_app_id', '' );
		$redirect_uri = self::get_redirect_uri();
		$state        = wp_create_nonce( 'wpcalibrate_csm_oauth_state' );

		// Store state in transient for CSRF verification.
		set_transient( 'wpcalibrate_csm_oauth_state_' . get_current_user_id(), $state, 15 * MINUTE_IN_SECONDS );

		$params = array(
			'client_id'     => $app_id,
			'redirect_uri'  => $redirect_uri,
			'state'         => $state,
			'scope'         => implode( ',', self::REQUIRED_PERMISSIONS ),
			'response_type' => 'code',
		);

		return add_query_arg( $params, 'https://www.facebook.com/' . MetaApiClient::GRAPH_API_VERSION . '/dialog/oauth' );
	}

	/**
	 * Handle incoming OAuth callback from Meta.
	 *
	 * @param string $code  Authorization code.
	 * @param string $state CSRF state string.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function handle_oauth_callback( string $code, string $state ): bool|WP_Error {
		$saved_state = get_transient( 'wpcalibrate_csm_oauth_state_' . get_current_user_id() );
		delete_transient( 'wpcalibrate_csm_oauth_state_' . get_current_user_id() );

		if ( empty( $saved_state ) || ! hash_equals( (string) $saved_state, $state ) ) {
			return new WP_Error( 'invalid_state', __( 'Invalid or expired OAuth state parameter (CSRF protection).', 'wpcalibrate-catalog-sync-meta' ) );
		}

		$app_id       = (string) SettingsRegistry::get( 'meta_app_id', '' );
		$app_secret   = (string) SettingsRegistry::get( 'meta_app_secret', '' );
		$redirect_uri = self::get_redirect_uri();

		// Exchange code for short-lived access token.
		$token_url = 'https://graph.facebook.com/' . MetaApiClient::GRAPH_API_VERSION . '/oauth/access_token';
		$params    = array(
			'client_id'     => $app_id,
			'client_secret' => $app_secret,
			'redirect_uri'  => $redirect_uri,
			'code'          => $code,
		);

		$response = wp_remote_get( add_query_arg( $params, $token_url ), array( 'timeout' => 30 ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['access_token'] ) ) {
			return new WP_Error( 'token_exchange_failed', __( 'Could not obtain access token from Meta.', 'wpcalibrate-catalog-sync-meta' ) );
		}

		$short_token = $body['access_token'];

		// Exchange for long-lived system token.
		$long_token = self::exchange_for_long_lived_token( $short_token, $app_id, $app_secret );
		$final_token = is_wp_error( $long_token ) ? $short_token : $long_token;

		SettingsRegistry::set( 'meta_access_token', $final_token );

		// Run health check to update connection status.
		ConnectionHealth::check_health();

		Logger::info( 'Successfully connected Meta account via OAuth.' );
		return true;
	}

	/**
	 * Exchange a short-lived user token for a long-lived access token (60-day token).
	 *
	 * @param string $short_token Short-lived user access token.
	 * @param string $app_id      Meta App ID.
	 * @param string $app_secret  Meta App Secret.
	 * @return string|WP_Error
	 */
	public static function exchange_for_long_lived_token( string $short_token, string $app_id, string $app_secret ): string|WP_Error {
		$exchange_url = 'https://graph.facebook.com/' . MetaApiClient::GRAPH_API_VERSION . '/oauth/access_token';
		$params       = array(
			'grant_type'        => 'fb_exchange_token',
			'client_id'         => $app_id,
			'client_secret'     => $app_secret,
			'fb_exchange_token' => $short_token,
		);

		$res = wp_remote_get( add_query_arg( $params, $exchange_url ), array( 'timeout' => 30 ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}

		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( ! empty( $data['access_token'] ) ) {
			return $data['access_token'];
		}

		return new WP_Error( 'long_lived_exchange_failed', __( 'Could not exchange for long-lived Meta token.', 'wpcalibrate-catalog-sync-meta' ) );
	}

	/**
	 * Disconnect active Meta connection safely.
	 *
	 * @return void
	 */
	public static function disconnect(): void {
		SettingsRegistry::set( 'meta_access_token', '' );
		SettingsRegistry::set( 'meta_catalog_id', '' );
		SettingsRegistry::set( 'meta_business_id', '' );
		SettingsRegistry::set( 'meta_connection_status', ConnectionHealth::STATUS_NOT_CONFIGURED );
		SettingsRegistry::set( 'meta_last_connection_check', time() );

		Logger::info( 'Meta connection was disconnected by administrator.' );
	}
}
