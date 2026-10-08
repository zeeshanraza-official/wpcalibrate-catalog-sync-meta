<?php
/**
 * Product Rules Tab Template.
 *
 * @package WPCalibrate\CatalogSyncMeta
 */

use WPCalibrate\CatalogSyncMeta\Admin\AdminController;
use WPCalibrate\CatalogSyncMeta\Admin\AdminMenu;
use WPCalibrate\CatalogSyncMeta\Settings\SettingsRegistry;

defined( 'ABSPATH' ) || exit;

$settings   = SettingsRegistry::get_all();
$action_url = admin_url( 'admin.php?page=' . AdminMenu::SUBMENU_SLUG . '&tab=rules' );

// Retrieve all product categories.
$all_categories = get_terms(
	array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => false,
	)
);
$excluded_cats  = (array) ( $settings['excluded_categories'] ?? array() );
$included_cats  = (array) ( $settings['included_categories'] ?? array() );
?>

<div class="wpcalibrate-csm-section">
	<div class="wpcalibrate-csm-section-header">
		<h2><?php esc_html_e( 'Product Eligibility Rules', 'wpcalibrate-catalog-sync-meta' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Control which WooCommerce products qualify for Meta Catalog synchronization and feed generation.', 'wpcalibrate-catalog-sync-meta' ); ?>
		</p>
	</div>

	<!-- Precedence Rule Explainer -->
	<div class="notice notice-info inline" style="margin-bottom: 20px;">
		<p><strong><?php esc_html_e( 'Rule Precedence Order:', 'wpcalibrate-catalog-sync-meta' ); ?></strong></p>
		<ol style="margin-left: 20px; font-size: 13px;">
			<li><strong><?php esc_html_e( 'Explicit Product Exclusion:', 'wpcalibrate-catalog-sync-meta' ); ?></strong> <?php esc_html_e( 'Individual products marked "Exclude" on the product edit screen are always omitted.', 'wpcalibrate-catalog-sync-meta' ); ?></li>
			<li><strong><?php esc_html_e( 'Global Validity:', 'wpcalibrate-catalog-sync-meta' ); ?></strong> <?php esc_html_e( 'Only published products with valid public URLs are eligible (drafts, trash, and private products are always excluded).', 'wpcalibrate-catalog-sync-meta' ); ?></li>
			<li><strong><?php esc_html_e( 'Exclusion Rules:', 'wpcalibrate-catalog-sync-meta' ); ?></strong> <?php esc_html_e( 'Excluded categories, out-of-stock items, or hidden visibility settings take precedence.', 'wpcalibrate-catalog-sync-meta' ); ?></li>
			<li><strong><?php esc_html_e( 'Included Categories Restriction:', 'wpcalibrate-catalog-sync-meta' ); ?></strong> <?php esc_html_e( 'If specific categories are selected under "Included Categories", all other categories are excluded.', 'wpcalibrate-catalog-sync-meta' ); ?></li>
			<li><strong><?php esc_html_e( 'Eligible Catalog:', 'wpcalibrate-catalog-sync-meta' ); ?></strong> <?php esc_html_e( 'All remaining products are synchronized.', 'wpcalibrate-catalog-sync-meta' ); ?></li>
		</ol>
	</div>

	<form method="post" action="<?php echo esc_url( $action_url ); ?>" class="wpcalibrate-csm-form">
		<?php wp_nonce_field( AdminController::SAVE_NONCE, 'wpcalibrate_csm_nonce' ); ?>
		<input type="hidden" name="wpcalibrate_csm_save_tab" value="rules" />

		<h3><?php esc_html_e( 'Product Types & Visibility', 'wpcalibrate-catalog-sync-meta' ); ?></h3>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Supported Product Types', 'wpcalibrate-catalog-sync-meta' ); ?></th>
				<td>
					<fieldset>
						<label>
							<input type="checkbox" name="sync_simple_products" value="1" <?php checked( ! empty( $settings['sync_simple_products'] ) ); ?> />
							<?php esc_html_e( 'Synchronize Simple Products', 'wpcalibrate-catalog-sync-meta' ); ?>
						</label><br />
						<label>
							<input type="checkbox" name="sync_variable_products" value="1" <?php checked( ! empty( $settings['sync_variable_products'] ) ); ?> />
							<?php esc_html_e( 'Synchronize Variable Products (Product Groups)', 'wpcalibrate-catalog-sync-meta' ); ?>
						</label><br />
						<label>
							<input type="checkbox" name="sync_variations" value="1" <?php checked( ! empty( $settings['sync_variations'] ) ); ?> />
							<?php esc_html_e( 'Synchronize Individual Product Variations', 'wpcalibrate-catalog-sync-meta' ); ?>
						</label>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Stock & Visibility Filters', 'wpcalibrate-catalog-sync-meta' ); ?></th>
				<td>
					<fieldset>
						<label>
							<input type="checkbox" name="exclude_out_of_stock" value="1" <?php checked( ! empty( $settings['exclude_out_of_stock'] ) ); ?> />
							<?php esc_html_e( 'Exclude Out-of-Stock Products', 'wpcalibrate-catalog-sync-meta' ); ?>
						</label><br />
						<label>
							<input type="checkbox" name="exclude_hidden_catalog" value="1" <?php checked( ! empty( $settings['exclude_hidden_catalog'] ) ); ?> />
							<?php esc_html_e( 'Exclude Products Hidden from Catalog/Search Visibility', 'wpcalibrate-catalog-sync-meta' ); ?>
						</label><br />
						<label>
							<input type="checkbox" name="exclude_without_price" value="1" <?php checked( ! empty( $settings['exclude_without_price'] ) ); ?> />
							<?php esc_html_e( 'Exclude Products Without a Valid Price', 'wpcalibrate-catalog-sync-meta' ); ?>
						</label><br />
						<label>
							<input type="checkbox" name="exclude_without_image" value="1" <?php checked( ! empty( $settings['exclude_without_image'] ) ); ?> />
							<?php esc_html_e( 'Exclude Products Without an Image', 'wpcalibrate-catalog-sync-meta' ); ?>
						</label>
					</fieldset>
				</td>
			</tr>
		</table>

		<h3><?php esc_html_e( 'Category Inclusion & Exclusion', 'wpcalibrate-catalog-sync-meta' ); ?></h3>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="excluded_categories"><?php esc_html_e( 'Excluded Categories', 'wpcalibrate-catalog-sync-meta' ); ?></label></th>
				<td>
					<select id="excluded_categories" name="excluded_categories[]" multiple="multiple" style="min-width: 350px; min-height: 120px;">
						<?php if ( ! empty( $all_categories ) && ! is_wp_error( $all_categories ) ) : ?>
							<?php foreach ( $all_categories as $cat ) : ?>
								<option value="<?php echo esc_attr( $cat->term_id ); ?>" <?php echo in_array( $cat->term_id, $excluded_cats, true ) ? 'selected="selected"' : ''; ?>>
									<?php echo esc_html( $cat->name . ' (' . $cat->count . ')' ); ?>
								</option>
							<?php endforeach; ?>
						<?php endif; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Products in these categories will NEVER be synced (hold Ctrl/Cmd to select multiple).', 'wpcalibrate-catalog-sync-meta' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="included_categories"><?php esc_html_e( 'Included Categories (Optional Restriction)', 'wpcalibrate-catalog-sync-meta' ); ?></label></th>
				<td>
					<select id="included_categories" name="included_categories[]" multiple="multiple" style="min-width: 350px; min-height: 120px;">
						<?php if ( ! empty( $all_categories ) && ! is_wp_error( $all_categories ) ) : ?>
							<?php foreach ( $all_categories as $cat ) : ?>
								<option value="<?php echo esc_attr( $cat->term_id ); ?>" <?php echo in_array( $cat->term_id, $included_cats, true ) ? 'selected="selected"' : ''; ?>>
									<?php echo esc_html( $cat->name . ' (' . $cat->count . ')' ); ?>
								</option>
							<?php endforeach; ?>
						<?php endif; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Leave empty to include all non-excluded categories. If categories are selected here, ONLY products in these categories will sync.', 'wpcalibrate-catalog-sync-meta' ); ?></p>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save Product Rules', 'wpcalibrate-catalog-sync-meta' ) ); ?>
	</form>
</div>
