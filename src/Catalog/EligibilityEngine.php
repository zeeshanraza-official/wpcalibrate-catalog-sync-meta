<?php
/**
 * Product Eligibility Engine.
 *
 * @package WPCalibrate\CatalogSyncMeta
 */

namespace WPCalibrate\CatalogSyncMeta\Catalog;

use WC_Product;
use WC_Product_Variation;
use WPCalibrate\CatalogSyncMeta\Settings\SettingsRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Class EligibilityEngine
 */
class EligibilityEngine {

	/**
	 * Meta key for manual per-product exclusion.
	 *
	 * @var string
	 */
	public const META_EXCLUDE = '_wpcalibrate_csm_exclude';

	/**
	 * Check if a product or variation is eligible for catalog synchronization.
	 *
	 * @param WC_Product           $product Product or Variation object.
	 * @param array<string>|null   $reasons Output array populated with disqualifying reasons if any.
	 * @return bool True if eligible, false otherwise.
	 */
	public static function is_eligible( WC_Product $product, ?array &$reasons = null ): bool {
		$reasons = array();
		$settings = SettingsRegistry::get_all();

		$product_id = $product->get_id();
		$is_variation = $product instanceof WC_Product_Variation;
		$parent_id = $is_variation ? $product->get_parent_id() : $product_id;

		// ---------------------------------------------------------------------
		// Precedence Rule 1: Explicit per-product manual exclusion
		// ---------------------------------------------------------------------
		if ( 'yes' === get_post_meta( $product_id, self::META_EXCLUDE, true ) ) {
			$reasons[] = __( 'Product manually excluded in product settings.', 'wpcalibrate-catalog-sync-meta' );
			return false;
		}

		if ( $is_variation && 'yes' === get_post_meta( $parent_id, self::META_EXCLUDE, true ) ) {
			$reasons[] = __( 'Parent product manually excluded in product settings.', 'wpcalibrate-catalog-sync-meta' );
			return false;
		}

		// ---------------------------------------------------------------------
		// Precedence Rule 2: Global fundamental eligibility requirements
		// ---------------------------------------------------------------------
		// Post status check: Must be published.
		$status = $product->get_status();
		if ( 'publish' !== $status ) {
			$reasons[] = sprintf(
				/* translators: %s: Current post status */
				__( 'Product status is "%s" (only published products are eligible).', 'wpcalibrate-catalog-sync-meta' ),
				$status
			);
			return false;
		}

		// If variation, verify parent product is also published.
		if ( $is_variation ) {
			$parent_status = get_post_status( $parent_id );
			if ( 'publish' !== $parent_status ) {
				$reasons[] = __( 'Parent variable product is not published.', 'wpcalibrate-catalog-sync-meta' );
				return false;
			}
		}

		// Product type check.
		$type = $product->get_type();
		if ( 'simple' === $type && empty( $settings['sync_simple_products'] ) ) {
			$reasons[] = __( 'Simple products are disabled in sync rules.', 'wpcalibrate-catalog-sync-meta' );
			return false;
		}
		if ( 'variable' === $type && empty( $settings['sync_variable_products'] ) ) {
			$reasons[] = __( 'Variable products are disabled in sync rules.', 'wpcalibrate-catalog-sync-meta' );
			return false;
		}
		if ( 'variation' === $type && empty( $settings['sync_variations'] ) ) {
			$reasons[] = __( 'Variations are disabled in sync rules.', 'wpcalibrate-catalog-sync-meta' );
			return false;
		}

		// Permalink check.
		$permalink = $product->get_permalink();
		if ( empty( $permalink ) || ! filter_var( $permalink, FILTER_VALIDATE_URL ) ) {
			$reasons[] = __( 'Product does not have a valid public URL.', 'wpcalibrate-catalog-sync-meta' );
			return false;
		}

		// Price check.
		if ( ! empty( $settings['exclude_without_price'] ) ) {
			$price = $product->get_price();
			if ( '' === $price || null === $price || ( ! $is_variation && 'variable' !== $type && ! is_numeric( $price ) ) ) {
				$reasons[] = __( 'Product has no valid price assigned.', 'wpcalibrate-catalog-sync-meta' );
				return false;
			}
		}

		// Image check.
		if ( ! empty( $settings['exclude_without_image'] ) ) {
			$image_id = $product->get_image_id();
			if ( empty( $image_id ) && $is_variation ) {
				$parent = wc_get_product( $parent_id );
				$image_id = $parent ? $parent->get_image_id() : 0;
			}
			if ( empty( $image_id ) ) {
				$reasons[] = __( 'Product has no featured image.', 'wpcalibrate-catalog-sync-meta' );
				return false;
			}
		}

		// ---------------------------------------------------------------------
		// Precedence Rule 3: Excluded categories, tags, stock & visibility rules
		// ---------------------------------------------------------------------
		// Out of stock rule.
		if ( ! empty( $settings['exclude_out_of_stock'] ) && ! $product->is_in_stock() ) {
			$reasons[] = __( 'Product is out of stock.', 'wpcalibrate-catalog-sync-meta' );
			return false;
		}

		// Hidden catalog visibility.
		if ( ! empty( $settings['exclude_hidden_catalog'] ) ) {
			$visibility = $product->get_catalog_visibility();
			if ( 'hidden' === $visibility ) {
				$reasons[] = __( 'Product catalog visibility is hidden.', 'wpcalibrate-catalog-sync-meta' );
				return false;
			}
		}

		// Category exclusions (use parent's categories for variations).
		$category_ids = wc_get_product_term_ids( $parent_id, 'product_cat' );
		$excluded_cats = (array) ( $settings['excluded_categories'] ?? array() );
		if ( ! empty( $excluded_cats ) && ! empty( $category_ids ) ) {
			$intersect = array_intersect( $category_ids, $excluded_cats );
			if ( ! empty( $intersect ) ) {
				$reasons[] = __( 'Product belongs to an excluded category.', 'wpcalibrate-catalog-sync-meta' );
				return false;
			}
		}

		// Tag exclusions.
		$tag_ids = wc_get_product_term_ids( $parent_id, 'product_tag' );
		$excluded_tags = (array) ( $settings['excluded_tags'] ?? array() );
		if ( ! empty( $excluded_tags ) && ! empty( $tag_ids ) ) {
			$intersect = array_intersect( $tag_ids, $excluded_tags );
			if ( ! empty( $intersect ) ) {
				$reasons[] = __( 'Product has an excluded product tag.', 'wpcalibrate-catalog-sync-meta' );
				return false;
			}
		}

		// ---------------------------------------------------------------------
		// Precedence Rule 4: Included category restriction (if set)
		// ---------------------------------------------------------------------
		$included_cats = (array) ( $settings['included_categories'] ?? array() );
		if ( ! empty( $included_cats ) ) {
			$intersect = array_intersect( $category_ids, $included_cats );
			if ( empty( $intersect ) ) {
				$reasons[] = __( 'Product is not in any explicitly included category.', 'wpcalibrate-catalog-sync-meta' );
				return false;
			}
		}

		// ---------------------------------------------------------------------
		// Precedence Rule 5: Extensibility filter and final approval
		// ---------------------------------------------------------------------
		$is_eligible = true;

		/**
		 * Filter product catalog sync eligibility.
		 *
		 * @param bool       $is_eligible Current eligibility status.
		 * @param WC_Product $product     The WooCommerce product object.
		 */
		$filtered_eligible = apply_filters( 'wpcalibrate_csm_product_is_eligible', $is_eligible, $product );

		if ( ! $filtered_eligible ) {
			$reasons[] = __( 'Disqualified by custom filter.', 'wpcalibrate-catalog-sync-meta' );
			return false;
		}

		return true;
	}

	/**
	 * Check eligibility by product ID.
	 *
	 * @param int                $product_id Product ID.
	 * @param array<string>|null $reasons    Output array for disqualifying reasons.
	 * @return bool
	 */
	public static function check_by_id( int $product_id, ?array &$reasons = null ): bool {
		$product = wc_get_product( $product_id );
		if ( ! $product instanceof WC_Product ) {
			$reasons = array( __( 'Product not found.', 'wpcalibrate-catalog-sync-meta' ) );
			return false;
		}
		return self::is_eligible( $product, $reasons );
	}
}
