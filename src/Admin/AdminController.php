<?php
/**
 * Main Admin Settings Controller and Tabbed UI Renderer.
 *
 * @package WPCalibrate\CatalogSyncMeta
 */

namespace WPCalibrate\CatalogSyncMeta\Admin;

use WPCalibrate\CatalogSyncMeta\Api\ConnectionHealth;
use WPCalibrate\CatalogSyncMeta\Data\SyncEventRepository;
use WPCalibrate\CatalogSyncMeta\Feed\XmlFeedGenerator;
use WPCalibrate\CatalogSyncMeta\Integrations\MetaOAuthManager;
use WPCalibrate\CatalogSyncMeta\Licensing\LicenseManager;
use WPCalibrate\CatalogSyncMeta\Logging\DiagnosticsReporter;
use WPCalibrate\CatalogSyncMeta\Logging\Logger;
use WPCalibrate\CatalogSyncMeta\Queue\QueueManager;
use WPCalibrate\CatalogSyncMeta\Security\SecurityHelper;
use WPCalibrate\CatalogSyncMeta\Settings\SettingsRegistry;
use WPCalibrate\CatalogSyncMeta\Support\SupportManager;
use WPCalibrate\CatalogSyncMeta\Sync\DirectSyncService;
use WPCalibrate\CatalogSyncMeta\Sync\ReconciliationService;

defined( 'ABSPATH' ) || exit;

/**
 * Class AdminController
 */
class AdminController {

	/**
	 * Nonce action for saving settings.
	 */
	public const SAVE_NONCE = 'wpcalibrate_csm_save_settings_action';

	/**
	 * Register admin controller hooks.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'admin_init', array( __CLASS__, 'handle_form_actions' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue admin CSS and JS only on plugin screens and product edit screen.
	 *
	 * @param string $hook_suffix Admin hook suffix.
	 * @return void
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		$is_plugin_screen = isset( $_GET['page'] ) && AdminMenu::SUBMENU_SLUG === $_GET['page'];
		$is_product_screen = in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) && 'product' === get_post_type();

		if ( ! $is_plugin_screen && ! $is_product_screen ) {
			return;
		}

		wp_enqueue_style(
			'wpcalibrate-csm-admin-css',
			WPCALIBRATE_CSM_URL . 'assets/css/admin.css',
			array(),
			WPCALIBRATE_CSM_VERSION
		);

		wp_enqueue_script(
			'wpcalibrate-csm-admin-js',
			WPCALIBRATE_CSM_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			WPCALIBRATE_CSM_VERSION,
			true
		);

		wp_localize_script(
			'wpcalibrate-csm-admin-js',
			'wpcalibrateCsmL10n',
			array(
				'confirmReconcile' => __( 'Start a full catalog reconciliation? This will scan and queue all eligible products.', 'wpcalibrate-catalog-sync-meta' ),
				'confirmDisconnect' => __( 'Disconnect Meta connection? Existing synchronization settings will be cleared.', 'wpcalibrate-catalog-sync-meta' ),
				'copied'           => __( 'Copied to clipboard!', 'wpcalibrate-catalog-sync-meta' ),
				'copyFail'         => __( 'Failed to copy to clipboard.', 'wpcalibrate-catalog-sync-meta' ),
			)
		);
	}

	/**
	 * Handle admin POST/GET actions.
	 *
	 * @return void
	 */
	public static function handle_form_actions(): void {
		// Check that we are on our plugin page.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['page'] ) || AdminMenu::SUBMENU_SLUG !== $_GET['page'] ) {
			return;
		}

		// Handle tab save actions.
		if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['wpcalibrate_csm_save_tab'] ) ) {
			check_admin_referer( self::SAVE_NONCE, 'wpcalibrate_csm_nonce' );

			if ( ! SecurityHelper::check_admin_permission() ) {
				wp_die( esc_html__( 'Unauthorized permission.', 'wpcalibrate-catalog-sync-meta' ) );
			}

			self::save_tab_settings( sanitize_key( $_POST['wpcalibrate_csm_save_tab'] ) );
		}

		// Handle quick GET actions (e.g. pause/resume, test connection, retry).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['csm_action'] ) ) {
			$action = sanitize_key( $_GET['csm_action'] );
			check_admin_referer( 'csm_action_' . $action );

			if ( ! SecurityHelper::check_admin_permission() ) {
				wp_die( esc_html__( 'Unauthorized permission.', 'wpcalibrate-catalog-sync-meta' ) );
			}

			self::execute_admin_action( $action );
		}
	}

	/**
	 * Save settings submitted by specific tab.
	 *
	 * @param string $tab Tab identifier.
	 * @return void
	 */
	private static function save_tab_settings( string $tab ): void {
		$all = SettingsRegistry::get_all( true );

		switch ( $tab ) {
			case 'connection':
				$all['meta_app_id']       = sanitize_text_field( $_POST['meta_app_id'] ?? '' );
				$all['meta_app_secret']   = sanitize_text_field( $_POST['meta_app_secret'] ?? '' );
				$all['meta_access_token'] = sanitize_text_field( $_POST['meta_access_token'] ?? '' );
				$all['meta_business_id']  = sanitize_text_field( $_POST['meta_business_id'] ?? '' );
				$all['meta_catalog_id']   = sanitize_text_field( $_POST['meta_catalog_id'] ?? '' );
				SettingsRegistry::update_all( $all );
				ConnectionHealth::check_health();
				add_settings_error( 'wpcalibrate_csm', 'saved', __( 'Meta connection settings saved.', 'wpcalibrate-catalog-sync-meta' ), 'success' );
				break;

			case 'rules':
				$all['sync_simple_products']   = isset( $_POST['sync_simple_products'] );
				$all['sync_variable_products'] = isset( $_POST['sync_variable_products'] );
				$all['sync_variations']        = isset( $_POST['sync_variations'] );
				$all['exclude_out_of_stock']   = isset( $_POST['exclude_out_of_stock'] );
				$all['exclude_hidden_catalog'] = isset( $_POST['exclude_hidden_catalog'] );
				$all['exclude_without_price']  = isset( $_POST['exclude_without_price'] );
				$all['exclude_without_image']  = isset( $_POST['exclude_without_image'] );

				$excluded_cats = isset( $_POST['excluded_categories'] ) && is_array( $_POST['excluded_categories'] )
					? array_map( 'absint', $_POST['excluded_categories'] )
					: array();
				$all['excluded_categories'] = $excluded_cats;

				$included_cats = isset( $_POST['included_categories'] ) && is_array( $_POST['included_categories'] )
					? array_map( 'absint', $_POST['included_categories'] )
					: array();
				$all['included_categories'] = $included_cats;

				SettingsRegistry::update_all( $all );
				add_settings_error( 'wpcalibrate_csm', 'saved', __( 'Product eligibility rules saved.', 'wpcalibrate-catalog-sync-meta' ), 'success' );
				break;

			case 'mapping':
				$all['identifier_type']    = sanitize_key( $_POST['identifier_type'] ?? 'product_id' );
				$all['brand_source']       = sanitize_key( $_POST['brand_source'] ?? 'attribute' );
				$all['brand_field']        = sanitize_text_field( $_POST['brand_field'] ?? 'pa_brand' );
				$all['gtin_source']        = sanitize_key( $_POST['gtin_source'] ?? 'custom_meta' );
				$all['gtin_field']         = sanitize_text_field( $_POST['gtin_field'] ?? '_gtin' );
				$all['mpn_source']         = sanitize_key( $_POST['mpn_source'] ?? 'sku' );
				$all['mpn_field']          = sanitize_text_field( $_POST['mpn_field'] ?? '' );
				$all['condition_source']   = sanitize_key( $_POST['condition_source'] ?? 'static' );
				$all['condition_default']  = sanitize_key( $_POST['condition_default'] ?? 'new' );
				$all['description_source'] = sanitize_key( $_POST['description_source'] ?? 'full_description' );
				SettingsRegistry::update_all( $all );
				add_settings_error( 'wpcalibrate_csm', 'saved', __( 'Field mappings saved.', 'wpcalibrate-catalog-sync-meta' ), 'success' );
				break;

			case 'sync':
				$all['enable_auto_sync']        = isset( $_POST['enable_auto_sync'] );
				$all['batch_size']              = max( 5, min( 250, absint( $_POST['batch_size'] ?? 50 ) ) );
				$all['throttle_delay_ms']       = max( 0, min( 5000, absint( $_POST['throttle_delay_ms'] ?? 100 ) ) );
				$all['max_retries']             = max( 1, min( 10, absint( $_POST['max_retries'] ?? 3 ) ) );
				$all['reconciliation_interval'] = sanitize_key( $_POST['reconciliation_interval'] ?? 'daily' );
				SettingsRegistry::update_all( $all );
				add_settings_error( 'wpcalibrate_csm', 'saved', __( 'Synchronization settings saved.', 'wpcalibrate-catalog-sync-meta' ), 'success' );
				break;

			case 'feed':
				$all['enable_feed'] = isset( $_POST['enable_feed'] );
				SettingsRegistry::update_all( $all );
				add_settings_error( 'wpcalibrate_csm', 'saved', __( 'XML feed configuration saved.', 'wpcalibrate-catalog-sync-meta' ), 'success' );
				break;

			case 'logs':
				$all['log_level']                 = sanitize_key( $_POST['log_level'] ?? 'info' );
				$all['data_retention_days']       = absint( $_POST['data_retention_days'] ?? 30 );
				$all['delete_data_on_uninstall']  = isset( $_POST['delete_data_on_uninstall'] );
				SettingsRegistry::update_all( $all );
				add_settings_error( 'wpcalibrate_csm', 'saved', __( 'Logging and retention settings saved.', 'wpcalibrate-catalog-sync-meta' ), 'success' );
				break;

			case 'license':
				$key = sanitize_text_field( $_POST['license_key'] ?? '' );
				$res = LicenseManager::activate( $key );
				if ( is_wp_error( $res ) ) {
					add_settings_error( 'wpcalibrate_csm', 'lic_err', $res->get_error_message(), 'warning' );
				} else {
					add_settings_error( 'wpcalibrate_csm', 'saved', __( 'License settings updated.', 'wpcalibrate-catalog-sync-meta' ), 'success' );
				}
				break;
		}
	}

	/**
	 * Execute quick admin action.
	 *
	 * @param string $action Action key.
	 * @return void
	 */
	private static function execute_admin_action( string $action ): void {
		$tab = sanitize_key( $_GET['tab'] ?? 'dashboard' );

		switch ( $action ) {
			case 'pause_sync':
				SettingsRegistry::set( 'is_sync_paused', true );
				add_settings_error( 'wpcalibrate_csm', 'action', __( 'Automatic catalog synchronization has been paused.', 'wpcalibrate-catalog-sync-meta' ), 'warning' );
				break;

			case 'resume_sync':
				SettingsRegistry::set( 'is_sync_paused', false );
				QueueManager::schedule_batch_processing_if_needed( 0 );
				add_settings_error( 'wpcalibrate_csm', 'action', __( 'Automatic catalog synchronization has been resumed.', 'wpcalibrate-catalog-sync-meta' ), 'success' );
				break;

			case 'full_reconcile':
				$started = ReconciliationService::start_full_reconciliation();
				if ( $started ) {
					add_settings_error( 'wpcalibrate_csm', 'action', __( 'Full catalog reconciliation started in the background.', 'wpcalibrate-catalog-sync-meta' ), 'success' );
				} else {
					add_settings_error( 'wpcalibrate_csm', 'action', __( 'Full reconciliation is already currently running.', 'wpcalibrate-catalog-sync-meta' ), 'warning' );
				}
				break;

			case 'retry_failed':
				$failed_events = SyncEventRepository::get_events( array( 'status' => 'failed', 'limit' => 200 ) );
				$product_ids   = array();
				foreach ( $failed_events['items'] as $item ) {
					$product_ids[] = (int) $item->product_id;
				}
				$queued = QueueManager::enqueue_products( array_unique( $product_ids ) );
				add_settings_error(
					'wpcalibrate_csm',
					'action',
					sprintf(
						/* translators: %d: Queued product count */
						__( 'Queued %d failed products for retry.', 'wpcalibrate-catalog-sync-meta' ),
						$queued
					),
					'success'
				);
				break;

			case 'sync_changed':
				QueueManager::schedule_batch_processing_if_needed( 0 );
				add_settings_error( 'wpcalibrate_csm', 'action', __( 'Queued products are now being processed in the background.', 'wpcalibrate-catalog-sync-meta' ), 'success' );
				break;

			case 'test_connection':
				$health = ConnectionHealth::check_health();
				add_settings_error( 'wpcalibrate_csm', 'action', $health['message'], 'connected' === $health['status'] ? 'success' : 'error' );
				break;

			case 'disconnect_meta':
				MetaOAuthManager::disconnect();
				add_settings_error( 'wpcalibrate_csm', 'action', __( 'Meta connection disconnected.', 'wpcalibrate-catalog-sync-meta' ), 'info' );
				break;

			case 'regenerate_feed_token':
				XmlFeedGenerator::regenerate_token();
				add_settings_error( 'wpcalibrate_csm', 'action', __( 'Secure feed access token regenerated.', 'wpcalibrate-catalog-sync-meta' ), 'success' );
				break;

			case 'prune_logs':
				$days    = (int) SettingsRegistry::get( 'data_retention_days', 30 );
				$deleted = SyncEventRepository::prune_old_events( $days );
				add_settings_error(
					'wpcalibrate_csm',
					'action',
					sprintf(
						/* translators: %d: Number of pruned records */
						__( 'Pruned %d records older than %d days.', 'wpcalibrate-catalog-sync-meta' ),
						$deleted,
						$days
					),
					'info'
				);
				break;

			case 'deactivate_license':
				LicenseManager::deactivate();
				add_settings_error( 'wpcalibrate_csm', 'action', __( 'License deactivated.', 'wpcalibrate-catalog-sync-meta' ), 'info' );
				break;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . AdminMenu::SUBMENU_SLUG . '&tab=' . $tab ) );
		exit;
	}

	/**
	 * Render plugin main settings page.
	 *
	 * @return void
	 */
	public static function render_page(): void {
		if ( ! SecurityHelper::check_admin_permission() ) {
			wp_die( esc_html__( 'Unauthorized access.', 'wpcalibrate-catalog-sync-meta' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'overview';

		$tabs = array(
			'overview'    => __( 'Overview', 'wpcalibrate-catalog-sync-meta' ),
			'dashboard'   => __( 'Dashboard', 'wpcalibrate-catalog-sync-meta' ),
			'connection'  => __( 'Meta Connection', 'wpcalibrate-catalog-sync-meta' ),
			'catalog'     => __( 'Catalog', 'wpcalibrate-catalog-sync-meta' ),
			'rules'       => __( 'Product Rules', 'wpcalibrate-catalog-sync-meta' ),
			'mapping'     => __( 'Field Mapping', 'wpcalibrate-catalog-sync-meta' ),
			'sync'        => __( 'Sync Settings', 'wpcalibrate-catalog-sync-meta' ),
			'feed'        => __( 'Feed', 'wpcalibrate-catalog-sync-meta' ),
			'diagnostics' => __( 'Diagnostics', 'wpcalibrate-catalog-sync-meta' ),
			'logs'        => __( 'Logs', 'wpcalibrate-catalog-sync-meta' ),
			'license'     => __( 'License', 'wpcalibrate-catalog-sync-meta' ),
			'support'     => __( 'Support', 'wpcalibrate-catalog-sync-meta' ),
		);

		if ( ! array_key_exists( $current_tab, $tabs ) ) {
			$current_tab = 'overview';
		}

		?>
		<div class="wrap wpcalibrate-csm-wrap">
			<!-- Header Branding -->
			<div class="wpcalibrate-csm-header">
				<div class="wpcalibrate-csm-header-brand">
					<img src="<?php echo esc_url( WPCALIBRATE_CSM_URL . 'branding/icon-dark.png' ); ?>" alt="WPCalibrate" class="wpcalibrate-csm-header-logo" width="36" height="36" />
					<div class="wpcalibrate-csm-header-titles">
						<h1><?php esc_html_e( 'Catalog Sync for Meta', 'wpcalibrate-catalog-sync-meta' ); ?></h1>
						<span class="wpcalibrate-csm-version"><?php echo esc_html( 'v' . WPCALIBRATE_CSM_VERSION ); ?> &bull; <?php esc_html_e( 'by WPCalibrate', 'wpcalibrate-catalog-sync-meta' ); ?></span>
					</div>
				</div>
				<div class="wpcalibrate-csm-header-badge">
					<?php
					$health = ConnectionHealth::get_status_presentation();
					?>
					<span class="wpcalibrate-csm-status-indicator <?php echo esc_attr( $health['badge_class'] ); ?>">
						<?php echo esc_html( $health['label'] ); ?>
					</span>
				</div>
			</div>

			<!-- Admin Notices -->
			<?php settings_errors( 'wpcalibrate_csm' ); ?>

			<!-- Tab Navigation -->
			<nav class="nav-tab-wrapper wpcalibrate-csm-nav-tabs" aria-label="<?php esc_attr_e( 'Plugin Settings Tabs', 'wpcalibrate-catalog-sync-meta' ); ?>">
				<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SUBMENU_SLUG . '&tab=' . $tab_key ) ); ?>"
						class="nav-tab <?php echo $current_tab === $tab_key ? 'nav-tab-active' : ''; ?>"
						aria-current="<?php echo $current_tab === $tab_key ? 'true' : 'false'; ?>">
						<?php echo esc_html( $tab_label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<!-- Tab Content -->
			<div class="wpcalibrate-csm-content-card">
				<?php
				$template_path = WPCALIBRATE_CSM_PATH . 'templates/tab-' . $current_tab . '.php';
				if ( file_exists( $template_path ) ) {
					include $template_path;
				} else {
					echo '<p>' . esc_html__( 'Tab template not found.', 'wpcalibrate-catalog-sync-meta' ) . '</p>';
				}
				?>
			</div>
		</div>
		<?php
	}
}
