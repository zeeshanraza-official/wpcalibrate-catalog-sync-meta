<?php
/**
 * Dashboard Tab Template.
 *
 * @package WPCalibrate\CatalogSyncMeta
 */

use WPCalibrate\CatalogSyncMeta\Admin\AdminMenu;
use WPCalibrate\CatalogSyncMeta\Api\ConnectionHealth;
use WPCalibrate\CatalogSyncMeta\Data\SyncEventRepository;
use WPCalibrate\CatalogSyncMeta\Feed\XmlFeedGenerator;
use WPCalibrate\CatalogSyncMeta\Queue\QueueManager;
use WPCalibrate\CatalogSyncMeta\Settings\SettingsRegistry;
use WPCalibrate\CatalogSyncMeta\Sync\ReconciliationService;

defined( 'ABSPATH' ) || exit;

$settings       = SettingsRegistry::get_all();
$counts         = SyncEventRepository::get_status_counts();
$health         = ConnectionHealth::get_status_presentation();
$queue_size     = QueueManager::get_queue_size();
$is_paused      = ! empty( $settings['is_sync_paused'] );
$auto_sync      = ! empty( $settings['enable_auto_sync'] );
$is_reconciling = ReconciliationService::is_running();
$feed_enabled   = ! empty( $settings['enable_feed'] );

// Approximate WooCommerce product counts.
$product_counts = function_exists( 'wp_count_posts' ) ? wp_count_posts( 'product' ) : (object) array( 'publish' => 0 );
$total_products = (int) ( $product_counts->publish ?? 0 );

// Recent failures.
$recent_failures = SyncEventRepository::get_events( array( 'status' => 'failed', 'limit' => 5 ) );

$action_url = admin_url( 'admin.php?page=' . AdminMenu::SUBMENU_SLUG . '&tab=dashboard' );
?>

<div class="wpcalibrate-csm-dashboard">
	<!-- Top Metric Cards -->
	<div class="wpcalibrate-csm-cards-grid">
		<div class="wpcalibrate-csm-card">
			<div class="wpcalibrate-csm-card-label"><?php esc_html_e( 'Connection Health', 'wpcalibrate-catalog-sync-meta' ); ?></div>
			<div class="wpcalibrate-csm-card-value">
				<span class="wpcalibrate-csm-badge <?php echo esc_attr( $health['badge_class'] ); ?>">
					<?php echo esc_html( $health['label'] ); ?>
				</span>
			</div>
			<div class="wpcalibrate-csm-card-sub">
				<?php
				if ( ! empty( $settings['meta_catalog_id'] ) ) {
					/* translators: %s: Catalog ID */
					printf( esc_html__( 'Catalog ID: %s', 'wpcalibrate-catalog-sync-meta' ), esc_html( $settings['meta_catalog_id'] ) );
				} else {
					esc_html_e( 'No catalog connected', 'wpcalibrate-catalog-sync-meta' );
				}
				?>
			</div>
		</div>

		<div class="wpcalibrate-csm-card">
			<div class="wpcalibrate-csm-card-label"><?php esc_html_e( 'Synced Items', 'wpcalibrate-catalog-sync-meta' ); ?></div>
			<div class="wpcalibrate-csm-card-value text-success"><?php echo esc_html( number_format_i18n( $counts['synced'] ) ); ?></div>
			<div class="wpcalibrate-csm-card-sub"><?php printf( esc_html__( 'Of %s published products', 'wpcalibrate-catalog-sync-meta' ), esc_html( number_format_i18n( $total_products ) ) ); ?></div>
		</div>

		<div class="wpcalibrate-csm-card">
			<div class="wpcalibrate-csm-card-label"><?php esc_html_e( 'Pending in Queue', 'wpcalibrate-catalog-sync-meta' ); ?></div>
			<div class="wpcalibrate-csm-card-value text-warning"><?php echo esc_html( number_format_i18n( $queue_size ) ); ?></div>
			<div class="wpcalibrate-csm-card-sub">
				<?php echo $is_paused ? '<strong style="color:#d63638;">' . esc_html__( 'Sync Paused', 'wpcalibrate-catalog-sync-meta' ) . '</strong>' : esc_html__( 'Processing active', 'wpcalibrate-catalog-sync-meta' ); ?>
			</div>
		</div>

		<div class="wpcalibrate-csm-card">
			<div class="wpcalibrate-csm-card-label"><?php esc_html_e( 'Failed Items', 'wpcalibrate-catalog-sync-meta' ); ?></div>
			<div class="wpcalibrate-csm-card-value text-danger"><?php echo esc_html( number_format_i18n( $counts['failed'] ) ); ?></div>
			<div class="wpcalibrate-csm-card-sub">
				<?php if ( $counts['failed'] > 0 ) : ?>
					<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'csm_action', 'retry_failed', $action_url ), 'csm_action_retry_failed' ) ); ?>" class="button-link-danger">
						<?php esc_html_e( 'Retry Failed Now &rarr;', 'wpcalibrate-catalog-sync-meta' ); ?>
					</a>
				<?php else : ?>
					<?php esc_html_e( 'No sync errors recorded', 'wpcalibrate-catalog-sync-meta' ); ?>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<!-- Quick Action Buttons Bar -->
	<div class="wpcalibrate-csm-actions-bar">
		<h3><?php esc_html_e( 'Catalog Operations', 'wpcalibrate-catalog-sync-meta' ); ?></h3>
		<div class="wpcalibrate-csm-buttons-cluster">
			<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'csm_action', 'sync_changed', $action_url ), 'csm_action_sync_changed' ) ); ?>" class="button button-primary">
				<?php esc_html_e( 'Sync Changed Products', 'wpcalibrate-catalog-sync-meta' ); ?>
			</a>

			<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'csm_action', 'full_reconcile', $action_url ), 'csm_action_full_reconcile' ) ); ?>" class="button button-secondary wpcalibrate-confirm-reconcile">
				<?php esc_html_e( 'Full Catalog Sync', 'wpcalibrate-catalog-sync-meta' ); ?>
			</a>

			<?php if ( $counts['failed'] > 0 ) : ?>
				<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'csm_action', 'retry_failed', $action_url ), 'csm_action_retry_failed' ) ); ?>" class="button button-secondary">
					<?php esc_html_e( 'Retry Failed', 'wpcalibrate-catalog-sync-meta' ); ?>
				</a>
			<?php endif; ?>

			<?php if ( $is_paused ) : ?>
				<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'csm_action', 'resume_sync', $action_url ), 'csm_action_resume_sync' ) ); ?>" class="button button-secondary button-resume">
					<?php esc_html_e( 'Resume Automatic Sync', 'wpcalibrate-catalog-sync-meta' ); ?>
				</a>
			<?php else : ?>
				<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'csm_action', 'pause_sync', $action_url ), 'csm_action_pause_sync' ) ); ?>" class="button button-secondary button-pause">
					<?php esc_html_e( 'Pause Automatic Sync', 'wpcalibrate-catalog-sync-meta' ); ?>
				</a>
			<?php endif; ?>

			<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'csm_action', 'test_connection', $action_url ), 'csm_action_test_connection' ) ); ?>" class="button button-secondary">
				<?php esc_html_e( 'Test Connection', 'wpcalibrate-catalog-sync-meta' ); ?>
			</a>
		</div>
	</div>

	<!-- Status Details Grid -->
	<div class="wpcalibrate-csm-split-grid">
		<!-- Left: Sync Timing & Operational Status -->
		<div class="wpcalibrate-csm-box">
			<h4><?php esc_html_e( 'Synchronization Timestamps', 'wpcalibrate-catalog-sync-meta' ); ?></h4>
			<table class="wpcalibrate-csm-table-detail">
				<tr>
					<th><?php esc_html_e( 'Last Incremental Sync:', 'wpcalibrate-catalog-sync-meta' ); ?></th>
					<td><?php echo $settings['last_incremental_sync'] ? esc_html( wp_date( 'Y-m-d H:i:s', $settings['last_incremental_sync'] ) ) : esc_html__( 'Never', 'wpcalibrate-catalog-sync-meta' ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Last Full Reconciliation:', 'wpcalibrate-catalog-sync-meta' ); ?></th>
					<td><?php echo $settings['last_reconciliation_time'] ? esc_html( wp_date( 'Y-m-d H:i:s', $settings['last_reconciliation_time'] ) ) : esc_html__( 'Never', 'wpcalibrate-catalog-sync-meta' ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Reconciliation Schedule:', 'wpcalibrate-catalog-sync-meta' ); ?></th>
					<td><?php echo esc_html( ucfirst( (string) $settings['reconciliation_interval'] ) ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Current Sync State:', 'wpcalibrate-catalog-sync-meta' ); ?></th>
					<td>
						<?php
						if ( $is_reconciling ) {
							echo '<span class="text-warning"><strong>' . esc_html__( 'Reconciliation running in background', 'wpcalibrate-catalog-sync-meta' ) . '</strong></span>';
						} elseif ( $is_paused ) {
							echo '<span class="text-danger">' . esc_html__( 'Paused by administrator', 'wpcalibrate-catalog-sync-meta' ) . '</span>';
						} elseif ( $queue_size > 0 ) {
							echo '<span class="text-info">' . esc_html__( 'Processing background queue', 'wpcalibrate-catalog-sync-meta' ) . '</span>';
						} else {
							echo '<span class="text-success">' . esc_html__( 'Idle / Up to date', 'wpcalibrate-catalog-sync-meta' ) . '</span>';
						}
						?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'XML Feed Status:', 'wpcalibrate-catalog-sync-meta' ); ?></th>
					<td>
						<?php if ( $feed_enabled ) : ?>
							<span class="text-success"><?php esc_html_e( 'Enabled & Active', 'wpcalibrate-catalog-sync-meta' ); ?></span>
							<a href="<?php echo esc_url( XmlFeedGenerator::get_feed_url() ); ?>" target="_blank" rel="noopener noreferrer" style="margin-left:8px;">
								<?php esc_html_e( 'View Feed XML', 'wpcalibrate-catalog-sync-meta' ); ?>
							</a>
						<?php else : ?>
							<span class="text-neutral"><?php esc_html_e( 'Disabled', 'wpcalibrate-catalog-sync-meta' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
			</table>
		</div>

		<!-- Right: Recent Critical Errors -->
		<div class="wpcalibrate-csm-box">
			<h4><?php esc_html_e( 'Recent Sync Failures', 'wpcalibrate-catalog-sync-meta' ); ?></h4>
			<?php if ( ! empty( $recent_failures['items'] ) ) : ?>
				<ul class="wpcalibrate-csm-recent-errors">
					<?php foreach ( $recent_failures['items'] as $err ) : ?>
						<li>
							<strong>#<?php echo esc_html( $err->product_id ); ?></strong>
							<?php if ( ! empty( $err->error_code ) ) : ?>
								<code>[<?php echo esc_html( $err->error_code ); ?>]</code>
							<?php endif; ?>
							<span><?php echo esc_html( $err->error_message ); ?></span>
							<small><?php echo esc_html( $err->created_at ); ?></small>
						</li>
					<?php endforeach; ?>
				</ul>
				<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SUBMENU_SLUG . '&tab=logs&status=failed' ) ); ?>"><?php esc_html_e( 'View full error history &rarr;', 'wpcalibrate-catalog-sync-meta' ); ?></a></p>
			<?php else : ?>
				<p class="wpcalibrate-csm-no-errors"><?php esc_html_e( 'No recent sync errors. All eligible catalog products are running cleanly.', 'wpcalibrate-catalog-sync-meta' ); ?></p>
			<?php endif; ?>
		</div>
	</div>
</div>
