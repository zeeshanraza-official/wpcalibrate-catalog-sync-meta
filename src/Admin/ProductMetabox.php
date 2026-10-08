<?php
/**
 * WooCommerce Product Edit Screen Metabox for Meta Catalog Sync.
 *
 * @package WPCalibrate\CatalogSyncMeta
 */

namespace WPCalibrate\CatalogSyncMeta\Admin;

use WC_Product;
use WC_Product_Variation;
use WPCalibrate\CatalogSyncMeta\Catalog\EligibilityEngine;
use WPCalibrate\CatalogSyncMeta\Catalog\ProductIdentifier;
use WPCalibrate\CatalogSyncMeta\Queue\QueueManager;
use WPCalibrate\CatalogSyncMeta\Security\SecurityHelper;
use WPCalibrate\CatalogSyncMeta\Sync\DirectSyncService;

defined( 'ABSPATH' ) || exit;

/**
 * Class ProductMetabox
 */
class ProductMetabox {

	/**
	 * Nonce action name.
	 */
	public const NONCE_ACTION = 'wpcalibrate_csm_product_meta_save';

	/**
	 * Nonce field name.
	 */
	public const NONCE_NAME = 'wpcalibrate_csm_product_meta_nonce';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post_product', array( __CLASS__, 'save_meta_box' ), 10, 2 );
		add_action( 'admin_post_wpcalibrate_csm_retry_single_product', array( __CLASS__, 'handle_manual_retry' ) );
	}

	/**
	 * Add Meta Catalog metabox to product edit screen.
	 *
	 * @return void
	 */
	public static function add_meta_box(): void {
		add_meta_box(
			'wpcalibrate_csm_product_box',
			__( 'Meta Catalog Sync (WPCalibrate)', 'wpcalibrate-catalog-sync-meta' ),
			array( __CLASS__, 'render_meta_box' ),
			'product',
			'side',
			'default'
		);
	}

	/**
	 * Render metabox contents.
	 *
	 * @param \WP_Post $post Current post object.
	 * @return void
	 */
	public static function render_meta_box( \WP_Post $post ): void {
		$product = wc_get_product( $post->ID );
		if ( ! $product instanceof WC_Product ) {
			echo '<p>' . esc_html__( 'Product data unavailable.', 'wpcalibrate-catalog-sync-meta' ) . '</p>';
			return;
		}

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$status       = get_post_meta( $post->ID, DirectSyncService::META_SYNC_STATUS, true ) ?: 'not_synced';
		$last_sync    = (int) get_post_meta( $post->ID, DirectSyncService::META_LAST_SYNC, true );
		$last_error   = get_post_meta( $post->ID, DirectSyncService::META_LAST_ERROR, true );
		$is_excluded  = ( 'yes' === get_post_meta( $post->ID, EligibilityEngine::META_EXCLUDE, true ) );
		$retailer_id  = ProductIdentifier::get_retailer_id( $product );
		$reasons      = array();
		$is_eligible  = EligibilityEngine::is_eligible( $product, $reasons );

		$status_labels = array(
			'not_synced' => array( 'label' => __( 'Not Synced', 'wpcalibrate-catalog-sync-meta' ), 'class' => 'status-neutral' ),
			'pending'    => array( 'label' => __( 'Pending Sync', 'wpcalibrate-catalog-sync-meta' ), 'class' => 'status-warning' ),
			'synced'     => array( 'label' => __( 'Synced', 'wpcalibrate-catalog-sync-meta' ), 'class' => 'status-success' ),
			'failed'     => array( 'label' => __( 'Failed', 'wpcalibrate-catalog-sync-meta' ), 'class' => 'status-danger' ),
			'excluded'   => array( 'label' => __( 'Excluded', 'wpcalibrate-catalog-sync-meta' ), 'class' => 'status-neutral' ),
			'removed'    => array( 'label' => __( 'Removed', 'wpcalibrate-catalog-sync-meta' ), 'class' => 'status-neutral' ),
		);

		$current_badge = $status_labels[ $status ] ?? $status_labels['not_synced'];
		?>
		<div class="wpcalibrate-csm-product-panel">
			<div class="wpcalibrate-csm-row">
				<strong><?php esc_html_e( 'Sync Status:', 'wpcalibrate-catalog-sync-meta' ); ?></strong>
				<span class="wpcalibrate-csm-badge <?php echo esc_attr( $current_badge['class'] ); ?>">
					<?php echo esc_html( $current_badge['label'] ); ?>
				</span>
			</div>

			<div class="wpcalibrate-csm-row">
				<strong><?php esc_html_e( 'Catalog Retailer ID:', 'wpcalibrate-catalog-sync-meta' ); ?></strong>
				<code><?php echo esc_html( $retailer_id ); ?></code>
			</div>

			<div class="wpcalibrate-csm-row">
				<strong><?php esc_html_e( 'Catalog Eligibility:', 'wpcalibrate-catalog-sync-meta' ); ?></strong>
				<?php if ( $is_eligible ) : ?>
					<span style="color: #46b450; font-weight: 600;"><?php esc_html_e( 'Eligible', 'wpcalibrate-catalog-sync-meta' ); ?></span>
				<?php else : ?>
					<span style="color: #dc3232; font-weight: 600;"><?php esc_html_e( 'Not Eligible', 'wpcalibrate-catalog-sync-meta' ); ?></span>
					<div class="wpcalibrate-csm-reasons" style="font-size: 11px; color: #666; margin-top: 4px;">
						<?php echo esc_html( implode( ' ', $reasons ) ); ?>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( $last_sync > 0 ) : ?>
				<div class="wpcalibrate-csm-row">
					<strong><?php esc_html_e( 'Last Synced:', 'wpcalibrate-catalog-sync-meta' ); ?></strong>
					<span><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_sync ) ); ?></span>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $last_error ) && 'failed' === $status ) : ?>
				<div class="wpcalibrate-csm-row wpcalibrate-csm-error-box" style="background: #fff8e5; border-left: 3px solid #ffb900; padding: 6px; margin: 8px 0; font-size: 12px;">
					<strong><?php esc_html_e( 'Recent Error:', 'wpcalibrate-catalog-sync-meta' ); ?></strong>
					<div style="word-break: break-word; color: #a00;"><?php echo esc_html( $last_error ); ?></div>
				</div>
			<?php endif; ?>

			<div class="wpcalibrate-csm-row" style="margin-top: 12px; border-top: 1px solid #eee; padding-top: 8px;">
				<label>
					<input type="checkbox" name="wpcalibrate_csm_exclude" value="yes" <?php checked( $is_excluded, true ); ?> />
					<?php esc_html_e( 'Exclude this product from Meta Catalog', 'wpcalibrate-catalog-sync-meta' ); ?>
				</label>
			</div>

			<div class="wpcalibrate-csm-row wpcalibrate-csm-actions" style="margin-top: 12px;">
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wpcalibrate_csm_retry_single_product&product_id=' . $post->ID ), 'wpcalibrate_csm_retry_' . $post->ID ) ); ?>" class="button button-secondary button-small">
					<?php esc_html_e( 'Queue for Sync Now', 'wpcalibrate-catalog-sync-meta' ); ?>
				</a>
			</div>

			<?php if ( $product->is_type( 'variable' ) ) : ?>
				<div class="wpcalibrate-csm-variations-summary" style="margin-top: 12px; border-top: 1px solid #eee; padding-top: 8px;">
					<strong><?php esc_html_e( 'Variations:', 'wpcalibrate-catalog-sync-meta' ); ?></strong>
					<?php
					$children = $product->get_children();
					printf(
						/* translators: %d: Variation count */
						' <span>' . esc_html__( '%d variations in catalog item group.', 'wpcalibrate-catalog-sync-meta' ) . '</span>',
						count( $children )
					);
					?>
				</div>
			<?php endif; ?>
		</div>
		<style>
			.wpcalibrate-csm-product-panel .wpcalibrate-csm-row { margin-bottom: 8px; font-size: 12px; }
			.wpcalibrate-csm-badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: 600; }
			.wpcalibrate-csm-badge.status-success { background: #d4edda; color: #155724; }
			.wpcalibrate-csm-badge.status-warning { background: #fff3cd; color: #856404; }
			.wpcalibrate-csm-badge.status-danger { background: #f8d7da; color: #721c24; }
			.wpcalibrate-csm-badge.status-neutral { background: #e2e3e5; color: #383d41; }
		</style>
		<?php
	}

	/**
	 * Save product metabox inputs.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post instance.
	 * @return void
	 */
	public static function save_meta_box( int $post_id, \WP_Post $post ): void {
		// Nonce check.
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_product', $post_id ) ) {
			return;
		}

		$is_excluded = isset( $_POST['wpcalibrate_csm_exclude'] ) ? 'yes' : 'no';
		$prev        = get_post_meta( $post_id, EligibilityEngine::META_EXCLUDE, true );

		if ( $is_excluded !== $prev ) {
			update_post_meta( $post_id, EligibilityEngine::META_EXCLUDE, $is_excluded );
			// Queue sync to update or delete on Meta.
			QueueManager::enqueue_product( $post_id );
		}
	}

	/**
	 * Handle manual retry button click.
	 *
	 * @return void
	 */
	public static function handle_manual_retry(): void {
		$product_id = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0;

		check_admin_referer( 'wpcalibrate_csm_retry_' . $product_id );

		if ( ! SecurityHelper::check_admin_permission() ) {
			wp_die( esc_html__( 'Unauthorized action.', 'wpcalibrate-catalog-sync-meta' ) );
		}

		if ( $product_id > 0 ) {
			QueueManager::enqueue_product( $product_id );
		}

		wp_safe_redirect( add_query_arg( array( 'post' => $product_id, 'action' => 'edit', 'wpcalibrate_queued' => 1 ), admin_url( 'post.php' ) ) );
		exit;
	}
}
