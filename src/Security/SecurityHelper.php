<?php
/**
 * Security and Authorization Helper.
 *
 * @package WPCalibrate\CatalogSyncMeta
 */

namespace WPCalibrate\CatalogSyncMeta\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Class SecurityHelper
 */
class SecurityHelper {

	/**
	 * Default admin capability required to manage plugin.
	 *
	 * @var string
	 */
	public const DEFAULT_CAPABILITY = 'manage_woocommerce';

	/**
	 * Get the required capability for administrative catalog operations.
	 *
	 * @return string
	 */
	public static function get_admin_capability(): string {
		$capability = self::DEFAULT_CAPABILITY;

		// Fallback to manage_options if WooCommerce capabilities are not available.
		if ( ! current_user_can( $capability ) && current_user_can( 'manage_options' ) ) {
			$capability = 'manage_options';
		}

		/**
		 * Filter the required capability for WPCalibrate Catalog Sync administration.
		 *
		 * @param string $capability Capability name.
		 */
		return apply_filters( 'wpcalibrate_csm_admin_capability', $capability );
	}

	/**
	 * Check whether the current user has administrative permission for this plugin.
	 *
	 * @return bool
	 */
	public static function check_admin_permission(): bool {
		return current_user_can( self::get_admin_capability() );
	}

	/**
	 * Verify WordPress Nonce for an action.
	 *
	 * @param string $nonce  Nonce token.
	 * @param string $action Nonce action name.
	 * @return bool
	 */
	public static function verify_nonce( string $nonce, string $action ): bool {
		return (bool) wp_verify_nonce( $nonce, $action );
	}

	/**
	 * Mask sensitive credentials for display or logging.
	 *
	 * @param string $secret        Secret string.
	 * @param int    $prefix_length Length of visible prefix.
	 * @param int    $suffix_length Length of visible suffix.
	 * @return string Masked string.
	 */
	public static function mask_secret( string $secret, int $prefix_length = 4, int $suffix_length = 4 ): string {
		$length = strlen( $secret );
		if ( 0 === $length ) {
			return '';
		}

		if ( $length <= ( $prefix_length + $suffix_length ) ) {
			return str_repeat( '*', min( 8, $length ) );
		}

		$prefix = substr( $secret, 0, $prefix_length );
		$suffix = substr( $secret, -$suffix_length );
		$masked = str_repeat( '*', min( 12, $length - ( $prefix_length + $suffix_length ) ) );

		return $prefix . $masked . $suffix;
	}

	/**
	 * Recursively redact sensitive keys from arrays, payloads, or debug reports.
	 *
	 * @param mixed $data Data array, object, or string.
	 * @return mixed Sanitized and redacted data.
	 */
	public static function redact_sensitive_data( mixed $data ): mixed {
		$sensitive_keys = array(
			'access_token',
			'app_secret',
			'client_secret',
			'secret',
			'license_key',
			'api_key',
			'token',
			'authorization',
			'auth_header',
			'feed_token',
			'feed_key',
			'password',
			'consumer_secret',
			'x-fb-exchange-token',
		);

		if ( is_array( $data ) ) {
			$redacted = array();
			foreach ( $data as $key => $value ) {
				$key_lower = strtolower( (string) $key );
				$is_secret = false;
				foreach ( $sensitive_keys as $secret_key ) {
					if ( str_contains( $key_lower, $secret_key ) ) {
						$is_secret = true;
						break;
					}
				}

				if ( $is_secret ) {
					$redacted[ $key ] = is_string( $value ) && ! empty( $value )
						? self::mask_secret( $value )
						: '***REDACTED***';
				} elseif ( is_array( $value ) ) {
					$redacted[ $key ] = self::redact_sensitive_data( $value );
				} else {
					$redacted[ $key ] = $value;
				}
			}
			return $redacted;
		}

		return $data;
	}

	/**
	 * Sanitize text input with length limit and HTML stripping.
	 *
	 * @param mixed $input Raw input.
	 * @param int   $max_length Max character length.
	 * @return string Sanitized string.
	 */
	public static function sanitize_text( mixed $input, int $max_length = 255 ): string {
		if ( ! is_scalar( $input ) ) {
			return '';
		}
		$clean = sanitize_text_field( (string) $input );
		return substr( $clean, 0, $max_length );
	}
}
