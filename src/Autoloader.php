<?php
/**
 * PSR-4 Autoloader for WPCalibrate Catalog Sync for Meta.
 *
 * @package WPCalibrate\CatalogSyncMeta
 */

namespace WPCalibrate\CatalogSyncMeta;

defined( 'ABSPATH' ) || exit;

/**
 * Class Autoloader
 */
class Autoloader {

	/**
	 * Namespace prefix to match.
	 *
	 * @var string
	 */
	private const NAMESPACE_PREFIX = 'WPCalibrate\\CatalogSyncMeta\\';

	/**
	 * Register the autoloader.
	 *
	 * @return void
	 */
	public static function register(): void {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Autoload a class by name.
	 *
	 * @param string $class Fully-qualified class name.
	 * @return void
	 */
	public static function autoload( string $class ): void {
		if ( ! str_starts_with( $class, self::NAMESPACE_PREFIX ) ) {
			return;
		}

		$relative_class = substr( $class, strlen( self::NAMESPACE_PREFIX ) );
		$file           = dirname( __DIR__ ) . '/src/' . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}
