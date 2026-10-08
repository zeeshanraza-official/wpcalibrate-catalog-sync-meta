<?php
/**
 * Field Mapping Settings Tab Template.
 *
 * @package WPCalibrate\CatalogSyncMeta
 */

use WPCalibrate\CatalogSyncMeta\Admin\AdminController;
use WPCalibrate\CatalogSyncMeta\Admin\AdminMenu;
use WPCalibrate\CatalogSyncMeta\Settings\SettingsRegistry;

defined( 'ABSPATH' ) || exit;

$settings   = SettingsRegistry::get_all();
$action_url = admin_url( 'admin.php?page=' . AdminMenu::SUBMENU_SLUG . '&tab=mapping' );
?>

<div class="wpcalibrate-csm-section">
	<div class="wpcalibrate-csm-section-header">
		<h2><?php esc_html_e( 'Product & Attribute Field Mapping', 'wpcalibrate-catalog-sync-meta' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Map WooCommerce product fields, global attributes, and custom post meta to Meta Catalog product attributes.', 'wpcalibrate-catalog-sync-meta' ); ?>
		</p>
	</div>

	<div class="notice notice-warning inline" style="margin-bottom: 20px;">
		<p>
			<strong><?php esc_html_e( 'GTIN & Brand Integrity Guarantee:', 'wpcalibrate-catalog-sync-meta' ); ?></strong>
			<?php esc_html_e( 'This plugin never fabricates fake GTIN, UPC, EAN, MPN or Brand data. Fields without valid mapped values will be omitted from the catalog rather than populated with invalid placeholders.', 'wpcalibrate-catalog-sync-meta' ); ?>
		</p>
	</div>

	<form method="post" action="<?php echo esc_url( $action_url ); ?>" class="wpcalibrate-csm-form">
		<?php wp_nonce_field( AdminController::SAVE_NONCE, 'wpcalibrate_csm_nonce' ); ?>
		<input type="hidden" name="wpcalibrate_csm_save_tab" value="mapping" />

		<h3><?php esc_html_e( 'Product Identifier Strategy', 'wpcalibrate-catalog-sync-meta' ); ?></h3>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="identifier_type"><?php esc_html_e( 'Catalog Retailer ID Source', 'wpcalibrate-catalog-sync-meta' ); ?></label></th>
				<td>
					<select id="identifier_type" name="identifier_type">
						<option value="product_id" <?php selected( $settings['identifier_type'], 'product_id' ); ?>>
							<?php esc_html_e( 'WordPress Product ID (Recommended: wc_123 / wc_var_456)', 'wpcalibrate-catalog-sync-meta' ); ?>
						</option>
						<option value="sku" <?php selected( $settings['identifier_type'], 'sku' ); ?>>
							<?php esc_html_e( 'WooCommerce SKU (Falls back to Product ID if SKU is missing)', 'wpcalibrate-catalog-sync-meta' ); ?>
						</option>
					</select>
					<p class="description">
						<?php esc_html_e( 'The Product ID strategy produces deterministic, collision-free identifiers that remain completely stable across product edits and price updates.', 'wpcalibrate-catalog-sync-meta' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<h3><?php esc_html_e( 'Brand, GTIN & Manufacturer Numbers', 'wpcalibrate-catalog-sync-meta' ); ?></h3>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="brand_source"><?php esc_html_e( 'Brand Source', 'wpcalibrate-catalog-sync-meta' ); ?></label></th>
				<td>
					<select id="brand_source" name="brand_source">
						<option value="attribute" <?php selected( $settings['brand_source'], 'attribute' ); ?>><?php esc_html_e( 'WooCommerce Product Attribute', 'wpcalibrate-catalog-sync-meta' ); ?></option>
						<option value="custom_meta" <?php selected( $settings['brand_source'], 'custom_meta' ); ?>><?php esc_html_e( 'Custom Post Meta Field', 'wpcalibrate-catalog-sync-meta' ); ?></option>
						<option value="static" <?php selected( $settings['brand_source'], 'static' ); ?>><?php esc_html_e( 'Static Store Name', 'wpcalibrate-catalog-sync-meta' ); ?></option>
					</select>
					<input type="text" id="brand_field" name="brand_field" value="<?php echo esc_attr( $settings['brand_field'] ); ?>" class="regular-text" style="margin-left: 8px;" placeholder="pa_brand or _brand" />
					<p class="description"><?php esc_html_e( 'Attribute slug (e.g. pa_brand, brand) or post meta key storing the product brand name.', 'wpcalibrate-catalog-sync-meta' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="gtin_source"><?php esc_html_e( 'GTIN / UPC / EAN Source', 'wpcalibrate-catalog-sync-meta' ); ?></label></th>
				<td>
					<select id="gtin_source" name="gtin_source">
						<option value="custom_meta" <?php selected( $settings['gtin_source'], 'custom_meta' ); ?>><?php esc_html_e( 'Custom Post Meta Field', 'wpcalibrate-catalog-sync-meta' ); ?></option>
						<option value="attribute" <?php selected( $settings['gtin_source'], 'attribute' ); ?>><?php esc_html_e( 'Product Attribute', 'wpcalibrate-catalog-sync-meta' ); ?></option>
						<option value="none" <?php selected( $settings['gtin_source'], 'none' ); ?>><?php esc_html_e( 'None / Disabled', 'wpcalibrate-catalog-sync-meta' ); ?></option>
					</select>
					<input type="text" id="gtin_field" name="gtin_field" value="<?php echo esc_attr( $settings['gtin_field'] ); ?>" class="regular-text" style="margin-left: 8px;" placeholder="_gtin, _barcode, or pa_ean" />
					<p class="description"><?php esc_html_e( 'Meta key or attribute slug where standard 8, 12, 13, or 14-digit barcodes are stored.', 'wpcalibrate-catalog-sync-meta' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="mpn_source"><?php esc_html_e( 'MPN Source', 'wpcalibrate-catalog-sync-meta' ); ?></label></th>
				<td>
					<select id="mpn_source" name="mpn_source">
						<option value="sku" <?php selected( $settings['mpn_source'], 'sku' ); ?>><?php esc_html_e( 'WooCommerce SKU', 'wpcalibrate-catalog-sync-meta' ); ?></option>
						<option value="custom_meta" <?php selected( $settings['mpn_source'], 'custom_meta' ); ?>><?php esc_html_e( 'Custom Post Meta', 'wpcalibrate-catalog-sync-meta' ); ?></option>
						<option value="attribute" <?php selected( $settings['mpn_source'], 'attribute' ); ?>><?php esc_html_e( 'Product Attribute', 'wpcalibrate-catalog-sync-meta' ); ?></option>
					</select>
					<input type="text" id="mpn_field" name="mpn_field" value="<?php echo esc_attr( $settings['mpn_field'] ); ?>" class="regular-text" style="margin-left: 8px;" placeholder="_mpn" />
					<p class="description"><?php esc_html_e( 'Manufacturer Part Number mapping.', 'wpcalibrate-catalog-sync-meta' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="condition_default"><?php esc_html_e( 'Product Condition', 'wpcalibrate-catalog-sync-meta' ); ?></label></th>
				<td>
					<select id="condition_source" name="condition_source">
						<option value="static" <?php selected( $settings['condition_source'], 'static' ); ?>><?php esc_html_e( 'Static Default', 'wpcalibrate-catalog-sync-meta' ); ?></option>
						<option value="attribute" <?php selected( $settings['condition_source'], 'attribute' ); ?>><?php esc_html_e( 'Product Attribute', 'wpcalibrate-catalog-sync-meta' ); ?></option>
						<option value="custom_meta" <?php selected( $settings['condition_source'], 'custom_meta' ); ?>><?php esc_html_e( 'Custom Post Meta', 'wpcalibrate-catalog-sync-meta' ); ?></option>
					</select>
					<select id="condition_default" name="condition_default" style="margin-left: 8px;">
						<option value="new" <?php selected( $settings['condition_default'], 'new' ); ?>><?php esc_html_e( 'New', 'wpcalibrate-catalog-sync-meta' ); ?></option>
						<option value="refurbished" <?php selected( $settings['condition_default'], 'refurbished' ); ?>><?php esc_html_e( 'Refurbished', 'wpcalibrate-catalog-sync-meta' ); ?></option>
						<option value="used" <?php selected( $settings['condition_default'], 'used' ); ?>><?php esc_html_e( 'Used', 'wpcalibrate-catalog-sync-meta' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="description_source"><?php esc_html_e( 'Description Source', 'wpcalibrate-catalog-sync-meta' ); ?></label></th>
				<td>
					<select id="description_source" name="description_source">
						<option value="full_description" <?php selected( $settings['description_source'], 'full_description' ); ?>><?php esc_html_e( 'Main Description (Falls back to Short)', 'wpcalibrate-catalog-sync-meta' ); ?></option>
						<option value="short_description" <?php selected( $settings['description_source'], 'short_description' ); ?>><?php esc_html_e( 'Short Description Only', 'wpcalibrate-catalog-sync-meta' ); ?></option>
						<option value="both" <?php selected( $settings['description_source'], 'both' ); ?>><?php esc_html_e( 'Both (Short Description + Main Description)', 'wpcalibrate-catalog-sync-meta' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'HTML tags and unsafe scripts are stripped automatically.', 'wpcalibrate-catalog-sync-meta' ); ?></p>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save Field Mappings', 'wpcalibrate-catalog-sync-meta' ) ); ?>
	</form>
</div>
