<?php
/**
 * Incremental Product Change Detector.
 *
 * @package WPCalibrate\CatalogSyncMeta
 */

namespace WPCalibrate\CatalogSyncMeta\Sync;

use WPCalibrate\CatalogSyncMeta\Queue\QueueManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class ChangeDetector
 */
class ChangeDetector {

	/**
	 * Register WooCommerce and WordPress change hooks.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		// Product creation and updates.
		add_action( 'woocommerce_new_product', array( __CLASS__, 'on_product_save' ), 20, 2 );
		add_action( 'woocommerce_update_product', array( __CLASS__, 'on_product_save' ), 20, 2 );
		add_action( 'woocommerce_product_duplicate', array( __CLASS__, 'on_product_duplicate' ), 20, 2 );

		// Variations.
		add_action( 'woocommerce_update_product_variation', array( __CLASS__, 'on_variation_update' ), 20, 1 );

		// Stock status and level changes.
		add_action( 'woocommerce_product_set_stock', array( __CLASS__, 'on_stock_change' ), 20, 1 );
		add_action( 'woocommerce_variation_set_stock', array( __CLASS__, 'on_stock_change' ), 20, 1 );

		// Trash / Delete / Restore.
		add_action( 'wp_trash_post', array( __CLASS__, 'on_post_trash' ), 20, 1 );
		add_action( 'untrashed_post', array( __CLASS__, 'on_post_untrash' ), 20, 1 );
		add_action( 'before_delete_post', array( __CLASS__, 'on_post_delete' ), 20, 1 );
	}

	/**
	 * Handle product save/update.
	 *
	 * @param int         $product_id Product ID.
	 * @param mixed|null  $product    WC_Product or null.
	 * @return void
	 */
	public static function on_product_save( int $product_id, mixed $product = null ): void {
		if ( wp_is_post_revision( $product_id ) || wp_is_post_autosave( $product_id ) ) {
			return;
		}

		QueueManager::enqueue_product( $product_id );
	}

	/**
	 * Handle product duplication.
	 *
	 * @param mixed $duplicate Duplicated product object.
	 * @param mixed $product   Original product.
	 * @return void
	 */
	public static function on_product_duplicate( mixed $duplicate, mixed $product ): void {
		if ( is_object( $duplicate ) && method_exists( $duplicate, 'get_id' ) ) {
			QueueManager::enqueue_product( $duplicate->get_id() );
		}
	}

	/**
	 * Handle variation updates.
	 *
	 * @param int $variation_id Variation ID.
	 * @return void
	 */
	public static function on_variation_update( int $variation_id ): void {
		if ( $variation_id <= 0 ) {
			return;
		}

		$parent_id = wp_get_post_parent_id( $variation_id );
		if ( $parent_id > 0 ) {
			QueueManager::enqueue_product( $parent_id );
		} else {
			QueueManager::enqueue_product( $variation_id );
		}
	}

	/**
	 * Handle stock status changes.
	 *
	 * @param mixed $product WC_Product instance.
	 * @return void
	 */
	public static function on_stock_change( mixed $product ): void {
		if ( is_object( $product ) && method_exists( $product, 'get_id' ) ) {
			$product_id = $product->get_id();
			if ( 'variation' === $product->get_type() ) {
				$parent_id = $product->get_parent_id();
				QueueManager::enqueue_product( $parent_id ?: $product_id );
			} else {
				QueueManager::enqueue_product( $product_id );
			}
		}
	}

	/**
	 * Handle trashing of a product.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function on_post_trash( int $post_id ): void {
		$post_type = get_post_type( $post_id );
		if ( 'product' === $post_type || 'product_variation' === $post_type ) {
			// Trigger removal handler via QueueManager.
			QueueManager::enqueue_product( $post_id );
		}
	}

	/**
	 * Handle restoring a product from trash.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function on_post_untrash( int $post_id ): void {
		$post_type = get_post_type( $post_id );
		if ( 'product' === $post_type || 'product_variation' === $post_type ) {
			QueueManager::enqueue_product( $post_id );
		}
	}

	/**
	 * Handle permanent deletion of a product.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function on_post_delete( int $post_id ): void {
		$post_type = get_post_type( $post_id );
		if ( 'product' === $post_type || 'product_variation' === $post_type ) {
			// Record removal in sync events before postmeta is deleted.
			\WPCalibrate\CatalogSyncMeta\Data\SyncEventRepository::record_event(
				$post_id,
				0,
				'removed',
				'delete',
				null,
				__( 'Product deleted from WordPress.', 'wpcalibrate-catalog-sync-meta' )
			);
		}
	}
}
