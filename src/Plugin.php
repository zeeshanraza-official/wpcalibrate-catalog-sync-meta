<?php
/**
 * Main Plugin Orchestrator.
 *
 * @package WPCalibrate\CatalogSyncMeta
 */

namespace WPCalibrate\CatalogSyncMeta;

use WPCalibrate\CatalogSyncMeta\Admin\AdminController;
use WPCalibrate\CatalogSyncMeta\Admin\AdminMenu;
use WPCalibrate\CatalogSyncMeta\Admin\ProductMetabox;
use WPCalibrate\CatalogSyncMeta\Compatibility\CompatibilityChecker;
use WPCalibrate\CatalogSyncMeta\Data\MigrationManager;
use WPCalibrate\CatalogSyncMeta\Feed\XmlFeedGenerator;
use WPCalibrate\CatalogSyncMeta\Queue\QueueManager;
use WPCalibrate\CatalogSyncMeta\Rest\RestServer;
use WPCalibrate\CatalogSyncMeta\Sync\ChangeDetector;
use WPCalibrate\CatalogSyncMeta\Sync\ReconciliationService;

defined( 'ABSPATH' ) || exit;

/**
 * Class Plugin
 */
class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {}

	/**
	 * Initialize plugin components.
	 *
	 * @return void
	 */
	public function init(): void {
		// Load plugin translations.
		load_plugin_textdomain(
			WPCALIBRATE_CSM_TEXT_DOMAIN,
			false,
			dirname( WPCALIBRATE_CSM_BASENAME ) . '/languages'
		);

		// Check system and WooCommerce dependencies.
		if ( ! CompatibilityChecker::is_compatible() ) {
			add_action( 'admin_notices', array( CompatibilityChecker::class, 'render_admin_notices' ) );
			return; // Fail gracefully without crashing WordPress.
		}

		// Run database migrations if version changed.
		MigrationManager::maybe_migrate();

		// Register admin UI subsystems.
		AdminMenu::register_hooks();
		AdminController::register_hooks();
		ProductMetabox::register_hooks();

		// Register background queue and change detection.
		QueueManager::register_hooks();
		ChangeDetector::register_hooks();

		// Register reconciliation step hook.
		add_action( 'wpcalibrate_csm_reconcile_step', array( ReconciliationService::class, 'process_step' ) );

		// Register XML feed interception on init.
		add_action( 'init', array( XmlFeedGenerator::class, 'maybe_serve_feed' ), 1 );

		// Register REST API endpoints.
		add_action( 'rest_api_init', array( RestServer::class, 'register_routes' ) );
	}
}
