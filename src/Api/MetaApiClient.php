<?php
/**
 * Official Meta Graph API Client Abstraction.
 *
 * @package WPCalibrate\CatalogSyncMeta
 */

namespace WPCalibrate\CatalogSyncMeta\Api;

use WP_Error;
use WPCalibrate\CatalogSyncMeta\Logging\Logger;
use WPCalibrate\CatalogSyncMeta\Security\SecurityHelper;
use WPCalibrate\CatalogSyncMeta\Settings\SettingsRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Class MetaApiClient
 */
class MetaApiClient {

	/**
	 * Meta Graph API version.
	 *
	 * @var string
	 */
	public const GRAPH_API_VERSION = 'v21.0';

	/**
	 * Meta Graph API base endpoint.
	 *
	 * @var string
	 */
	public const BASE_URL = 'https://graph.facebook.com/' . self::GRAPH_API_VERSION;

	/**
	 * Default request timeout in seconds.
	 *
	 * @var int
	 */
	public const TIMEOUT_SECONDS = 30;

	/**
	 * Send an authenticated request to Meta Graph API.
	 *
	 * @param string               $endpoint Endpoint relative path (e.g. '/{catalog_id}/items_batch').
	 * @param string               $method   HTTP Method ('GET', 'POST', 'DELETE').
	 * @param array<string, mixed> $body     Request body params.
	 * @param string|null          $token    Optional override access token.
	 * @return array<string, mixed>|WP_Error Response array on success, WP_Error on failure.
	 */
	public static function request(
		string $endpoint,
		string $method = 'GET',
		array $body = array(),
		?string $token = null
	): array|WP_Error {
		$access_token = $token ?: (string) SettingsRegistry::get( 'meta_access_token', '' );

		if ( empty( $access_token ) ) {
			return new WP_Error(
				'meta_auth_missing',
				__( 'Meta API access token is missing or not configured.', 'wpcalibrate-catalog-sync-meta' )
			);
		}

		$url = self::BASE_URL . '/' . ltrim( $endpoint, '/' );

		$args = array(
			'method'      => strtoupper( $method ),
			'timeout'     => self::TIMEOUT_SECONDS,
			'redirection' => 5,
			'httpversion' => '1.1',
			'blocking'    => true,
			'headers'     => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Content-Type'  => 'application/json; charset=utf-8',
				'Accept'        => 'application/json',
				'User-Agent'    => 'WPCalibrate-CatalogSyncMeta/' . WPCALIBRATE_CSM_VERSION,
			),
			'sslverify'   => true, // Strictly verify SSL in production.
		);

		if ( 'POST' === $args['method'] || 'PUT' === $args['method'] ) {
			$args['body'] = wp_json_encode( $body );
		} elseif ( 'GET' === $args['method'] && ! empty( $body ) ) {
			$url = add_query_arg( $body, $url );
		}

		// Execute request using WordPress HTTP API.
		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			Logger::error(
				'Network/HTTP Error calling Meta API: ' . $response->get_error_message(),
				array( 'endpoint' => $endpoint )
			);
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$raw_body    = wp_remote_retrieve_body( $response );
		$headers     = wp_remote_retrieve_headers( $response );

		$data = json_decode( $raw_body, true );

		// Check for rate limiting in response headers.
		self::check_rate_limits( $headers );

		// Check HTTP error status.
		if ( $status_code < 200 || $status_code >= 300 ) {
			return self::handle_api_error_response( $status_code, $data );
		}

		if ( ! is_array( $data ) ) {
			return new WP_Error(
				'meta_invalid_json',
				__( 'Malformed response body received from Meta API.', 'wpcalibrate-catalog-sync-meta' ),
				array( 'status_code' => $status_code )
			);
		}

		return $data;
	}

	/**
	 * Send batch of items to Meta Catalog (`/{catalog_id}/items_batch`).
	 *
	 * @param string               $catalog_id Catalog ID.
	 * @param array<array<string, mixed>> $requests   Array of item batch operations.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function send_items_batch( string $catalog_id, array $requests ): array|WP_Error {
		if ( empty( $catalog_id ) ) {
			return new WP_Error( 'missing_catalog_id', __( 'Meta Catalog ID is not configured.', 'wpcalibrate-catalog-sync-meta' ) );
		}

		$payload = array(
			'requests' => $requests,
		);

		return self::request( '/' . rawurlencode( $catalog_id ) . '/items_batch', 'POST', $payload );
	}

	/**
	 * Fetch catalogs accessible by the current token or business.
	 *
	 * @param string|null $business_id Optional business ID.
	 * @return array<array<string, mixed>>|WP_Error Array of catalog objects or error.
	 */
	public static function get_catalogs( ?string $business_id = null ): array|WP_Error {
		$endpoint = $business_id ? '/' . rawurlencode( $business_id ) . '/owned_product_catalogs' : '/me/product_catalogs';
		$params   = array(
			'fields' => 'id,name,vertical,product_count',
			'limit'  => 50,
		);

		$res = self::request( $endpoint, 'GET', $params );
		if ( is_wp_error( $res ) ) {
			return $res;
		}

		return isset( $res['data'] ) && is_array( $res['data'] ) ? $res['data'] : array();
	}

	/**
	 * Parse and classify Meta API error response.
	 *
	 * @param int         $status_code HTTP status code.
	 * @param mixed       $data        Decoded response data.
	 * @return WP_Error
	 */
	private static function handle_api_error_response( int $status_code, mixed $data ): WP_Error {
		$error_info = is_array( $data ) && isset( $data['error'] ) ? $data['error'] : array();

		$code     = $error_info['code'] ?? $status_code;
		$subcode  = $error_info['error_subcode'] ?? 0;
		$message  = $error_info['message'] ?? sprintf( __( 'HTTP %d error from Meta API.', 'wpcalibrate-catalog-sync-meta' ), $status_code );
		$type     = $error_info['type'] ?? 'MetaApiException';

		// Classify error type.
		$classification = 'unknown';

		if ( 190 === (int) $code ) {
			$classification = 'token_expired';
		} elseif ( in_array( (int) $code, array( 4, 17, 32, 613 ), true ) ) {
			$classification = 'rate_limited';
		} elseif ( in_array( (int) $code, array( 10, 200, 298 ), true ) ) {
			$classification = 'permission_denied';
		} elseif ( in_array( (int) $code, array( 100, 10800 ), true ) ) {
			$classification = 'invalid_catalog';
		}

		Logger::error(
			sprintf( 'Meta API Error [%s/%s]: %s', $code, $subcode, $message ),
			array(
				'status'         => $status_code,
				'classification' => $classification,
				'error_type'     => $type,
			)
		);

		return new WP_Error(
			'meta_api_error_' . $classification,
			$message,
			array(
				'code'           => $code,
				'subcode'        => $subcode,
				'classification' => $classification,
				'status_code'    => $status_code,
			)
		);
	}

	/**
	 * Inspect headers for Meta rate limiting telemetry.
	 *
	 * @param mixed $headers Response headers.
	 * @return void
	 */
	private static function check_rate_limits( mixed $headers ): void {
		if ( empty( $headers ) ) {
			return;
		}

		// Meta headers include: x-business-use-case-usage, x-app-usage.
		$app_usage = $headers['x-app-usage'] ?? null;
		if ( $app_usage ) {
			$usage = json_decode( $app_usage, true );
			if ( is_array( $usage ) && isset( $usage['call_count'] ) && (int) $usage['call_count'] > 80 ) {
				Logger::warning( 'Meta API call count nearing rate limit (>80%): ' . $app_usage );
			}
		}
	}
}
