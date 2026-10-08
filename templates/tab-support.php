<?php
/**
 * Support Tab Template.
 *
 * @package WPCalibrate\CatalogSyncMeta
 */

use WPCalibrate\CatalogSyncMeta\Support\SupportManager;

defined( 'ABSPATH' ) || exit;

$details = SupportManager::get_details();
?>

<div class="wpcalibrate-csm-section">
	<div class="wpcalibrate-csm-section-header">
		<h2><?php esc_html_e( 'Customer Support & Resources', 'wpcalibrate-catalog-sync-meta' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Need help with your catalog integration, field mapping, or Meta setup? The WPCalibrate team is here to assist.', 'wpcalibrate-catalog-sync-meta' ); ?>
		</p>
	</div>

	<div class="wpcalibrate-csm-cards-grid" style="margin-bottom: 30px;">
		<div class="wpcalibrate-csm-card">
			<div class="wpcalibrate-csm-card-label"><?php esc_html_e( 'Direct Email Support', 'wpcalibrate-catalog-sync-meta' ); ?></div>
			<div class="wpcalibrate-csm-card-value" style="font-size: 16px;">
				<a href="mailto:<?php echo esc_attr( $details['email'] ); ?>">
					<?php echo esc_html( $details['email'] ); ?>
				</a>
			</div>
			<div class="wpcalibrate-csm-card-sub"><?php esc_html_e( 'Guaranteed reply within 24 hours on business days.', 'wpcalibrate-catalog-sync-meta' ); ?></div>
		</div>

		<div class="wpcalibrate-csm-card">
			<div class="wpcalibrate-csm-card-label"><?php esc_html_e( 'Phone & WhatsApp', 'wpcalibrate-catalog-sync-meta' ); ?></div>
			<div class="wpcalibrate-csm-card-value" style="font-size: 16px;">
				<a href="https://wa.me/447474795976" target="_blank" rel="noopener noreferrer">
					<?php echo esc_html( $details['phone'] ); ?>
				</a>
			</div>
			<div class="wpcalibrate-csm-card-sub"><?php esc_html_e( 'Direct priority customer messaging.', 'wpcalibrate-catalog-sync-meta' ); ?></div>
		</div>

		<div class="wpcalibrate-csm-card">
			<div class="wpcalibrate-csm-card-label"><?php esc_html_e( 'WPCalibrate Marketplace', 'wpcalibrate-catalog-sync-meta' ); ?></div>
			<div class="wpcalibrate-csm-card-value" style="font-size: 16px;">
				<a href="<?php echo esc_url( $details['marketplace'] ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'marketplace.wpcalibrate.com', 'wpcalibrate-catalog-sync-meta' ); ?>
				</a>
			</div>
			<div class="wpcalibrate-csm-card-sub"><?php esc_html_e( 'Manage subscriptions, licenses & add-ons.', 'wpcalibrate-catalog-sync-meta' ); ?></div>
		</div>

		<div class="wpcalibrate-csm-card">
			<div class="wpcalibrate-csm-card-label"><?php esc_html_e( 'Official Website', 'wpcalibrate-catalog-sync-meta' ); ?></div>
			<div class="wpcalibrate-csm-card-value" style="font-size: 16px;">
				<a href="<?php echo esc_url( $details['website'] ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'wpcalibrate.com', 'wpcalibrate-catalog-sync-meta' ); ?>
				</a>
			</div>
			<div class="wpcalibrate-csm-card-sub"><?php esc_html_e( 'Documentation, tutorials, and plugin guides.', 'wpcalibrate-catalog-sync-meta' ); ?></div>
		</div>
	</div>

	<!-- Trademark Notice Box -->
	<div class="wpcalibrate-csm-box" style="background: #fdfdfd; border-color: #e2e4e7;">
		<h4><?php esc_html_e( 'Legal & Trademark Notices', 'wpcalibrate-catalog-sync-meta' ); ?></h4>
		<p style="font-size: 12px; color: #666; line-height: 1.6;">
			<?php esc_html_e( 'Meta and Facebook are registered trademarks of Meta Platforms, Inc. WPCalibrate Catalog Sync for Meta is an independent commercial extension developed by WPCalibrate and is not affiliated with, authorized, maintained, sponsored, or endorsed by Meta Platforms, Inc.', 'wpcalibrate-catalog-sync-meta' ); ?>
		</p>
	</div>
</div>
