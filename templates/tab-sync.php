<?php
/**
 * Sync Settings Tab Template.
 *
 * @package WPCalibrate\CatalogSyncMeta
 */

use WPCalibrate\CatalogSyncMeta\Admin\AdminController;
use WPCalibrate\CatalogSyncMeta\Admin\AdminMenu;
use WPCalibrate\CatalogSyncMeta\Settings\SettingsRegistry;

defined( 'ABSPATH' ) || exit;

$settings   = SettingsRegistry::get_all();
$action_url = admin_url( 'admin.php?page=' . AdminMenu::SUBMENU_SLUG . '&tab=sync' );
?>

<div class="wpcalibrate-csm-section">
	<div class="wpcalibrate-csm-section-header">
		<h2><?php esc_html_e( 'Synchronization & Throttling Controls', 'wpcalibrate-catalog-sync-meta' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Configure background batch sizes, server throttling, retry limits, and scheduled full catalog reconciliation.', 'wpcalibrate-catalog-sync-meta' ); ?>
		</p>
	</div>

	<form method="post" action="<?php echo esc_url( $action_url ); ?>" class="wpcalibrate-csm-form">
		<?php wp_nonce_field( AdminController::SAVE_NONCE, 'wpcalibrate_csm_nonce' ); ?>
		<input type="hidden" name="wpcalibrate_csm_save_tab" value="sync" />

		<h3><?php esc_html_e( 'Background Processing & Throttling', 'wpcalibrate-catalog-sync-meta' ); ?></h3>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Automatic Synchronization', 'wpcalibrate-catalog-sync-meta' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="enable_auto_sync" value="1" <?php checked( ! empty( $settings['enable_auto_sync'] ) ); ?> />
						<?php esc_html_e( 'Enable automatic background sync on product changes and stock updates', 'wpcalibrate-catalog-sync-meta' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'When enabled, WooCommerce hooks queue changed products into Action Scheduler without blocking store operations.', 'wpcalibrate-catalog-sync-meta' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="batch_size"><?php esc_html_e( 'Batch Size', 'wpcalibrate-catalog-sync-meta' ); ?></label></th>
				<td>
					<input type="number" id="batch_size" name="batch_size" value="<?php echo esc_attr( $settings['batch_size'] ); ?>" min="5" max="250" step="5" class="small-text" />
					<span class="description"><?php esc_html_e( 'Products per background request batch (default: 50, bounds: 5 - 250).', 'wpcalibrate-catalog-sync-meta' ); ?></span>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="throttle_delay_ms"><?php esc_html_e( 'Throttling Delay', 'wpcalibrate-catalog-sync-meta' ); ?></label></th>
				<td>
					<input type="number" id="throttle_delay_ms" name="throttle_delay_ms" value="<?php echo esc_attr( $settings['throttle_delay_ms'] ); ?>" min="0" max="5000" step="50" class="small-text" />
					<span class="description"><?php esc_html_e( 'Delay between consecutive batches in milliseconds (default: 100ms) to respect server and API load.', 'wpcalibrate-catalog-sync-meta' ); ?></span>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="max_retries"><?php esc_html_e( 'Maximum Retries', 'wpcalibrate-catalog-sync-meta' ); ?></label></th>
				<td>
					<input type="number" id="max_retries" name="max_retries" value="<?php echo esc_attr( $settings['max_retries'] ); ?>" min="1" max="10" step="1" class="small-text" />
					<span class="description"><?php esc_html_e( 'Max retry attempts for transient network or rate-limit failures (default: 3).', 'wpcalibrate-catalog-sync-meta' ); ?></span>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="reconciliation_interval"><?php esc_html_e( 'Full Catalog Reconciliation', 'wpcalibrate-catalog-sync-meta' ); ?></label></th>
				<td>
					<select id="reconciliation_interval" name="reconciliation_interval">
						<option value="hourly" <?php selected( $settings['reconciliation_interval'], 'hourly' ); ?>><?php esc_html_e( 'Hourly', 'wpcalibrate-catalog-sync-meta' ); ?></option>
						<option value="twicedaily" <?php selected( $settings['reconciliation_interval'], 'twicedaily' ); ?>><?php esc_html_e( 'Twice Daily', 'wpcalibrate-catalog-sync-meta' ); ?></option>
						<option value="daily" <?php selected( $settings['reconciliation_interval'], 'daily' ); ?>><?php esc_html_e( 'Daily (Recommended)', 'wpcalibrate-catalog-sync-meta' ); ?></option>
						<option value="weekly" <?php selected( $settings['reconciliation_interval'], 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'wpcalibrate-catalog-sync-meta' ); ?></option>
					</select>
					<p class="description">
						<?php esc_html_e( 'Scheduled full audit that audits all WooCommerce catalog items, catches missed hooks, and reconciles state.', 'wpcalibrate-catalog-sync-meta' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save Sync Settings', 'wpcalibrate-catalog-sync-meta' ) ); ?>
	</form>
</div>
