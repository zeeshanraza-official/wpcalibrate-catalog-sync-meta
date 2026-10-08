<?php
/**
 * Meta Connection Settings Tab.
 *
 * @package WPCalibrate\CatalogSyncMeta
 */

use WPCalibrate\CatalogSyncMeta\Admin\AdminController;
use WPCalibrate\CatalogSyncMeta\Admin\AdminMenu;
use WPCalibrate\CatalogSyncMeta\Api\ConnectionHealth;
use WPCalibrate\CatalogSyncMeta\Integrations\MetaOAuthManager;
use WPCalibrate\CatalogSyncMeta\Security\SecurityHelper;
use WPCalibrate\CatalogSyncMeta\Settings\SettingsRegistry;

defined( 'ABSPATH' ) || exit;

$settings         = SettingsRegistry::get_all();
$oauth_configured = MetaOAuthManager::is_oauth_configured();
$block_reason     = MetaOAuthManager::get_oauth_block_reason();
$health           = ConnectionHealth::get_status_presentation();
$action_url       = admin_url( 'admin.php?page=' . AdminMenu::SUBMENU_SLUG . '&tab=connection' );
?>

<div class="wpcalibrate-csm-section">
	<div class="wpcalibrate-csm-section-header">
		<h2><?php esc_html_e( 'Meta Account & Catalog Connection', 'wpcalibrate-catalog-sync-meta' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Connect your WooCommerce store to your Meta Business Manager and Meta Product Catalog using official Graph APIs.', 'wpcalibrate-catalog-sync-meta' ); ?>
		</p>
	</div>

	<!-- External Configuration Status Banner -->
	<?php if ( ! $oauth_configured ) : ?>
		<div class="notice notice-info inline wpcalibrate-csm-notice-box">
			<p><strong><?php esc_html_e( 'Meta OAuth Integration Boundary:', 'wpcalibrate-catalog-sync-meta' ); ?></strong></p>
			<p><?php echo esc_html( $block_reason ); ?></p>
			<p>
				<?php esc_html_e( 'You may either input production Meta App credentials below when received, or directly provide a Meta Business Manager System User Access Token and Catalog ID.', 'wpcalibrate-catalog-sync-meta' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<!-- Active Connection Status -->
	<div class="wpcalibrate-csm-connection-status-panel">
		<div class="status-indicator-row">
			<strong><?php esc_html_e( 'Current Connection State:', 'wpcalibrate-catalog-sync-meta' ); ?></strong>
			<span class="wpcalibrate-csm-badge <?php echo esc_attr( $health['badge_class'] ); ?>">
				<?php echo esc_html( $health['label'] ); ?>
			</span>
			<?php if ( $settings['meta_last_connection_check'] ) : ?>
				<span class="text-muted" style="margin-left: 10px; font-size: 12px;">
					<?php printf( esc_html__( '(Verified %s)', 'wpcalibrate-catalog-sync-meta' ), esc_html( human_time_diff( $settings['meta_last_connection_check'] ) . ' ' . __( 'ago', 'wpcalibrate-catalog-sync-meta' ) ) ); ?>
				</span>
			<?php endif; ?>
		</div>

		<div class="connection-actions-row" style="margin-top: 15px;">
			<?php if ( $oauth_configured ) : ?>
				<?php
				$auth_url = MetaOAuthManager::get_authorization_url();
				if ( ! is_wp_error( $auth_url ) ) :
					?>
					<a href="<?php echo esc_url( $auth_url ); ?>" class="button button-primary">
						<?php esc_html_e( 'Connect Meta Account (OAuth)', 'wpcalibrate-catalog-sync-meta' ); ?>
					</a>
				<?php endif; ?>
			<?php endif; ?>

			<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'csm_action', 'test_connection', $action_url ), 'csm_action_test_connection' ) ); ?>" class="button button-secondary">
				<?php esc_html_e( 'Test Connection', 'wpcalibrate-catalog-sync-meta' ); ?>
			</a>

			<?php if ( ! empty( $settings['meta_access_token'] ) ) : ?>
				<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'csm_action', 'disconnect_meta', $action_url ), 'csm_action_disconnect_meta' ) ); ?>" class="button button-link-delete wpcalibrate-confirm-disconnect" style="margin-left: 12px;">
					<?php esc_html_e( 'Disconnect Meta', 'wpcalibrate-catalog-sync-meta' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>

	<!-- Credentials Form -->
	<form method="post" action="<?php echo esc_url( $action_url ); ?>" class="wpcalibrate-csm-form">
		<?php wp_nonce_field( AdminController::SAVE_NONCE, 'wpcalibrate_csm_nonce' ); ?>
		<input type="hidden" name="wpcalibrate_csm_save_tab" value="connection" />

		<h3><?php esc_html_e( 'Meta Application & Access Credentials', 'wpcalibrate-catalog-sync-meta' ); ?></h3>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="meta_app_id"><?php esc_html_e( 'Meta App ID', 'wpcalibrate-catalog-sync-meta' ); ?></label></th>
				<td>
					<input type="text" id="meta_app_id" name="meta_app_id" value="<?php echo esc_attr( $settings['meta_app_id'] ); ?>" class="regular-text" autocomplete="off" />
					<p class="description"><?php esc_html_e( 'Supplied by WPCalibrate or your Meta for Developers App.', 'wpcalibrate-catalog-sync-meta' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="meta_app_secret"><?php esc_html_e( 'Meta App Secret', 'wpcalibrate-catalog-sync-meta' ); ?></label></th>
				<td>
					<input type="password" id="meta_app_secret" name="meta_app_secret" value="<?php echo esc_attr( $settings['meta_app_secret'] ); ?>" class="regular-text" autocomplete="new-password" />
					<p class="description">
						<?php
						if ( ! empty( $settings['meta_app_secret'] ) ) {
							printf( esc_html__( 'Saved secret: %s', 'wpcalibrate-catalog-sync-meta' ), esc_html( SecurityHelper::mask_secret( $settings['meta_app_secret'] ) ) );
						} else {
							esc_html_e( 'Required for automatic OAuth token exchanges.', 'wpcalibrate-catalog-sync-meta' );
						}
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="meta_access_token"><?php esc_html_e( 'Meta Access Token', 'wpcalibrate-catalog-sync-meta' ); ?></label></th>
				<td>
					<input type="password" id="meta_access_token" name="meta_access_token" value="<?php echo esc_attr( $settings['meta_access_token'] ); ?>" class="large-text" autocomplete="new-password" />
					<p class="description">
						<?php
						if ( ! empty( $settings['meta_access_token'] ) ) {
							printf( esc_html__( 'Active token: %s', 'wpcalibrate-catalog-sync-meta' ), esc_html( SecurityHelper::mask_secret( $settings['meta_access_token'] ) ) );
						} else {
							esc_html_e( 'Enter a System User token generated with catalog_management permissions, or obtain one via OAuth.', 'wpcalibrate-catalog-sync-meta' );
						}
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="meta_business_id"><?php esc_html_e( 'Meta Business Manager ID', 'wpcalibrate-catalog-sync-meta' ); ?></label></th>
				<td>
					<input type="text" id="meta_business_id" name="meta_business_id" value="<?php echo esc_attr( $settings['meta_business_id'] ); ?>" class="regular-text" />
					<p class="description"><?php esc_html_e( 'Your Meta Business ID (e.g. 123456789012345).', 'wpcalibrate-catalog-sync-meta' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="meta_catalog_id"><?php esc_html_e( 'Meta Product Catalog ID', 'wpcalibrate-catalog-sync-meta' ); ?></label></th>
				<td>
					<input type="text" id="meta_catalog_id" name="meta_catalog_id" value="<?php echo esc_attr( $settings['meta_catalog_id'] ); ?>" class="regular-text" required />
					<p class="description"><?php esc_html_e( 'The ID of the Commerce Manager Catalog where products will be synced.', 'wpcalibrate-catalog-sync-meta' ); ?></p>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save Connection Settings', 'wpcalibrate-catalog-sync-meta' ) ); ?>
	</form>
</div>
