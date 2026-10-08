<?php
/**
 * Plugin Name:       WPCalibrate Catalog Sync for Meta
 * Plugin URI:        https://marketplace.wpcalibrate.com/
 * Description:       Synchronizes WooCommerce product catalog data with Meta (Facebook/Instagram) product catalogs with reliable background processing, incremental updates, feed generation, and diagnostics.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.2
 * Author:            WPCalibrate
 * Author URI:        https://wpcalibrate.com
 * License:           Commercial
 * License URI:       https://marketplace.wpcalibrate.com/license
 * Text Domain:       wpcalibrate-catalog-sync-meta
 * Domain Path:       /languages
 * WC requires at least: 8.5
 * WC tested up to:   11.1.2
 *
 * @package WPCalibrate\CatalogSyncMeta
 */

defined( 'ABSPATH' ) || exit;

// Define core constants.
define( 'WPCALIBRATE_CSM_VERSION', '1.0.0' );
define( 'WPCALIBRATE_CSM_FILE', __FILE__ );
define( 'WPCALIBRATE_CSM_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPCALIBRATE_CSM_URL', plugin_dir_url( __FILE__ ) );
define( 'WPCALIBRATE_CSM_BASENAME', plugin_basename( __FILE__ ) );
define( 'WPCALIBRATE_CSM_TEXT_DOMAIN', 'wpcalibrate-catalog-sync-meta' );
define( 'WPCALIBRATE_CSM_MIN_PHP', '8.2' );
define( 'WPCALIBRATE_CSM_MIN_WP', '6.5' );
define( 'WPCALIBRATE_CSM_MIN_WC', '8.5' );

// Load PSR-4 Autoloader.
require_once WPCALIBRATE_CSM_PATH . 'src/Autoloader.php';
\WPCalibrate\CatalogSyncMeta\Autoloader::register();

// Declare WooCommerce HPOS and Cart/Checkout Blocks compatibility.
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WPCALIBRATE_CSM_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', WPCALIBRATE_CSM_FILE, true );
		}
	}
);

// Register lifecycle hooks.
register_activation_hook(
	WPCALIBRATE_CSM_FILE,
	array( '\WPCalibrate\CatalogSyncMeta\Activation\Activator', 'activate' )
);

register_deactivation_hook(
	WPCALIBRATE_CSM_FILE,
	array( '\WPCalibrate\CatalogSyncMeta\Activation\Deactivator', 'deactivate' )
);

// Bootstrap plugin on plugins_loaded.
add_action(
	'plugins_loaded',
	function () {
		\WPCalibrate\CatalogSyncMeta\Plugin::instance()->init();
	}
);
