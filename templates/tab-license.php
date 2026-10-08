<?php
/**
 * License Tab Template.
 *
 * @package WPCalibrate\CatalogSyncMeta
 */

use WPCalibrate\CatalogSyncMeta\Admin\AdminController;
use WPCalibrate\CatalogSyncMeta\Admin\AdminMenu;
use WPCalibrate\CatalogSyncMeta\Licensing\LicenseManager;
use WPCalibrate\CatalogSyncMeta\Settings\SettingsRegistry;

defined( 'ABSPATH' ) || exit;

$settings          = SettingsRegistry::get_all();
$action_url        = admin_url( 'admin.php?page=' . AdminMenu::SUBMENU_SLUG . '&tab=license' );
$masked_key        = LicenseManager::get_masked_key();
$is_configured     = LicenseManager::is_provider_configured();
?>

<div class="wpcalibrate-csm-section">
	<div class="wpcalibrate-csm-section-header">
		<h2><?php esc_html_e( 'Commercial License Management', 'wpcalibrate-catalog-sync-meta' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Manage your commercial license key for automatic updates and priority support from WPCalibrate.', 'wpcalibrate-catalog-sync-meta' ); ?>
		</p>
	</div>

	<!-- External Dependency Callout -->
	<div class="notice notice-info inline" style="margin-bottom: 25px;">
		<p><strong><?php esc_html_e( 'Licensing Integration Boundary:', 'wpcalibrate-catalog-sync-meta' ); ?></strong></p>
		<p>
			<?php esc_html_e( 'BLOCKED BY EXTERNAL CONFIGURATION: WPCalibrate commercial licensing server endpoints and validation credentials have not yet been supplied. The licensing client architecture is implemented to seamlessly connect once the production licensing server details are provided.', 'wpcalibrate-catalog-sync-meta' ); ?>
		</p>
		<p>
			<em><?php esc_html_e( 'No fake verification or mock activation is performed.', 'wpcalibrate-catalog-sync-meta' ); ?></em>
		</p>
	</div>

	<!-- License Status Panel -->
	<div class="wpcalibrate-csm-box" style="margin-bottom: 25px;">
		<div style="display: flex; justify-content: space-between; align-items: center;">
			<div>
				<strong><?php esc_html_e( 'License Status:', 'wpcalibrate-catalog-sync-meta' ); ?></strong>
				<span class="wpcalibrate-csm-badge notice-warning" style="margin-left: 8px;">
					<?php esc_html_e( 'Licensing integration not configured', 'wpcalibrate-catalog-sync-meta' ); ?>
				</span>
			</div>
			<?php if ( ! empty( $masked_key ) ) : ?>
				<div>
					<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'csm_action', 'deactivate_license', $action_url ), 'csm_action_deactivate_license' ) ); ?>" class="button button-secondary">
						<?php esc_html_e( 'Deactivate License', 'wpcalibrate-catalog-sync-meta' ); ?>
					</a>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<!-- License Form -->
	<form method="post" action="<?php echo esc_url( $action_url ); ?>" class="wpcalibrate-csm-form">
		<?php wp_nonce_field( AdminController::SAVE_NONCE, 'wpcalibrate_csm_nonce' ); ?>
		<input type="hidden" name="wpcalibrate_csm_save_tab" value="license" />

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="license_key"><?php esc_html_e( 'License Key', 'wpcalibrate-catalog-sync-meta' ); ?></label></th>
				<td>
					<input type="text" id="license_key" name="license_key" value="<?php echo esc_attr( $settings['license_key'] ); ?>" class="large-text" placeholder="WPC-XXXX-XXXX-XXXX" />
					<p class="description">
						<?php
						if ( ! empty( $masked_key ) ) {
							printf( esc_html__( 'Active saved key: %s', 'wpcalibrate-catalog-sync-meta' ), esc_html( $masked_key ) );
						} else {
							esc_html_e( 'Enter your license key provided in your WPCalibrate purchase confirmation.', 'wpcalibrate-catalog-sync-meta' );
						}
						?>
					</p>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Activate License', 'wpcalibrate-catalog-sync-meta' ), 'primary' ); ?>
	</form>
</div>
