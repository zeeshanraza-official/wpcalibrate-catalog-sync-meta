<?php
/**
 * Logs and Event History Tab Template.
 *
 * @package WPCalibrate\CatalogSyncMeta
 */

use WPCalibrate\CatalogSyncMeta\Admin\AdminController;
use WPCalibrate\CatalogSyncMeta\Admin\AdminMenu;
use WPCalibrate\CatalogSyncMeta\Data\SyncEventRepository;
use WPCalibrate\CatalogSyncMeta\Settings\SettingsRegistry;

defined( 'ABSPATH' ) || exit;

$settings       = SettingsRegistry::get_all();
$action_url     = admin_url( 'admin.php?page=' . AdminMenu::SUBMENU_SLUG . '&tab=logs' );
$current_status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
$paged          = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 );
$per_page       = 25;

$events_data = SyncEventRepository::get_events(
	array(
		'status' => $current_status,
		'limit'  => $per_page,
		'offset' => ( $paged - 1 ) * $per_page,
	)
);

$events      = $events_data['items'];
$total_count = $events_data['total'];
$total_pages = ceil( $total_count / $per_page );
?>

<div class="wpcalibrate-csm-section">
	<div class="wpcalibrate-csm-section-header">
		<h2><?php esc_html_e( 'Sync Event History & Logs', 'wpcalibrate-catalog-sync-meta' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Inspect granular catalog synchronization operations, API errors, and automated retention policies.', 'wpcalibrate-catalog-sync-meta' ); ?>
		</p>
	</div>

	<!-- Logging & Data Retention Settings -->
	<form method="post" action="<?php echo esc_url( $action_url ); ?>" class="wpcalibrate-csm-form" style="margin-bottom: 30px;">
		<?php wp_nonce_field( AdminController::SAVE_NONCE, 'wpcalibrate_csm_nonce' ); ?>
		<input type="hidden" name="wpcalibrate_csm_save_tab" value="logs" />

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="log_level"><?php esc_html_e( 'WooCommerce Log Level', 'wpcalibrate-catalog-sync-meta' ); ?></label></th>
				<td>
					<select id="log_level" name="log_level">
						<option value="debug" <?php selected( $settings['log_level'], 'debug' ); ?>><?php esc_html_e( 'Debug (Verbose)', 'wpcalibrate-catalog-sync-meta' ); ?></option>
						<option value="info" <?php selected( $settings['log_level'], 'info' ); ?>><?php esc_html_e( 'Info (Recommended)', 'wpcalibrate-catalog-sync-meta' ); ?></option>
						<option value="warning" <?php selected( $settings['log_level'], 'warning' ); ?>><?php esc_html_e( 'Warning', 'wpcalibrate-catalog-sync-meta' ); ?></option>
						<option value="error" <?php selected( $settings['log_level'], 'error' ); ?>><?php esc_html_e( 'Errors Only', 'wpcalibrate-catalog-sync-meta' ); ?></option>
					</select>
					<span class="description" style="margin-left: 10px;">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-status&tab=logs' ) ); ?>" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'View WooCommerce Logs &rarr;', 'wpcalibrate-catalog-sync-meta' ); ?>
						</a>
					</span>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="data_retention_days"><?php esc_html_e( 'Diagnostic Data Retention', 'wpcalibrate-catalog-sync-meta' ); ?></label></th>
				<td>
					<select id="data_retention_days" name="data_retention_days">
						<option value="7" <?php selected( $settings['data_retention_days'], 7 ); ?>><?php esc_html_e( '7 Days', 'wpcalibrate-catalog-sync-meta' ); ?></option>
						<option value="14" <?php selected( $settings['data_retention_days'], 14 ); ?>><?php esc_html_e( '14 Days', 'wpcalibrate-catalog-sync-meta' ); ?></option>
						<option value="30" <?php selected( $settings['data_retention_days'], 30 ); ?>><?php esc_html_e( '30 Days (Default)', 'wpcalibrate-catalog-sync-meta' ); ?></option>
						<option value="60" <?php selected( $settings['data_retention_days'], 60 ); ?>><?php esc_html_e( '60 Days', 'wpcalibrate-catalog-sync-meta' ); ?></option>
						<option value="90" <?php selected( $settings['data_retention_days'], 90 ); ?>><?php esc_html_e( '90 Days', 'wpcalibrate-catalog-sync-meta' ); ?></option>
					</select>
					<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'csm_action', 'prune_logs', $action_url ), 'csm_action_prune_logs' ) ); ?>" class="button button-secondary" style="margin-left: 12px;">
						<?php esc_html_e( 'Prune Old Records Now', 'wpcalibrate-catalog-sync-meta' ); ?>
					</a>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Uninstall Cleanup Policy', 'wpcalibrate-catalog-sync-meta' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="delete_data_on_uninstall" value="1" <?php checked( ! empty( $settings['delete_data_on_uninstall'] ) ); ?> />
						<strong style="color: #a00;"><?php esc_html_e( 'Delete all plugin settings, sync history, and database tables when uninstalled', 'wpcalibrate-catalog-sync-meta' ); ?></strong>
					</label>
					<p class="description">
						<?php esc_html_e( 'Default: OFF. When unchecked, plugin settings and sync history are preserved during updates or reinstalls. Note: WooCommerce products, orders, categories, and customer data are never deleted.', 'wpcalibrate-catalog-sync-meta' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save Logging Settings', 'wpcalibrate-catalog-sync-meta' ) ); ?>
	</form>

	<!-- Filter Submenu -->
	<div class="wpcalibrate-csm-filter-bar">
		<ul class="subsubsub">
			<li><a href="<?php echo esc_url( $action_url ); ?>" class="<?php echo empty( $current_status ) ? 'current' : ''; ?>"><?php esc_html_e( 'All Events', 'wpcalibrate-catalog-sync-meta' ); ?></a> |</li>
			<li><a href="<?php echo esc_url( add_query_arg( 'status', 'synced', $action_url ) ); ?>" class="<?php echo 'synced' === $current_status ? 'current' : ''; ?>"><?php esc_html_e( 'Synced', 'wpcalibrate-catalog-sync-meta' ); ?></a> |</li>
			<li><a href="<?php echo esc_url( add_query_arg( 'status', 'failed', $action_url ) ); ?>" class="<?php echo 'failed' === $current_status ? 'current' : ''; ?>"><?php esc_html_e( 'Failed', 'wpcalibrate-catalog-sync-meta' ); ?></a> |</li>
			<li><a href="<?php echo esc_url( add_query_arg( 'status', 'skipped', $action_url ) ); ?>" class="<?php echo 'skipped' === $current_status ? 'current' : ''; ?>"><?php esc_html_e( 'Skipped / Excluded', 'wpcalibrate-catalog-sync-meta' ); ?></a></li>
		</ul>
	</div>

	<!-- Event Table -->
	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th style="width: 60px;"><?php esc_html_e( 'ID', 'wpcalibrate-catalog-sync-meta' ); ?></th>
				<th style="width: 100px;"><?php esc_html_e( 'Product ID', 'wpcalibrate-catalog-sync-meta' ); ?></th>
				<th style="width: 100px;"><?php esc_html_e( 'Type', 'wpcalibrate-catalog-sync-meta' ); ?></th>
				<th style="width: 90px;"><?php esc_html_e( 'Status', 'wpcalibrate-catalog-sync-meta' ); ?></th>
				<th><?php esc_html_e( 'Details / Error Message', 'wpcalibrate-catalog-sync-meta' ); ?></th>
				<th style="width: 160px;"><?php esc_html_e( 'Timestamp (UTC)', 'wpcalibrate-catalog-sync-meta' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( ! empty( $events ) ) : ?>
				<?php foreach ( $events as $evt ) : ?>
					<tr>
						<td>#<?php echo esc_html( $evt->id ); ?></td>
						<td>
							<strong>
								<a href="<?php echo esc_url( get_edit_post_link( $evt->product_id ) ); ?>" target="_blank" rel="noopener noreferrer">
									#<?php echo esc_html( $evt->product_id ); ?>
								</a>
							</strong>
							<?php if ( ! empty( $evt->variation_id ) ) : ?>
								<small>(Var #<?php echo esc_html( $evt->variation_id ); ?>)</small>
							<?php endif; ?>
						</td>
						<td><code><?php echo esc_html( $evt->event_type ); ?></code></td>
						<td>
							<span class="wpcalibrate-csm-badge status-<?php echo esc_attr( $evt->status ); ?>">
								<?php echo esc_html( ucfirst( $evt->status ) ); ?>
							</span>
						</td>
						<td>
							<?php if ( ! empty( $evt->error_code ) ) : ?>
								<strong style="color: #a00;">[<?php echo esc_html( $evt->error_code ); ?>]</strong>
							<?php endif; ?>
							<span><?php echo esc_html( $evt->error_message ?: '-' ); ?></span>
						</td>
						<td><?php echo esc_html( $evt->created_at ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr>
					<td colspan="6"><?php esc_html_e( 'No log records recorded matching criteria.', 'wpcalibrate-catalog-sync-meta' ); ?></td>
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
						'base'      => add_query_arg( 'paged', '%#%', $action_url ),
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
