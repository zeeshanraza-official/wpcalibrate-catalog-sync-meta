<?php
/**
 * Catalog Status and Products Table Tab.
 *
 * @package WPCalibrate\CatalogSyncMeta
 */

use WPCalibrate\CatalogSyncMeta\Admin\AdminMenu;
use WPCalibrate\CatalogSyncMeta\Catalog\ProductIdentifier;
use WPCalibrate\CatalogSyncMeta\Sync\DirectSyncService;

defined( 'ABSPATH' ) || exit;

// Filter arguments.
$current_status = isset( $_GET['sync_status'] ) ? sanitize_key( $_GET['sync_status'] ) : '';
$paged          = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 );
$per_page       = 20;

$meta_query = array();
if ( ! empty( $current_status ) ) {
	$meta_query[] = array(
		'key'   => DirectSyncService::META_SYNC_STATUS,
		'value' => $current_status,
	);
}

$query_args = array(
	'post_type'      => 'product',
	'post_status'    => array( 'publish', 'draft' ),
	'posts_per_page' => $per_page,
	'paged'          => $paged,
	'orderby'        => 'ID',
	'order'          => 'DESC',
);

if ( ! empty( $meta_query ) ) {
	$query_args['meta_query'] = $meta_query;
}

$products_query = new \WP_Query( $query_args );
$total_pages    = $products_query->max_num_pages;
$base_url       = admin_url( 'admin.php?page=' . AdminMenu::SUBMENU_SLUG . '&tab=catalog' );
?>

<div class="wpcalibrate-csm-section">
	<div class="wpcalibrate-csm-section-header">
		<h2><?php esc_html_e( 'Catalog Products Status', 'wpcalibrate-catalog-sync-meta' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Inspect synchronization status, identifiers, and diagnostics for store products.', 'wpcalibrate-catalog-sync-meta' ); ?>
		</p>
	</div>

	<!-- Status Filter Tabs -->
	<div class="wpcalibrate-csm-filter-bar">
		<ul class="subsubsub">
			<li>
				<a href="<?php echo esc_url( $base_url ); ?>" class="<?php echo empty( $current_status ) ? 'current' : ''; ?>">
					<?php esc_html_e( 'All', 'wpcalibrate-catalog-sync-meta' ); ?>
				</a> |
			</li>
			<li>
				<a href="<?php echo esc_url( add_query_arg( 'sync_status', 'synced', $base_url ) ); ?>" class="<?php echo 'synced' === $current_status ? 'current' : ''; ?>">
					<?php esc_html_e( 'Synced', 'wpcalibrate-catalog-sync-meta' ); ?>
				</a> |
			</li>
			<li>
				<a href="<?php echo esc_url( add_query_arg( 'sync_status', 'pending', $base_url ) ); ?>" class="<?php echo 'pending' === $current_status ? 'current' : ''; ?>">
					<?php esc_html_e( 'Pending', 'wpcalibrate-catalog-sync-meta' ); ?>
				</a> |
			</li>
			<li>
				<a href="<?php echo esc_url( add_query_arg( 'sync_status', 'failed', $base_url ) ); ?>" class="<?php echo 'failed' === $current_status ? 'current' : ''; ?>">
					<?php esc_html_e( 'Failed', 'wpcalibrate-catalog-sync-meta' ); ?>
				</a> |
			</li>
			<li>
				<a href="<?php echo esc_url( add_query_arg( 'sync_status', 'excluded', $base_url ) ); ?>" class="<?php echo 'excluded' === $current_status ? 'current' : ''; ?>">
					<?php esc_html_e( 'Excluded', 'wpcalibrate-catalog-sync-meta' ); ?>
				</a>
			</li>
		</ul>
	</div>

	<!-- Products Table -->
	<table class="wp-list-table widefat fixed striped table-view-list">
		<thead>
			<tr>
				<th style="width: 70px;"><?php esc_html_e( 'ID', 'wpcalibrate-catalog-sync-meta' ); ?></th>
				<th><?php esc_html_e( 'Product Title', 'wpcalibrate-catalog-sync-meta' ); ?></th>
				<th><?php esc_html_e( 'Type', 'wpcalibrate-catalog-sync-meta' ); ?></th>
				<th><?php esc_html_e( 'Retailer ID', 'wpcalibrate-catalog-sync-meta' ); ?></th>
				<th><?php esc_html_e( 'Sync Status', 'wpcalibrate-catalog-sync-meta' ); ?></th>
				<th><?php esc_html_e( 'Last Sync', 'wpcalibrate-catalog-sync-meta' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'wpcalibrate-catalog-sync-meta' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( $products_query->have_posts() ) : ?>
				<?php
				while ( $products_query->have_posts() ) :
					$products_query->the_post();
					$product = wc_get_product( get_the_ID() );
					if ( ! $product ) {
						continue;
					}
					$p_id        = $product->get_id();
					$retailer_id = ProductIdentifier::get_retailer_id( $product );
					$status      = get_post_meta( $p_id, DirectSyncService::META_SYNC_STATUS, true ) ?: 'not_synced';
					$last_sync   = (int) get_post_meta( $p_id, DirectSyncService::META_LAST_SYNC, true );
					$last_error  = get_post_meta( $p_id, DirectSyncService::META_LAST_ERROR, true );
					?>
					<tr>
						<td>#<?php echo esc_html( $p_id ); ?></td>
						<td>
							<strong>
								<a href="<?php echo esc_url( get_edit_post_link( $p_id ) ); ?>" target="_blank" rel="noopener noreferrer">
									<?php echo esc_html( $product->get_name() ); ?>
								</a>
							</strong>
							<?php if ( ! empty( $last_error ) && 'failed' === $status ) : ?>
								<div class="row-error" style="color: #a00; font-size: 11px; margin-top: 4px;">
									<?php echo esc_html( $last_error ); ?>
								</div>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( ucfirst( $product->get_type() ) ); ?></td>
						<td><code><?php echo esc_html( $retailer_id ); ?></code></td>
						<td>
							<span class="wpcalibrate-csm-badge status-<?php echo esc_attr( $status ); ?>">
								<?php echo esc_html( ucfirst( str_replace( '_', ' ', $status ) ) ); ?>
							</span>
						</td>
						<td>
							<?php echo $last_sync ? esc_html( wp_date( 'Y-m-d H:i', $last_sync ) ) : esc_html__( 'Never', 'wpcalibrate-catalog-sync-meta' ); ?>
						</td>
						<td>
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wpcalibrate_csm_retry_single_product&product_id=' . $p_id ), 'wpcalibrate_csm_retry_' . $p_id ) ); ?>" class="button button-small">
								<?php esc_html_e( 'Queue Sync', 'wpcalibrate-catalog-sync-meta' ); ?>
							</a>
						</td>
					</tr>
				<?php endwhile; ?>
				<?php wp_reset_postdata(); ?>
			<?php else : ?>
				<tr>
					<td colspan="7"><?php esc_html_e( 'No products found matching the criteria.', 'wpcalibrate-catalog-sync-meta' ); ?></td>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>

	<!-- Pagination -->
	<?php if ( $total_pages > 1 ) : ?>
		<div class="tablenav" style="margin-top: 15px;">
			<div class="tablenav-pages">
				<?php
				echo paginate_links(
					array(
						'base'      => add_query_arg( 'paged', '%#%', $base_url ),
						'format'    => '',
						'prev_text' => '&laquo;',
						'next_text' => '&raquo;',
						'total'     => $total_pages,
						'current'   => $paged,
					)
				);
				?>
			</div>
		</div>
	<?php endif; ?>
</div>
