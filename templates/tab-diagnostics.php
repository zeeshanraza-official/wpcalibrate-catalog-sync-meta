<?php
/**
 * Diagnostics Tab Template.
 *
 * @package WPCalibrate\CatalogSyncMeta
 */

use WPCalibrate\CatalogSyncMeta\Logging\DiagnosticsReporter;

defined( 'ABSPATH' ) || exit;

$report_text = DiagnosticsReporter::get_formatted_text_report();
$report_data = DiagnosticsReporter::get_report_data();
?>

<div class="wpcalibrate-csm-section">
	<div class="wpcalibrate-csm-section-header">
		<h2><?php esc_html_e( 'System Diagnostics & Health Report', 'wpcalibrate-catalog-sync-meta' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Detailed environment metrics, database integrity, and connection diagnostics. All sensitive credentials, tokens, and license keys are automatically redacted.', 'wpcalibrate-catalog-sync-meta' ); ?>
		</p>
	</div>

	<!-- System Overview Table -->
	<div class="wpcalibrate-csm-cards-grid" style="margin-bottom: 25px;">
		<div class="wpcalibrate-csm-card">
			<div class="wpcalibrate-csm-card-label"><?php esc_html_e( 'PHP Environment', 'wpcalibrate-catalog-sync-meta' ); ?></div>
			<div class="wpcalibrate-csm-card-value" style="font-size: 18px;"><?php echo esc_html( PHP_VERSION ); ?></div>
			<div class="wpcalibrate-csm-card-sub"><?php printf( esc_html__( 'Memory Limit: %s', 'wpcalibrate-catalog-sync-meta' ), esc_html( ini_get( 'memory_limit' ) ) ); ?></div>
		</div>

		<div class="wpcalibrate-csm-card">
			<div class="wpcalibrate-csm-card-label"><?php esc_html_e( 'WooCommerce HPOS', 'wpcalibrate-catalog-sync-meta' ); ?></div>
			<div class="wpcalibrate-csm-card-value" style="font-size: 18px;"><?php echo esc_html( $report_data['environment']['hpos_enabled'] ); ?></div>
			<div class="wpcalibrate-csm-card-sub"><?php esc_html_e( 'High-Performance Order Storage', 'wpcalibrate-catalog-sync-meta' ); ?></div>
		</div>

		<div class="wpcalibrate-csm-card">
			<div class="wpcalibrate-csm-card-label"><?php esc_html_e( 'Action Scheduler', 'wpcalibrate-catalog-sync-meta' ); ?></div>
			<div class="wpcalibrate-csm-card-value text-success" style="font-size: 18px;"><?php echo esc_html( $report_data['environment']['action_scheduler'] ); ?></div>
			<div class="wpcalibrate-csm-card-sub"><?php esc_html_e( 'Background Processing Engine', 'wpcalibrate-catalog-sync-meta' ); ?></div>
		</div>

		<div class="wpcalibrate-csm-card">
			<div class="wpcalibrate-csm-card-label"><?php esc_html_e( 'DB Schema Version', 'wpcalibrate-catalog-sync-meta' ); ?></div>
			<div class="wpcalibrate-csm-card-value" style="font-size: 18px;"><?php echo esc_html( $report_data['environment']['db_schema_version'] ); ?></div>
			<div class="wpcalibrate-csm-card-sub"><?php esc_html_e( 'Custom Sync Events Table', 'wpcalibrate-catalog-sync-meta' ); ?></div>
		</div>
	</div>

	<!-- Copy System Report Box -->
	<div class="wpcalibrate-csm-box">
		<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
			<h3 style="margin: 0;"><?php esc_html_e( 'System Report (Sanitized)', 'wpcalibrate-catalog-sync-meta' ); ?></h3>
			<button type="button" class="button button-primary wpcalibrate-copy-btn" data-target="#wpcalibrate_system_report">
				<?php esc_html_e( 'Copy System Report', 'wpcalibrate-catalog-sync-meta' ); ?>
			</button>
		</div>

		<textarea id="wpcalibrate_system_report" class="large-text code" rows="16" readonly="readonly" style="font-family: monospace; font-size: 12px; background: #fafafa;"><?php echo esc_textarea( $report_text ); ?></textarea>
		<p class="description">
			<?php esc_html_e( 'Provide this report when requesting support. Access tokens, app secrets, and license keys are completely redacted.', 'wpcalibrate-catalog-sync-meta' ); ?>
		</p>
	</div>
</div>
