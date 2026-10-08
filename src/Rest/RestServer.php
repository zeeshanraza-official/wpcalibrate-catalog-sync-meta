<?php
/**
 * REST API Controller.
 *
 * @package WPCalibrate\CatalogSyncMeta
 */

namespace WPCalibrate\CatalogSyncMeta\Rest;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WPCalibrate\CatalogSyncMeta\Api\ConnectionHealth;
use WPCalibrate\CatalogSyncMeta\Data\SyncEventRepository;
use WPCalibrate\CatalogSyncMeta\Feed\XmlFeedGenerator;
use WPCalibrate\CatalogSyncMeta\Queue\QueueManager;
use WPCalibrate\CatalogSyncMeta\Security\SecurityHelper;
use WPCalibrate\CatalogSyncMeta\Settings\SettingsRegistry;
use WPCalibrate\CatalogSyncMeta\Sync\ReconciliationService;

defined( 'ABSPATH' ) || exit;

/**
 * Class RestServer
 */
class RestServer {

	/**
	 * REST API Namespace.
	 *
	 * @var string
	 */
	public const REST_NAMESPACE = 'wpcalibrate-catalog-sync-meta/v1';

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		// Feed endpoint.
		register_rest_route(
			self::REST_NAMESPACE,
			'/feed',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'handle_feed' ),
				'permission_callback' => array( __CLASS__, 'check_feed_permission' ),
				'args'                => array(
					'token' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Status endpoint.
		register_rest_route(
			self::REST_NAMESPACE,
			'/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'handle_status' ),
				'permission_callback' => array( SecurityHelper::class, 'check_admin_permission' ),
			)
		);

		// Trigger sync endpoint.
		register_rest_route(
			self::REST_NAMESPACE,
			'/sync',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_sync' ),
				'permission_callback' => array( SecurityHelper::class, 'check_admin_permission' ),
				'args'                => array(
					'action' => array(
						'required'          => true,
						'type'              => 'string',
						'enum'              => array( 'sync_changed', 'full_reconcile', 'retry_failed' ),
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);
	}

	/**
	 * Verify feed token permission.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return bool|WP_Error
	 */
	public static function check_feed_permission( WP_REST_Request $request ): bool|WP_Error {
		$expected = (string) SettingsRegistry::get( 'feed_token', '' );
		$provided = (string) $request->get_param( 'token' );

		if ( empty( $expected ) || empty( $provided ) || ! hash_equals( $expected, $provided ) ) {
			return new WP_Error( 'rest_forbidden_feed', __( 'Invalid feed access token.', 'wpcalibrate-catalog-sync-meta' ), array( 'status' => 401 ) );
		}

		if ( ! SettingsRegistry::get( 'enable_feed', true ) ) {
			return new WP_Error( 'rest_feed_disabled', __( 'Product feed is disabled.', 'wpcalibrate-catalog-sync-meta' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Serve XML feed via REST.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return void
	 */
	public static function handle_feed( WP_REST_Request $request ): void {
		XmlFeedGenerator::render_feed( true );
	}

	/**
	 * Get status summary.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function handle_status( WP_REST_Request $request ): WP_REST_Response {
		$counts = SyncEventRepository::get_status_counts();
		$health = ConnectionHealth::get_status_presentation();

		$data = array(
			'connection' => array(
				'status'      => SettingsRegistry::get( 'meta_connection_status' ),
				'label'       => $health['label'],
				'catalog_id'  => SettingsRegistry::get( 'meta_catalog_id' ),
				'business_id' => SettingsRegistry::get( 'meta_business_id' ),
			),
			'sync' => array(
				'queue_size'               => QueueManager::get_queue_size(),
				'is_paused'                => (bool) SettingsRegistry::get( 'is_sync_paused' ),
				'auto_sync'                => (bool) SettingsRegistry::get( 'enable_auto_sync' ),
				'is_reconciling'           => ReconciliationService::is_running(),
				'last_reconciliation_time' => SettingsRegistry::get( 'last_reconciliation_time' ),
				'last_incremental_sync'    => SettingsRegistry::get( 'last_incremental_sync' ),
			),
			'counts' => $counts,
		);

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Trigger sync action.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_sync( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$action = $request->get_param( 'action' );

		switch ( $action ) {
			case 'full_reconcile':
				$started = ReconciliationService::start_full_reconciliation();
				if ( ! $started ) {
					return new WP_Error( 'already_running', __( 'Full reconciliation is already active.', 'wpcalibrate-catalog-sync-meta' ), array( 'status' => 409 ) );
				}
				return new WP_REST_Response( array( 'message' => __( 'Full catalog reconciliation started.', 'wpcalibrate-catalog-sync-meta' ) ), 200 );

			case 'retry_failed':
				$failed_events = SyncEventRepository::get_events( array( 'status' => 'failed', 'limit' => 100 ) );
				$product_ids   = array();
				foreach ( $failed_events['items'] as $item ) {
					$product_ids[] = (int) $item->product_id;
				}
				$queued = QueueManager::enqueue_products( array_unique( $product_ids ) );
				return new WP_REST_Response(
					array(
						'message' => sprintf(
							/* translators: %d: Number of products queued */
							__( 'Queued %d failed products for retry.', 'wpcalibrate-catalog-sync-meta' ),
							$queued
						),
						'queued'  => $queued,
					),
					200
				);

			case 'sync_changed':
			default:
				QueueManager::schedule_batch_processing_if_needed( 0 );
				return new WP_REST_Response( array( 'message' => __( 'Processing queued products.', 'wpcalibrate-catalog-sync-meta' ) ), 200 );
		}
	}
}
