<?php
/**
 * XML Feed Tab Template.
 *
 * @package WPCalibrate\CatalogSyncMeta
 */

use WPCalibrate\CatalogSyncMeta\Admin\AdminController;
use WPCalibrate\CatalogSyncMeta\Admin\AdminMenu;
use WPCalibrate\CatalogSyncMeta\Feed\XmlFeedGenerator;
use WPCalibrate\CatalogSyncMeta\Rest\RestServer;
use WPCalibrate\CatalogSyncMeta\Settings\SettingsRegistry;

defined( 'ABSPATH' ) || exit;

$settings   = SettingsRegistry::get_all();
$action_url = admin_url( 'admin.php?page=' . AdminMenu::SUBMENU_SLUG . '&tab=feed' );
$feed_url   = XmlFeedGenerator::get_feed_url();
$rest_feed  = rest_url( RestServer::REST_NAMESPACE . '/feed?token=' . rawurlencode( (string) $settings['feed_token'] ) );
?>

<div class="wpcalibrate-csm-section">
	<div class="wpcalibrate-csm-section-header">
		<h2><?php esc_html_e( 'Meta XML Product Feed', 'wpcalibrate-catalog-sync-meta' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'An optional, high-performance XML product feed formatted according to official Meta Catalog specifications. Use as a standalone feed or fallback data source in Meta Commerce Manager.', 'wpcalibrate-catalog-sync-meta' ); ?>
		</p>
	</div>

	<!-- Feed Endpoint Details Card -->
	<div class="wpcalibrate-csm-box" style="margin-bottom: 25px;">
		<h3><?php esc_html_e( 'Feed Delivery Endpoints', 'wpcalibrate-catalog-sync-meta' ); ?></h3>

		<p><strong><?php esc_html_e( 'Standard Feed URL (Recommended for Meta Commerce Manager Data Source):', 'wpcalibrate-catalog-sync-meta' ); ?></strong></p>
		<div class="wpcalibrate-csm-copy-field" style="display: flex; gap: 8px; margin-bottom: 15px;">
			<input type="text" id="csm_feed_url" value="<?php echo esc_url( $feed_url ); ?>" class="large-text" readonly="readonly" style="background:#f9f9f9;" />
			<button type="button" class="button wpcalibrate-copy-btn" data-target="#csm_feed_url"><?php esc_html_e( 'Copy URL', 'wpcalibrate-catalog-sync-meta' ); ?></button>
		</div>

		<p><strong><?php esc_html_e( 'REST API Feed Endpoint:', 'wpcalibrate-catalog-sync-meta' ); ?></strong></p>
		<div class="wpcalibrate-csm-copy-field" style="display: flex; gap: 8px;">
			<input type="text" id="csm_rest_feed_url" value="<?php echo esc_url( $rest_feed ); ?>" class="large-text" readonly="readonly" style="background:#f9f9f9;" />
			<button type="button" class="button wpcalibrate-copy-btn" data-target="#csm_rest_feed_url"><?php esc_html_e( 'Copy URL', 'wpcalibrate-catalog-sync-meta' ); ?></button>
		</div>

		<div class="wpcalibrate-csm-meta-stats" style="margin-top: 15px; font-size: 13px; color: #555;">
			<span><?php printf( esc_html__( 'Last Generated: %s', 'wpcalibrate-catalog-sync-meta' ), $settings['feed_last_generated'] ? esc_html( wp_date( 'Y-m-d H:i:s', $settings['feed_last_generated'] ) ) : esc_html__( 'Never', 'wpcalibrate-catalog-sync-meta' ) ); ?></span>
			&bull;
			<span><?php printf( esc_html__( 'Eligible Items in Feed: %s', 'wpcalibrate-catalog-sync-meta' ), esc_html( number_format_i18n( (int) $settings['feed_item_count'] ) ) ); ?></span>
		</div>
	</div>

	<!-- Feed Settings Form -->
	<form method="post" action="<?php echo esc_url( $action_url ); ?>" class="wpcalibrate-csm-form">
		<?php wp_nonce_field( AdminController::SAVE_NONCE, 'wpcalibrate_csm_nonce' ); ?>
		<input type="hidden" name="wpcalibrate_csm_save_tab" value="feed" />

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'XML Feed Status', 'wpcalibrate-catalog-sync-meta' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="enable_feed" value="1" <?php checked( ! empty( $settings['enable_feed'] ) ); ?> />
						<?php esc_html_e( 'Enable XML Product Feed delivery', 'wpcalibrate-catalog-sync-meta' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'If disabled, all requests to the feed URL will return HTTP 403 Forbidden.', 'wpcalibrate-catalog-sync-meta' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Feed Security Token', 'wpcalibrate-catalog-sync-meta' ); ?></th>
				<td>
					<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'csm_action', 'regenerate_feed_token', $action_url ), 'csm_action_regenerate_feed_token' ) ); ?>" class="button button-secondary">
						<?php esc_html_e( 'Regenerate Feed Token', 'wpcalibrate-catalog-sync-meta' ); ?>
					</a>
					<p class="description"><?php esc_html_e( 'Regenerating the token invalidates previous feed URLs and creates a new cryptographic 32-character key.', 'wpcalibrate-catalog-sync-meta' ); ?></p>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save Feed Settings', 'wpcalibrate-catalog-sync-meta' ) ); ?>
	</form>
</div>
