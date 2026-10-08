<?php
/**
 * WooCommerce to Meta Catalog Data Mapper.
 *
 * @package WPCalibrate\CatalogSyncMeta
 */

namespace WPCalibrate\CatalogSyncMeta\Mapping;

use WC_Product;
use WC_Product_Variation;
use WPCalibrate\CatalogSyncMeta\Catalog\ProductIdentifier;
use WPCalibrate\CatalogSyncMeta\Settings\SettingsRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Class FieldMapper
 */
class FieldMapper {

	/**
	 * Map a WooCommerce product or variation to Meta Catalog representation.
	 *
	 * @param WC_Product $product WooCommerce product or variation.
	 * @return array<string, mixed> Meta Catalog item payload.
	 */
	public static function map_product( WC_Product $product ): array {
		$settings     = SettingsRegistry::get_all();
		$is_variation = $product instanceof WC_Product_Variation;
		$product_id   = $product->get_id();
		$parent_id    = $is_variation ? $product->get_parent_id() : $product_id;
		$currency     = get_woocommerce_currency();

		// 1. Identifiers.
		$retailer_id = ProductIdentifier::get_retailer_id( $product );
		$group_id    = ProductIdentifier::get_item_group_id( $product );

		// 2. Title & Description.
		$title       = self::resolve_title( $product, $is_variation );
		$description = self::resolve_description( $product, $parent_id, $settings['description_source'] ?? 'full_description' );

		// 3. Pricing.
		$prices = self::resolve_pricing( $product, $currency );

		// 4. Availability & Inventory.
		$availability = self::resolve_availability( $product );
		$inventory    = $product->managing_stock() ? (int) $product->get_stock_quantity() : null;

		// 5. Links & URLs.
		$link = $product->get_permalink();

		// 6. Images.
		$images = self::resolve_images( $product, $parent_id, $is_variation );

		// 7. Categories & Taxonomies.
		$category_path = self::resolve_category_path( $parent_id );

		// 8. Mapped Identifiers (Brand, GTIN, MPN, Condition).
		$brand     = self::resolve_brand( $product, $parent_id, $settings );
		$gtin      = self::resolve_gtin( $product, $settings );
		$mpn       = self::resolve_mpn( $product, $settings );
		$condition = self::resolve_condition( $product, $settings );

		// Assemble standard Meta Catalog payload.
		$payload = array(
			'id'           => $retailer_id,
			'title'        => $title,
			'description'  => $description,
			'availability' => $availability,
			'condition'    => $condition,
			'price'        => $prices['price'],
			'link'         => esc_url_raw( $link ),
			'image_link'   => esc_url_raw( $images['image_link'] ),
			'brand'        => $brand,
		);

		if ( ! empty( $group_id ) ) {
			$payload['item_group_id'] = $group_id;
		}

		if ( ! empty( $prices['sale_price'] ) ) {
			$payload['sale_price'] = $prices['sale_price'];
		}

		if ( ! empty( $images['additional_image_link'] ) ) {
			$payload['additional_image_link'] = $images['additional_image_link'];
		}

		if ( null !== $inventory ) {
			$payload['inventory'] = $inventory;
		}

		if ( ! empty( $category_path ) ) {
			$payload['google_product_category'] = $category_path;
			$payload['fb_product_category']     = $category_path;
		}

		if ( ! empty( $gtin ) ) {
			$payload['gtin'] = $gtin;
		}

		if ( ! empty( $mpn ) ) {
			$payload['mpn'] = $mpn;
		}

		// Append variation attributes if applicable.
		if ( $is_variation ) {
			$variation_attrs = self::resolve_variation_attributes( $product );
			$payload         = array_merge( $payload, $variation_attrs );
		}

		/**
		 * Filter mapped Meta Catalog product payload.
		 *
		 * @param array<string, mixed> $payload  Generated Meta payload.
		 * @param WC_Product           $product  WooCommerce product instance.
		 * @param bool                 $is_var   Whether this is a variation.
		 */
		return apply_filters( 'wpcalibrate_csm_product_mapped_payload', $payload, $product, $is_variation );
	}

	/**
	 * Resolve clean product title.
	 *
	 * @param WC_Product $product      Product instance.
	 * @param bool       $is_variation Whether variation.
	 * @return string Title capped at 150 characters.
	 */
	public static function resolve_title( WC_Product $product, bool $is_variation ): string {
		$title = $product->get_name();
		if ( empty( $title ) ) {
			$title = $product->get_title();
		}
		$clean_title = wp_strip_all_tags( $title );
		return mb_substr( trim( $clean_title ), 0, 150, 'UTF-8' );
	}

	/**
	 * Resolve clean product description.
	 *
	 * @param WC_Product $product     Product.
	 * @param int        $parent_id   Parent post ID.
	 * @param string     $source_type Source type preference.
	 * @return string Clean text description capped at 5000 characters.
	 */
	public static function resolve_description( WC_Product $product, int $parent_id, string $source_type ): string {
		$desc = '';

		if ( 'short_description' === $source_type ) {
			$desc = $product->get_short_description();
		} elseif ( 'both' === $source_type ) {
			$desc = $product->get_short_description() . "\n\n" . $product->get_description();
		} else {
			$desc = $product->get_description();
			if ( empty( $desc ) ) {
				$desc = $product->get_short_description();
			}
		}

		// If variation and description is empty, fallback to parent description.
		if ( empty( $desc ) && $product instanceof WC_Product_Variation ) {
			$parent = wc_get_product( $parent_id );
			if ( $parent ) {
				$desc = $parent->get_description() ?: $parent->get_short_description();
			}
		}

		// Strip shortcodes, HTML tags, normalize whitespace.
		$clean = strip_shortcodes( $desc );
		$clean = wp_strip_all_tags( $clean );
		$clean = preg_replace( '/\s+/', ' ', $clean );
		$clean = trim( (string) $clean );

		if ( empty( $clean ) ) {
			$clean = self::resolve_title( $product, false );
		}

		return mb_substr( $clean, 0, 5000, 'UTF-8' );
	}

	/**
	 * Resolve formatted regular price and sale price.
	 *
	 * @param WC_Product $product  Product instance.
	 * @param string     $currency ISO 4217 Currency code.
	 * @return array{price: string, sale_price: string|null}
	 */
	public static function resolve_pricing( WC_Product $product, string $currency ): array {
		$regular_price = $product->get_regular_price();
		$sale_price    = $product->get_sale_price();
		$active_price  = $product->get_price();

		// Handle variable products without single price.
		if ( empty( $regular_price ) && $product->is_type( 'variable' ) ) {
			$regular_price = $active_price;
		}

		if ( '' === $regular_price || null === $regular_price ) {
			$regular_price = $active_price ?: '0.00';
		}

		$formatted_price = number_format( (float) $regular_price, 2, '.', '' ) . ' ' . $currency;
		$formatted_sale  = null;

		if ( $product->is_on_sale() && ! empty( $sale_price ) && (float) $sale_price < (float) $regular_price ) {
			$formatted_sale = number_format( (float) $sale_price, 2, '.', '' ) . ' ' . $currency;
		}

		return array(
			'price'      => $formatted_price,
			'sale_price' => $formatted_sale,
		);
	}

	/**
	 * Resolve availability string according to Meta specifications.
	 * Options: 'in stock', 'out of stock', 'preorder', 'available for order'.
	 *
	 * @param WC_Product $product Product.
	 * @return string Meta availability status.
	 */
	public static function resolve_availability( WC_Product $product ): string {
		if ( $product->is_on_backorder() ) {
			return 'preorder';
		}
		if ( $product->is_in_stock() ) {
			return 'in stock';
		}
		return 'out of stock';
	}

	/**
	 * Resolve featured and gallery images.
	 *
	 * @param WC_Product $product      Product.
	 * @param int        $parent_id    Parent ID.
	 * @param bool       $is_variation Whether variation.
	 * @return array{image_link: string, additional_image_link: array<string>}
	 */
	public static function resolve_images( WC_Product $product, int $parent_id, bool $is_variation ): array {
		$image_id = $product->get_image_id();

		// Fallback to parent image for variations.
		if ( empty( $image_id ) && $is_variation ) {
			$parent = wc_get_product( $parent_id );
			if ( $parent ) {
				$image_id = $parent->get_image_id();
			}
		}

		$primary_url = $image_id ? wp_get_attachment_image_url( $image_id, 'full' ) : '';
		if ( empty( $primary_url ) ) {
			$primary_url = wc_placeholder_img_src( 'full' );
		}

		// Ensure HTTPS where applicable.
		$primary_url = set_url_scheme( $primary_url, 'https' );

		// Gallery images.
		$gallery_ids = $product->get_gallery_image_ids();
		if ( empty( $gallery_ids ) && $is_variation ) {
			$parent = wc_get_product( $parent_id );
			if ( $parent ) {
				$gallery_ids = $parent->get_gallery_image_ids();
			}
		}

		$additional = array();
		if ( ! empty( $gallery_ids ) ) {
			foreach ( array_slice( $gallery_ids, 0, 10 ) as $gid ) {
				$url = wp_get_attachment_image_url( $gid, 'full' );
				if ( ! empty( $url ) ) {
					$additional[] = set_url_scheme( $url, 'https' );
				}
			}
		}

		return array(
			'image_link'            => $primary_url,
			'additional_image_link' => $additional,
		);
	}

	/**
	 * Resolve category breadcrumb path.
	 *
	 * @param int $parent_id Parent product ID.
	 * @return string Category path, e.g. "Apparel > Men > Shirts".
	 */
	public static function resolve_category_path( int $parent_id ): string {
		$terms = wc_get_product_terms( $parent_id, 'product_cat', array( 'orderby' => 'parent', 'order' => 'ASC' ) );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return '';
		}

		$names = array();
		foreach ( $terms as $term ) {
			$names[] = $term->name;
		}

		return implode( ' > ', $names );
	}

	/**
	 * Resolve brand without fabrication.
	 *
	 * @param WC_Product           $product   Product.
	 * @param int                  $parent_id Parent ID.
	 * @param array<string, mixed> $settings  Settings.
	 * @return string Brand name or empty.
	 */
	public static function resolve_brand( WC_Product $product, int $parent_id, array $settings ): string {
		$source = $settings['brand_source'] ?? 'attribute';
		$field  = trim( (string) ( $settings['brand_field'] ?? 'pa_brand' ) );

		if ( empty( $field ) ) {
			return '';
		}

		if ( 'attribute' === $source ) {
			// Check attribute on product or parent.
			$val = $product->get_attribute( $field );
			if ( ! empty( $val ) ) {
				return sanitize_text_field( $val );
			}
		} elseif ( 'custom_meta' === $source ) {
			$val = get_post_meta( $product->get_id(), $field, true );
			if ( empty( $val ) && $product instanceof WC_Product_Variation ) {
				$val = get_post_meta( $parent_id, $field, true );
			}
			if ( ! empty( $val ) && is_scalar( $val ) ) {
				return sanitize_text_field( (string) $val );
			}
		}

		return '';
	}

	/**
	 * Resolve GTIN / UPC / EAN without fabrication.
	 *
	 * @param WC_Product           $product  Product.
	 * @param array<string, mixed> $settings Settings.
	 * @return string GTIN or empty.
	 */
	public static function resolve_gtin( WC_Product $product, array $settings ): string {
		$source = $settings['gtin_source'] ?? 'custom_meta';
		$field  = trim( (string) ( $settings['gtin_field'] ?? '_gtin' ) );

		if ( empty( $field ) || 'none' === $source ) {
			return '';
		}

		if ( 'custom_meta' === $source ) {
			$val = get_post_meta( $product->get_id(), $field, true );
			if ( ! empty( $val ) && is_scalar( $val ) ) {
				$cleaned = preg_replace( '/[^0-9]/', '', (string) $val );
				if ( in_array( strlen( $cleaned ), array( 8, 12, 13, 14 ), true ) ) {
					return $cleaned;
				}
			}
		} elseif ( 'attribute' === $source ) {
			$val = $product->get_attribute( $field );
			if ( ! empty( $val ) ) {
				$cleaned = preg_replace( '/[^0-9]/', '', $val );
				if ( in_array( strlen( $cleaned ), array( 8, 12, 13, 14 ), true ) ) {
					return $cleaned;
				}
			}
		}

		return '';
	}

	/**
	 * Resolve MPN (Manufacturer Part Number).
	 *
	 * @param WC_Product           $product  Product.
	 * @param array<string, mixed> $settings Settings.
	 * @return string MPN or empty.
	 */
	public static function resolve_mpn( WC_Product $product, array $settings ): string {
		$source = $settings['mpn_source'] ?? 'sku';

		if ( 'sku' === $source ) {
			$sku = $product->get_sku();
			return ! empty( $sku ) ? sanitize_text_field( $sku ) : '';
		}

		$field = trim( (string) ( $settings['mpn_field'] ?? '' ) );
		if ( empty( $field ) ) {
			return '';
		}

		if ( 'custom_meta' === $source ) {
			$val = get_post_meta( $product->get_id(), $field, true );
			if ( ! empty( $val ) && is_scalar( $val ) ) {
				return sanitize_text_field( (string) $val );
			}
		} elseif ( 'attribute' === $source ) {
			$val = $product->get_attribute( $field );
			if ( ! empty( $val ) ) {
				return sanitize_text_field( $val );
			}
		}

		return '';
	}

	/**
	 * Resolve product condition ('new', 'refurbished', 'used').
	 *
	 * @param WC_Product           $product  Product.
	 * @param array<string, mixed> $settings Settings.
	 * @return string Condition string.
	 */
	public static function resolve_condition( WC_Product $product, array $settings ): string {
		$source  = $settings['condition_source'] ?? 'static';
		$default = $settings['condition_default'] ?? 'new';

		if ( 'static' === $source ) {
			return $default;
		}

		$field = trim( (string) ( $settings['condition_field'] ?? '' ) );
		if ( ! empty( $field ) ) {
			if ( 'custom_meta' === $source ) {
				$val = strtolower( trim( (string) get_post_meta( $product->get_id(), $field, true ) ) );
			} else {
				$val = strtolower( trim( (string) $product->get_attribute( $field ) ) );
			}

			if ( in_array( $val, array( 'new', 'refurbished', 'used' ), true ) ) {
				return $val;
			}
		}

		return $default;
	}

	/**
	 * Extract variation attributes (size, color, pattern, etc.).
	 *
	 * @param WC_Product_Variation $variation Product variation.
	 * @return array<string, string> Variation attributes.
	 */
	private static function resolve_variation_attributes( WC_Product_Variation $variation ): array {
		$attributes = $variation->get_variation_attributes();
		$result     = array();

		foreach ( $attributes as $attr_name => $attr_value ) {
			if ( empty( $attr_value ) ) {
				continue;
			}
			$clean_name = str_replace( array( 'attribute_', 'pa_' ), '', $attr_name );
			$clean_name = sanitize_key( $clean_name );

			// Map commonly known Meta attributes if applicable.
			if ( in_array( $clean_name, array( 'color', 'colour' ), true ) ) {
				$result['color'] = sanitize_text_field( $attr_value );
			} elseif ( 'size' === $clean_name ) {
				$result['size'] = sanitize_text_field( $attr_value );
			} elseif ( 'gender' === $clean_name ) {
				$result['gender'] = sanitize_text_field( $attr_value );
			} elseif ( 'material' === $clean_name ) {
				$result['material'] = sanitize_text_field( $attr_value );
			} elseif ( 'pattern' === $clean_name ) {
				$result['pattern'] = sanitize_text_field( $attr_value );
			} else {
				$result[ 'custom_label_' . count( $result ) ] = sanitize_text_field( $attr_value );
			}
		}

		return $result;
	}
}
