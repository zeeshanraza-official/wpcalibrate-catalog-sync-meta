<?php
/**
 * Product Identifier Strategy.
 *
 * @package WPCalibrate\CatalogSyncMeta
 */

namespace WPCalibrate\CatalogSyncMeta\Catalog;

use WC_Product;
use WC_Product_Variation;
use WPCalibrate\CatalogSyncMeta\Settings\SettingsRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Class ProductIdentifier
 */
class ProductIdentifier {

	/**
	 * Prefix used for WooCommerce simple/parent product catalog IDs.
	 *
	 * @var string
	 */
	public const PRODUCT_PREFIX = 'wc_';

	/**
	 * Prefix used for WooCommerce variation catalog IDs.
	 *
	 * @var string
	 */
	public const VARIATION_PREFIX = 'wc_var_';

	/**
	 * Generate a deterministic, stable, collision-free retailer ID for a product or variation.
	 *
	 * @param WC_Product $product WooCommerce product or variation.
	 * @return string Meta Catalog Retailer ID.
	 */
	public static function get_retailer_id( WC_Product $product ): string {
		$mode = SettingsRegistry::get( 'identifier_type', 'product_id' );

		// If SKU mode is explicitly configured and product has a non-empty SKU.
		if ( 'sku' === $mode ) {
			$sku = trim( (string) $product->get_sku() );
			if ( ! empty( $sku ) ) {
				/**
				 * Filter retailer ID generated in SKU mode.
				 *
				 * @param string     $sku     Product SKU.
				 * @param WC_Product $product Product instance.
				 */
				return apply_filters( 'wpcalibrate_csm_retailer_id_sku', sanitize_text_field( $sku ), $product );
			}
		}

		// Default and deterministic ID strategy based on immutable database ID.
		if ( $product instanceof WC_Product_Variation ) {
			$id = self::VARIATION_PREFIX . $product->get_id();
		} else {
			$id = self::PRODUCT_PREFIX . $product->get_id();
		}

		/**
		 * Filter the generated retailer ID.
		 *
		 * @param string     $id      Generated ID.
		 * @param WC_Product $product Product instance.
		 */
		return apply_filters( 'wpcalibrate_csm_retailer_id', $id, $product );
	}

	/**
	 * Get the item group ID (parent ID) for variable products and their variations.
	 *
	 * In Meta Product Catalogs, all variations belonging to the same product group
	 * must share the same `item_group_id`.
	 *
	 * @param WC_Product $product Product or variation.
	 * @return string|null Group ID or null if not part of a variation group.
	 */
	public static function get_item_group_id( WC_Product $product ): ?string {
		if ( $product instanceof WC_Product_Variation ) {
			$parent_id = $product->get_parent_id();
			$mode      = SettingsRegistry::get( 'identifier_type', 'product_id' );

			if ( 'sku' === $mode ) {
				$parent = wc_get_product( $parent_id );
				if ( $parent && ! empty( $parent->get_sku() ) ) {
					return sanitize_text_field( trim( $parent->get_sku() ) );
				}
			}

			return self::PRODUCT_PREFIX . $parent_id;
		}

		if ( 'variable' === $product->get_type() ) {
			$mode = SettingsRegistry::get( 'identifier_type', 'product_id' );
			if ( 'sku' === $mode && ! empty( $product->get_sku() ) ) {
				return sanitize_text_field( trim( $product->get_sku() ) );
			}
			return self::PRODUCT_PREFIX . $product->get_id();
		}

		return null;
	}

	/**
	 * Parse retailer ID back to WordPress Post/Product ID if possible.
	 *
	 * @param string $retailer_id Meta retailer ID.
	 * @return int|null WordPress ID or null if not determinable.
	 */
	public static function parse_product_id( string $retailer_id ): ?int {
		if ( str_starts_with( $retailer_id, self::VARIATION_PREFIX ) ) {
			$id = (int) substr( $retailer_id, strlen( self::VARIATION_PREFIX ) );
			return $id > 0 ? $id : null;
		}

		if ( str_starts_with( $retailer_id, self::PRODUCT_PREFIX ) ) {
			$id = (int) substr( $retailer_id, strlen( self::PRODUCT_PREFIX ) );
			return $id > 0 ? $id : null;
		}

		return null;
	}
}
