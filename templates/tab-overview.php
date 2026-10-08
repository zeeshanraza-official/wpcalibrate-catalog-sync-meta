<?php
/**
 * Overview Tab Template - Plugin Purpose, Methods & Guided Walkthrough.
 *
 * @package WPCalibrate\CatalogSyncMeta
 */

use WPCalibrate\CatalogSyncMeta\Admin\AdminMenu;
use WPCalibrate\CatalogSyncMeta\Api\ConnectionHealth;

defined( 'ABSPATH' ) || exit;

$base_url       = admin_url( 'admin.php?page=' . AdminMenu::SUBMENU_SLUG );
$connection_url = add_query_arg( 'tab', 'connection', $base_url );
$dashboard_url  = add_query_arg( 'tab', 'dashboard', $base_url );
$rules_url      = add_query_arg( 'tab', 'rules', $base_url );
$mapping_url    = add_query_arg( 'tab', 'mapping', $base_url );
$sync_url       = add_query_arg( 'tab', 'sync', $base_url );
$feed_url       = add_query_arg( 'tab', 'feed', $base_url );
$diag_url       = add_query_arg( 'tab', 'diagnostics', $base_url );
$health         = ConnectionHealth::get_status_presentation();
?>

<div class="wpcalibrate-csm-overview-container">

	<!-- Hero Welcome Banner -->
	<div class="wpcalibrate-csm-overview-hero">
		<div class="wpcalibrate-csm-overview-hero-content">
			<div class="wpcalibrate-csm-overview-hero-title">
				<h2><?php esc_html_e( 'Welcome to WPCalibrate Catalog Sync for Meta', 'wpcalibrate-catalog-sync-meta' ); ?></h2>
				<p class="wpcalibrate-csm-overview-hero-lead">
					<?php esc_html_e( 'Seamlessly synchronize your WooCommerce store products with Meta (Facebook & Instagram) Product Catalogs. Power Dynamic Ads, Instagram Shopping, and Advantage+ Catalog campaigns with real-time inventory precision and zero server slowdown.', 'wpcalibrate-catalog-sync-meta' ); ?>
				</p>
			</div>
			<div class="wpcalibrate-csm-overview-badges">
				<span class="wpcalibrate-csm-pill wpcalibrate-csm-pill-success">
					<span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'HPOS Compatible', 'wpcalibrate-catalog-sync-meta' ); ?>
				</span>
				<span class="wpcalibrate-csm-pill wpcalibrate-csm-pill-primary">
					<span class="dashicons dashicons-rest-api"></span> <?php esc_html_e( 'Meta Graph API v21.0', 'wpcalibrate-catalog-sync-meta' ); ?>
				</span>
				<span class="wpcalibrate-csm-pill wpcalibrate-csm-pill-purple">
					<span class="dashicons dashicons-clock"></span> <?php esc_html_e( 'Action Scheduler Powered', 'wpcalibrate-catalog-sync-meta' ); ?>
				</span>
				<span class="wpcalibrate-csm-pill wpcalibrate-csm-pill-warning">
					<span class="dashicons dashicons-rss"></span> <?php esc_html_e( 'Streaming XML RSS Feed', 'wpcalibrate-catalog-sync-meta' ); ?>
				</span>
				<span class="wpcalibrate-csm-pill wpcalibrate-csm-pill-neutral">
					<span class="dashicons dashicons-block-default"></span> <?php esc_html_e( 'Cart & Checkout Blocks Ready', 'wpcalibrate-catalog-sync-meta' ); ?>
				</span>
			</div>
		</div>
	</div>

	<!-- Quick Actions Bar -->
	<div class="wpcalibrate-csm-quick-actions-bar">
		<a href="<?php echo esc_url( $connection_url ); ?>" class="button button-primary wpcalibrate-csm-btn-action">
			<span class="dashicons dashicons-admin-links"></span>
			<?php esc_html_e( '1. Connect Meta Catalog', 'wpcalibrate-catalog-sync-meta' ); ?>
		</a>
		<a href="<?php echo esc_url( $rules_url ); ?>" class="button button-secondary wpcalibrate-csm-btn-action">
			<span class="dashicons dashicons-filter"></span>
			<?php esc_html_e( '2. Configure Product Rules', 'wpcalibrate-catalog-sync-meta' ); ?>
		</a>
		<a href="<?php echo esc_url( $mapping_url ); ?>" class="button button-secondary wpcalibrate-csm-btn-action">
			<span class="dashicons dashicons-tag"></span>
			<?php esc_html_e( '3. Field Mapping', 'wpcalibrate-catalog-sync-meta' ); ?>
		</a>
		<a href="<?php echo esc_url( $dashboard_url ); ?>" class="button button-secondary wpcalibrate-csm-btn-action">
			<span class="dashicons dashicons-dashboard"></span>
			<?php esc_html_e( '4. Dashboard & Actions', 'wpcalibrate-catalog-sync-meta' ); ?>
		</a>
		<a href="<?php echo esc_url( $feed_url ); ?>" class="button button-secondary wpcalibrate-csm-btn-action">
			<span class="dashicons dashicons-rss"></span>
			<?php esc_html_e( 'XML Feed Endpoint', 'wpcalibrate-catalog-sync-meta' ); ?>
		</a>
	</div>

	<!-- Section 1: Purpose & Architecture -->
	<div class="wpcalibrate-csm-overview-card">
		<div class="wpcalibrate-csm-card-icon-header">
			<span class="dashicons dashicons-info-outline wpcalibrate-csm-icon-header"></span>
			<div>
				<h3><?php esc_html_e( 'Plugin Purpose & Architecture', 'wpcalibrate-catalog-sync-meta' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Engineered for high volume, zero checkout slowdowns, and enterprise reliability.', 'wpcalibrate-catalog-sync-meta' ); ?></p>
			</div>
		</div>
		<div class="wpcalibrate-csm-card-body-text">
			<p>
				<?php esc_html_e( 'When running Facebook Dynamic Product Ads or Instagram Shopping, out-of-date product catalogs waste advertising spend by showing out-of-stock items, old prices, or incorrect variations. Traditional plugins slow down product saves and order checkouts by making blocking HTTP requests to third-party APIs during page loads.', 'wpcalibrate-catalog-sync-meta' ); ?>
			</p>
			<p>
				<?php esc_html_e( 'WPCalibrate Catalog Sync for Meta solves this with a non-blocking asynchronous architecture built specifically for WooCommerce and modern PHP 8.2+:', 'wpcalibrate-catalog-sync-meta' ); ?>
			</p>
			<div class="wpcalibrate-csm-features-grid">
				<div class="wpcalibrate-csm-feature-box">
					<span class="dashicons dashicons-update-alt wpcalibrate-csm-f-icon"></span>
					<h4><?php esc_html_e( 'Asynchronous Change Detection', 'wpcalibrate-catalog-sync-meta' ); ?></h4>
					<p><?php esc_html_e( 'Hooks product publish, price change, stock update, variation edit, and trashing events. Changes are buffered instantly without blocking HTTP requests during checkout or editing.', 'wpcalibrate-catalog-sync-meta' ); ?></p>
				</div>
				<div class="wpcalibrate-csm-feature-box">
					<span class="dashicons dashicons-networking wpcalibrate-csm-f-icon"></span>
					<h4><?php esc_html_e( 'Action Scheduler Queue Coalescing', 'wpcalibrate-catalog-sync-meta' ); ?></h4>
					<p><?php esc_html_e( 'Automatically coalesces repeated edits to the same SKU and chunks updates into configurable batches (5–250 items), preventing server resource spikes during bulk imports.', 'wpcalibrate-catalog-sync-meta' ); ?></p>
				</div>
				<div class="wpcalibrate-csm-feature-box">
					<span class="dashicons dashicons-shield-alt wpcalibrate-csm-f-icon"></span>
					<h4><?php esc_html_e( 'Rate Limit & Throttling Defense', 'wpcalibrate-catalog-sync-meta' ); ?></h4>
					<p><?php esc_html_e( 'Dynamically inspects Meta Graph API rate-limit headers (x-business-use-case-usage) and employs exponential backoff retry algorithms to avoid catalog throttling.', 'wpcalibrate-catalog-sync-meta' ); ?></p>
				</div>
				<div class="wpcalibrate-csm-feature-box">
					<span class="dashicons dashicons-database wpcalibrate-csm-f-icon"></span>
					<h4><?php esc_html_e( 'Zero Postmeta Bloat', 'wpcalibrate-catalog-sync-meta' ); ?></h4>
					<p><?php esc_html_e( 'High-volume synchronization events and error logs are stored in a dedicated indexed custom table rather than postmeta, keeping your database lean and fast.', 'wpcalibrate-catalog-sync-meta' ); ?></p>
				</div>
			</div>
		</div>
	</div>

	<!-- Section 2: Methods Explained (Direct API vs XML Feed) -->
	<div class="wpcalibrate-csm-overview-card">
		<div class="wpcalibrate-csm-card-icon-header">
			<span class="dashicons dashicons-randomize wpcalibrate-csm-icon-header"></span>
			<div>
				<h3><?php esc_html_e( 'Dual Synchronization Methods Explained', 'wpcalibrate-catalog-sync-meta' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Choose the best method for your store, or run both simultaneously for maximum flexibility.', 'wpcalibrate-catalog-sync-meta' ); ?></p>
			</div>
		</div>

		<div class="wpcalibrate-csm-methods-grid">
			<!-- Method 1 -->
			<div class="wpcalibrate-csm-method-card wpcalibrate-csm-method-card-primary">
				<div class="wpcalibrate-csm-method-header">
					<span class="wpcalibrate-csm-method-badge"><?php esc_html_e( 'Method A (Recommended)', 'wpcalibrate-catalog-sync-meta' ); ?></span>
					<h4><?php esc_html_e( 'Direct Meta Graph API Batch Sync', 'wpcalibrate-catalog-sync-meta' ); ?></h4>
				</div>
				<p class="wpcalibrate-csm-method-desc">
					<?php esc_html_e( 'Sends batch payloads directly to Meta Graph API v21.0 (/{catalog_id}/items_batch) in the background via Action Scheduler.', 'wpcalibrate-catalog-sync-meta' ); ?>
				</p>
				<ul class="wpcalibrate-csm-method-checklist">
					<li><strong><?php esc_html_e( 'Latency:', 'wpcalibrate-catalog-sync-meta' ); ?></strong> <?php esc_html_e( 'Near real-time (sub-minute updates on product save or stock change).', 'wpcalibrate-catalog-sync-meta' ); ?></li>
					<li><strong><?php esc_html_e( 'Operations:', 'wpcalibrate-catalog-sync-meta' ); ?></strong> <?php esc_html_e( 'Supports CREATE, UPDATE, and remote DELETE when products are trashed.', 'wpcalibrate-catalog-sync-meta' ); ?></li>
					<li><strong><?php esc_html_e( 'Requirements:', 'wpcalibrate-catalog-sync-meta' ); ?></strong> <?php esc_html_e( 'Meta Catalog ID and System User Access Token.', 'wpcalibrate-catalog-sync-meta' ); ?></li>
					<li><strong><?php esc_html_e( 'Best For:', 'wpcalibrate-catalog-sync-meta' ); ?></strong> <?php esc_html_e( 'Dynamic Product Ads, real-time stock sync, active Facebook/Instagram Shopping.', 'wpcalibrate-catalog-sync-meta' ); ?></li>
				</ul>
				<a href="<?php echo esc_url( $connection_url ); ?>" class="button button-primary">
					<?php esc_html_e( 'Configure Direct API Connection', 'wpcalibrate-catalog-sync-meta' ); ?> &rarr;
				</a>
			</div>

			<!-- Method 2 -->
			<div class="wpcalibrate-csm-method-card">
				<div class="wpcalibrate-csm-method-header">
					<span class="wpcalibrate-csm-method-badge wpcalibrate-csm-method-badge-secondary"><?php esc_html_e( 'Method B', 'wpcalibrate-catalog-sync-meta' ); ?></span>
					<h4><?php esc_html_e( 'Streaming XML RSS 2.0 Feed', 'wpcalibrate-catalog-sync-meta' ); ?></h4>
				</div>
				<p class="wpcalibrate-csm-method-desc">
					<?php esc_html_e( 'Generates a Google Shopping / Meta RSS 2.0 compliant XML feed URL that Meta Commerce Manager fetches on a scheduled timetable.', 'wpcalibrate-catalog-sync-meta' ); ?>
				</p>
				<ul class="wpcalibrate-csm-method-checklist">
					<li><strong><?php esc_html_e( 'Latency:', 'wpcalibrate-catalog-sync-meta' ); ?></strong> <?php esc_html_e( 'Scheduled (hourly, daily, or weekly according to Meta Commerce Manager pull schedule).', 'wpcalibrate-catalog-sync-meta' ); ?></li>
					<li><strong><?php esc_html_e( 'Performance:', 'wpcalibrate-catalog-sync-meta' ); ?></strong> <?php esc_html_e( 'Memory-bounded chunked streaming. Easily handles 50k+ items without timeouts.', 'wpcalibrate-catalog-sync-meta' ); ?></li>
					<li><strong><?php esc_html_e( 'Security:', 'wpcalibrate-catalog-sync-meta' ); ?></strong> <?php esc_html_e( 'Protected by a secure random 32-character token parameter in the feed URL.', 'wpcalibrate-catalog-sync-meta' ); ?></li>
					<li><strong><?php esc_html_e( 'Best For:', 'wpcalibrate-catalog-sync-meta' ); ?></strong> <?php esc_html_e( 'Backup sync, stores without direct Meta API access, or scheduled batch updates.', 'wpcalibrate-catalog-sync-meta' ); ?></li>
				</ul>
				<a href="<?php echo esc_url( $feed_url ); ?>" class="button button-secondary">
					<?php esc_html_e( 'View XML Feed URL & Settings', 'wpcalibrate-catalog-sync-meta' ); ?> &rarr;
				</a>
			</div>
		</div>
	</div>

	<!-- Section 3: How to Use (Step by Step) -->
	<div class="wpcalibrate-csm-overview-card">
		<div class="wpcalibrate-csm-card-icon-header">
			<span class="dashicons dashicons-list-view wpcalibrate-csm-icon-header"></span>
			<div>
				<h3><?php esc_html_e( 'How to Use WPCalibrate Catalog Sync for Meta (5-Step Guide)', 'wpcalibrate-catalog-sync-meta' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Follow these straightforward steps to connect and synchronize your catalog.', 'wpcalibrate-catalog-sync-meta' ); ?></p>
			</div>
		</div>

		<div class="wpcalibrate-csm-steps-list">
			<!-- Step 1 -->
			<div class="wpcalibrate-csm-step-item">
				<div class="wpcalibrate-csm-step-badge">1</div>
				<div class="wpcalibrate-csm-step-details">
					<h4><?php esc_html_e( 'Connect Your Meta Catalog', 'wpcalibrate-catalog-sync-meta' ); ?></h4>
					<p>
						<?php
						printf(
							/* translators: %s: Connection tab link */
							esc_html__( 'Navigate to the %s tab. Enter your Meta Catalog ID (found in Meta Commerce Manager) and your System User Access Token (generated in Meta Business Settings with catalog_management and business_management permissions). Click "Test Connection" to confirm live connectivity.', 'wpcalibrate-catalog-sync-meta' ),
							'<a href="' . esc_url( $connection_url ) . '"><strong>' . esc_html__( 'Meta Connection', 'wpcalibrate-catalog-sync-meta' ) . '</strong></a>'
						);
						?>
					</p>
				</div>
			</div>

			<!-- Step 2 -->
			<div class="wpcalibrate-csm-step-item">
				<div class="wpcalibrate-csm-step-badge">2</div>
				<div class="wpcalibrate-csm-step-details">
					<h4><?php esc_html_e( 'Configure Product Eligibility & Filter Rules', 'wpcalibrate-catalog-sync-meta' ); ?></h4>
					<p>
						<?php
						printf(
							/* translators: %s: Rules tab link */
							esc_html__( 'Open the %s tab to decide which products belong in Meta. Choose supported product types (Simple, Variable), toggle whether out-of-stock items should be included, and select specific categories to include or exclude.', 'wpcalibrate-catalog-sync-meta' ),
							'<a href="' . esc_url( $rules_url ) . '"><strong>' . esc_html__( 'Product Rules', 'wpcalibrate-catalog-sync-meta' ) . '</strong></a>'
						);
						?>
					</p>
				</div>
			</div>

			<!-- Step 3 -->
			<div class="wpcalibrate-csm-step-item">
				<div class="wpcalibrate-csm-step-badge">3</div>
				<div class="wpcalibrate-csm-step-details">
					<h4><?php esc_html_e( 'Verify Field & Attribute Mapping', 'wpcalibrate-catalog-sync-meta' ); ?></h4>
					<p>
						<?php
						printf(
							/* translators: %s: Mapping tab link */
							esc_html__( 'Open the %s tab to match WooCommerce product attributes with Meta catalog requirements. Map your store\'s Brand, GTIN / Barcode, Manufacturer Part Number (MPN), and Item Condition (New, Refurbished, Used).', 'wpcalibrate-catalog-sync-meta' ),
							'<a href="' . esc_url( $mapping_url ) . '"><strong>' . esc_html__( 'Field Mapping', 'wpcalibrate-catalog-sync-meta' ) . '</strong></a>'
						);
						?>
					</p>
				</div>
			</div>

			<!-- Step 4 -->
			<div class="wpcalibrate-csm-step-item">
				<div class="wpcalibrate-csm-step-badge">4</div>
				<div class="wpcalibrate-csm-step-details">
					<h4><?php esc_html_e( 'Perform Initial Full Catalog Reconciliation', 'wpcalibrate-catalog-sync-meta' ); ?></h4>
					<p>
						<?php
						printf(
							/* translators: %s: Dashboard tab link */
							esc_html__( 'Go to the %s tab and click "Full Reconciliation". This triggers a resumable background scan across your entire product database, verifying eligibility and enqueueing batch jobs to populate your Meta catalog.', 'wpcalibrate-catalog-sync-meta' ),
							'<a href="' . esc_url( $dashboard_url ) . '"><strong>' . esc_html__( 'Dashboard', 'wpcalibrate-catalog-sync-meta' ) . '</strong></a>'
						);
						?>
					</p>
				</div>
			</div>

			<!-- Step 5 -->
			<div class="wpcalibrate-csm-step-item">
				<div class="wpcalibrate-csm-step-badge">5</div>
				<div class="wpcalibrate-csm-step-details">
					<h4><?php esc_html_e( 'Monitor Live Health & Automatic Synchronization', 'wpcalibrate-catalog-sync-meta' ); ?></h4>
					<p>
						<?php esc_html_e( 'From this point forward, the plugin operates automatically! Whenever an eligible product is created, edited, sold, or trashed in WooCommerce, the change detector schedules an incremental background sync. Monitor sync events and health directly from the Dashboard and Logs tabs.', 'wpcalibrate-catalog-sync-meta' ); ?>
					</p>
				</div>
			</div>
		</div>
	</div>

	<!-- Section 4: Eligibility Precedence Engine -->
	<div class="wpcalibrate-csm-overview-card">
		<div class="wpcalibrate-csm-card-icon-header">
			<span class="dashicons dashicons-yes wpcalibrate-csm-icon-header"></span>
			<div>
				<h3><?php esc_html_e( 'Deterministic 5-Step Product Eligibility Precedence', 'wpcalibrate-catalog-sync-meta' ); ?></h3>
				<p class="description"><?php esc_html_e( 'How the engine evaluates whether a product is synced to Meta.', 'wpcalibrate-catalog-sync-meta' ); ?></p>
			</div>
		</div>
		<div class="wpcalibrate-csm-precedence-flow">
			<div class="wpcalibrate-csm-p-step">
				<div class="wpcalibrate-csm-p-num">1</div>
				<div class="wpcalibrate-csm-p-info">
					<strong><?php esc_html_e( 'Explicit Exclusion Override', 'wpcalibrate-catalog-sync-meta' ); ?></strong>
					<span><?php esc_html_e( 'If "Exclude from Meta Catalog" is checked in the product metabox, product is immediately excluded.', 'wpcalibrate-catalog-sync-meta' ); ?></span>
				</div>
			</div>
			<div class="wpcalibrate-csm-p-step">
				<div class="wpcalibrate-csm-p-num">2</div>
				<div class="wpcalibrate-csm-p-info">
					<strong><?php esc_html_e( 'Publish Status Check', 'wpcalibrate-catalog-sync-meta' ); ?></strong>
					<span><?php esc_html_e( 'Draft, pending, private, and trashed products are never synced remotely.', 'wpcalibrate-catalog-sync-meta' ); ?></span>
				</div>
			</div>
			<div class="wpcalibrate-csm-p-step">
				<div class="wpcalibrate-csm-p-num">3</div>
				<div class="wpcalibrate-csm-p-info">
					<strong><?php esc_html_e( 'Product Rules Tab', 'wpcalibrate-catalog-sync-meta' ); ?></strong>
					<span><?php esc_html_e( 'Must match enabled product types (Simple, Variable), stock status requirements, and catalog visibility.', 'wpcalibrate-catalog-sync-meta' ); ?></span>
				</div>
			</div>
			<div class="wpcalibrate-csm-p-step">
				<div class="wpcalibrate-csm-p-num">4</div>
				<div class="wpcalibrate-csm-p-info">
					<strong><?php esc_html_e( 'Category Inclusion / Exclusion', 'wpcalibrate-catalog-sync-meta' ); ?></strong>
					<span><?php esc_html_e( 'Must belong to included categories (if specified) and must NOT belong to any excluded categories.', 'wpcalibrate-catalog-sync-meta' ); ?></span>
				</div>
			</div>
			<div class="wpcalibrate-csm-p-step wpcalibrate-csm-p-step-final">
				<div class="wpcalibrate-csm-p-num"><span class="dashicons dashicons-yes"></span></div>
				<div class="wpcalibrate-csm-p-info">
					<strong><?php esc_html_e( 'Eligible for Synchronization', 'wpcalibrate-catalog-sync-meta' ); ?></strong>
					<span><?php esc_html_e( 'Transformed into Graph API batch item or included in the streaming XML RSS feed.', 'wpcalibrate-catalog-sync-meta' ); ?></span>
				</div>
			</div>
		</div>
	</div>

</div>
