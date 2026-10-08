<?php
/**
 * Direct Catalog Synchronization Service.
 *
 * @package WPCalibrate\CatalogSyncMeta
 */

namespace WPCalibrate\CatalogSyncMeta\Sync;

use WC_Product;
use WC_Product_Variation;
use WP_Error;
use WPCalibrate\CatalogSyncMeta\Api\MetaApiClient;
use WPCalibrate\CatalogSyncMeta\Catalog\EligibilityEngine;
use WPCalibrate\CatalogSyncMeta\Catalog\ProductIdentifier;
use WPCalibrate\CatalogSyncMeta\Data\SyncEventRepository;
use WPCalibrate\CatalogSyncMeta\Logging\Logger;
use WPCalibrate\CatalogSyncMeta\Mapping\FieldMapper;
use WPCalibrate\CatalogSyncMeta\Settings\SettingsRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Class DirectSyncService
 */
class DirectSyncService {

	/**
	 * Meta per-product postmeta keys.
	 */
	public const META_SYNC_STATUS = '_wpcalibrate_csm_sync_status';
	public const META_CATALOG_ID  = '_wpcalibrate_csm_catalog_id';
	public const META_LAST_SYNC   = '_wpcalibrate_csm_last_sync_time';
	public const META_LAST_ERROR  = '_wpcalibrate_csm_last_error';

	/**
	 * Synchronize a list of product IDs to Meta.
	 *
	 * @param array<int> $product_ids Product IDs to process.
	 * @return array{success: int, failed: int, excluded: int, errors: array<string>}
	 */
	public static function sync_products( array $product_ids ): array {
		$catalog_id = (string) SettingsRegistry::get( 'meta_catalog_id', '' );
		$summary    = array(
			'success'  => 0,
			'failed'   => 0,
			'excluded' => 0,
			'errors'   => array(),
		);

		if ( empty( $catalog_id ) ) {
			$error_msg = __( 'Cannot synchronize: Meta Catalog ID is not configured.', 'wpcalibrate-catalog-sync-meta' );
			Logger::warning( $error_msg );
			$summary['errors'][] = $error_msg;
			return $summary;
		}

		$batch_requests = array();
		$tracked_items  = array(); // Map retailer_id => ['product_id', 'variation_id']

		foreach ( $product_ids as $pid ) {
			$product = wc_get_product( $pid );

			// If product does not exist or is trashed, send remote DELETE.
			if ( ! $product instanceof WC_Product || 'trash' === get_post_status( $pid ) ) {
				$retailer_id = ProductIdentifier::PRODUCT_PREFIX . $pid;
				$batch_requests[] = array(
					'method'      => 'DELETE',
					'retailer_id' => $retailer_id,
				);
				$tracked_items[ $retailer_id ] = array(
					'product_id'   => $pid,
					'variation_id' => 0,
					'is_delete'    => true,
				);
				continue;
			}

			// Check eligibility.
			$reasons = array();
			if ( ! EligibilityEngine::is_eligible( $product, $reasons ) ) {
				$summary['excluded']++;
				update_post_meta( $pid, self::META_SYNC_STATUS, 'excluded' );
				update_post_meta( $pid, self::META_LAST_ERROR, implode( '; ', $reasons ) );

				SyncEventRepository::record_event(
					$pid,
					0,
					'skipped',
					'sync',
					'ineligible',
					implode( '; ', $reasons )
				);

				// If it was previously synced, remove it from the catalog.
				$prev_status = get_post_meta( $pid, self::META_SYNC_STATUS, true );
				if ( 'synced' === $prev_status ) {
					$retailer_id = ProductIdentifier::get_retailer_id( $product );
					$batch_requests[] = array(
						'method'      => 'DELETE',
						'retailer_id' => $retailer_id,
					);
					$tracked_items[ $retailer_id ] = array(
						'product_id'   => $pid,
						'variation_id' => 0,
						'is_delete'    => true,
					);
				}
				continue;
			}

			// Handle Variable Products and Variations.
			if ( $product->is_type( 'variable' ) ) {
				$children = $product->get_children();
				if ( ! empty( $children ) ) {
					foreach ( $children as $child_id ) {
						$variation = wc_get_product( $child_id );
						if ( ! $variation instanceof WC_Product_Variation ) {
							continue;
						}

						$var_reasons = array();
						if ( ! EligibilityEngine::is_eligible( $variation, $var_reasons ) ) {
							update_post_meta( $child_id, self::META_SYNC_STATUS, 'excluded' );
							continue;
						}

						$mapped_var   = FieldMapper::map_product( $variation );
						$retailer_var = $mapped_var['id'];

						$batch_requests[] = array(
							'method'      => 'UPDATE',
							'retailer_id' => $retailer_var,
							'data'        => $mapped_var,
						);
						$tracked_items[ $retailer_var ] = array(
							'product_id'   => $pid,
							'variation_id' => $child_id,
							'is_delete'    => false,
						);
					}
				}
			} else {
				// Simple product or other supported single product.
				$mapped      = FieldMapper::map_product( $product );
				$retailer_id = $mapped['id'];

				$batch_requests[] = array(
					'method'      => 'UPDATE',
					'retailer_id' => $retailer_id,
					'data'        => $mapped,
				);
				$tracked_items[ $retailer_id ] = array(
					'product_id'   => $pid,
					'variation_id' => 0,
					'is_delete'    => false,
				);
			}
		}

		if ( empty( $batch_requests ) ) {
			return $summary;
		}

		// Send batch request to Meta.
		$response = MetaApiClient::send_items_batch( $catalog_id, $batch_requests );

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			$error_code    = $response->get_error_code();
			$summary['errors'][] = $error_message;

			// Mark all items in this batch as failed.
			foreach ( $tracked_items as $item_meta ) {
				$summary['failed']++;
				$target_id = $item_meta['variation_id'] ?: $item_meta['product_id'];

				update_post_meta( $target_id, self::META_SYNC_STATUS, 'failed' );
				update_post_meta( $target_id, self::META_LAST_ERROR, $error_message );

				SyncEventRepository::record_event(
					$item_meta['product_id'],
					$item_meta['variation_id'],
					'failed',
					! empty( $item_meta['is_delete'] ) ? 'delete' : 'sync',
					$error_code,
					$error_message
				);
			}

			return $summary;
		}

		// Batch submitted successfully.
		$now = time();
		foreach ( $tracked_items as $retailer_id => $item_meta ) {
			$summary['success']++;
			$target_id = $item_meta['variation_id'] ?: $item_meta['product_id'];

			if ( ! empty( $item_meta['is_delete'] ) ) {
				update_post_meta( $target_id, self::META_SYNC_STATUS, 'removed' );
				SyncEventRepository::record_event(
					$item_meta['product_id'],
					$item_meta['variation_id'],
					'synced',
					'delete',
					null,
					__( 'Item removed from Meta catalog.', 'wpcalibrate-catalog-sync-meta' )
				);
			} else {
				update_post_meta( $target_id, self::META_SYNC_STATUS, 'synced' );
				update_post_meta( $target_id, self::META_CATALOG_ID, $catalog_id );
				update_post_meta( $target_id, self::META_LAST_SYNC, $now );
				update_post_meta( $target_id, self::META_LAST_ERROR, '' );

				SyncEventRepository::record_event(
					$item_meta['product_id'],
					$item_meta['variation_id'],
					'synced',
					'sync',
					null,
					__( 'Product synced to Meta catalog successfully.', 'wpcalibrate-catalog-sync-meta' )
				);
			}
		}

		SettingsRegistry::set( 'last_incremental_sync', $now );

		return $summary;
	}

	/**
	 * Remove a product from the remote Meta catalog.
	 *
	 * @param int $product_id Product ID.
	 * @return bool|WP_Error
	 */
	public static function delete_product_from_catalog( int $product_id ): bool|WP_Error {
		$catalog_id = (string) SettingsRegistry::get( 'meta_catalog_id', '' );
		if ( empty( $catalog_id ) ) {
			return new WP_Error( 'missing_catalog', __( 'Meta Catalog ID not configured.', 'wpcalibrate-catalog-sync-meta' ) );
		}

		$retailer_id = ProductIdentifier::PRODUCT_PREFIX . $product_id;
		$requests    = array(
			array(
				'method'      => 'DELETE',
				'retailer_id' => $retailer_id,
			),
		);

		$res = MetaApiClient::send_items_batch( $catalog_id, $requests );
		if ( is_wp_error( $res ) ) {
			return $res;
		}

		update_post_meta( $product_id, self::META_SYNC_STATUS, 'removed' );
		SyncEventRepository::record_event( $product_id, 0, 'synced', 'delete', null, __( 'Product removed from Meta catalog.', 'wpcalibrate-catalog-sync-meta' ) );

		return true;
	}
}
