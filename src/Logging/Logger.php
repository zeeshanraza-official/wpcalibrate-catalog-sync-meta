<?php
/**
 * Sanitized WooCommerce Logger Wrapper.
 *
 * @package WPCalibrate\CatalogSyncMeta
 */

namespace WPCalibrate\CatalogSyncMeta\Logging;

use WPCalibrate\CatalogSyncMeta\Security\SecurityHelper;
use WPCalibrate\CatalogSyncMeta\Settings\SettingsRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Class Logger
 */
class Logger {

	/**
	 * Log source identifier for WooCommerce Logger.
	 *
	 * @var string
	 */
	public const LOG_SOURCE = 'wpcalibrate-catalog-sync-meta';

	/**
	 * Log levels hierarchy.
	 *
	 * @var array<string, int>
	 */
	private const LEVELS = array(
		'debug'   => 1,
		'info'    => 2,
		'notice'  => 3,
		'warning' => 4,
		'error'   => 5,
	);

	/**
	 * Log an info level message.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Context data (will be redacted).
	 * @return void
	 */
	public static function info( string $message, array $context = array() ): void {
		self::log( 'info', $message, $context );
	}

	/**
	 * Log a warning level message.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Context data (will be redacted).
	 * @return void
	 */
	public static function warning( string $message, array $context = array() ): void {
		self::log( 'warning', $message, $context );
	}

	/**
	 * Log an error level message.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Context data (will be redacted).
	 * @return void
	 */
	public static function error( string $message, array $context = array() ): void {
		self::log( 'error', $message, $context );
	}

	/**
	 * Log a debug level message.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Context data (will be redacted).
	 * @return void
	 */
	public static function debug( string $message, array $context = array() ): void {
		self::log( 'debug', $message, $context );
	}

	/**
	 * Write a log entry.
	 *
	 * @param string               $level   Log level.
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Context details.
	 * @return void
	 */
	public static function log( string $level, string $message, array $context = array() ): void {
		$configured_level = SettingsRegistry::get( 'log_level', 'info' );
		$level_int        = self::LEVELS[ $level ] ?? 2;
		$threshold_int    = self::LEVELS[ $configured_level ] ?? 2;

		if ( $level_int < $threshold_int ) {
			return;
		}

		// Strictly redact all credentials, keys, headers, and tokens.
		$sanitized_context = SecurityHelper::redact_sensitive_data( $context );
		$sanitized_context['source'] = self::LOG_SOURCE;

		if ( function_exists( 'wc_get_logger' ) ) {
			$wc_logger = wc_get_logger();
			if ( $wc_logger ) {
				$wc_logger->log( $level, $message, $sanitized_context );
				return;
			}
		}

		// Fallback if WooCommerce logger not initialized.
		$log_entry = sprintf(
			'[%s] [%s] %s %s',
			gmdate( 'Y-m-d H:i:s' ),
			strtoupper( $level ),
			$message,
			! empty( $sanitized_context ) ? wp_json_encode( $sanitized_context ) : ''
		);

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( self::LOG_SOURCE . ': ' . $log_entry );
	}
}
