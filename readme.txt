=== WPCalibrate Catalog Sync for Meta ===
Contributors: wpcalibrate
Tags: woocommerce, facebook, meta, catalog sync, instagram shop, product feed, commerce manager
Requires at least: 6.5
Tested up to: 7.1.3
Requires PHP: 8.2
Stable tag: 1.0.0
License: Commercial
License URI: https://marketplace.wpcalibrate.com/license

Enterprise-grade catalog synchronization between WooCommerce and Meta Product Catalogs with background batching, incremental updates, and XML feed.

== Description ==

WPCalibrate Catalog Sync for Meta provides reliable, server-friendly catalog synchronization between your WooCommerce store and Meta/Facebook Product Catalogs. Built for modern high-volume eCommerce, the plugin synchronizes simple products, variable parent products, and individual variations without slowing down your server or frontend shopping experience.

= Key Features =

* **Official Meta API Batch Sync:** Direct integration with Meta Graph API v21.0 using the `items_batch` endpoint for fast, batched product updates and deletions.
* **Streamed XML Product Feed:** Memory-bounded, Meta/Google Shopping compliant XML feed with secure token protection. Use it as a secondary data source or scheduled fallback in Meta Commerce Manager.
* **Deterministic Identifiers:** Stable, collision-free product and variation identifier strategy (`wc_123` / `wc_var_456`) that remains invariant across product edits and price updates.
* **Action Scheduler Background Queue:** Changes are coalesced and queued asynchronously in the background. Normal WooCommerce product saves and orders are never delayed by remote HTTP requests.
* **Intelligent Incremental Updates:** Automatically detects price changes, inventory updates, attribute edits, and trashed items, queuing only modified products.
* **Full Catalog Reconciliation:** Resumable, paginated audit scans that catch missed hooks, remove out-of-sync products, and recover from failures without overloading memory.
* **Flexible Field & Attribute Mapping:** Map custom post meta, global attributes, taxonomies, and core product properties to Meta attributes including Brand, GTIN/UPC/EAN, MPN, and Condition.
* **Data Integrity Guarantee:** Never fabricates fake barcodes, GTINs, MPNs, or brands. Missing mapped values are omitted cleanly rather than populated with invalid placeholders.
* **Granular Product Rules:** Exclude specific products, categories, tags, out-of-stock items, or hidden catalog items with strict, predictable rule precedence.
* **Per-Product Status Inspector:** Monitor catalog sync status, retailer IDs, last sync timestamps, and view sanitized errors directly inside WooCommerce product edit screens.
* **Throttling & Concurrency Protection:** Configurable batch sizes (5–250), delay throttling (0–5000ms), and concurrency locks prevent duplicate runs and respect Meta API rate limits.
* **Zero Credential Exposure:** Strict automated redaction guarantees that access tokens, app secrets, license keys, and authorization headers are never exposed in logs or diagnostics reports.
* **WooCommerce HPOS Compatible:** Fully declared and tested with High-Performance Order Storage (HPOS) and Cart/Checkout Blocks.

= What Is Out of Scope in V1 =

To maintain rock-solid catalog reliability and zero server bloat, the following unrelated features are intentionally excluded from V1:
* Meta Pixel & Conversion API (CAPI) tracking
* Ad campaign and audience management
* Messenger & WhatsApp customer chat
* WooCommerce order and customer synchronization

= Legal & Trademark Disclaimer =

Meta and Facebook are registered trademarks of Meta Platforms, Inc. WPCalibrate Catalog Sync for Meta is an independent commercial WordPress extension developed by WPCalibrate and is not officially affiliated with, authorized, maintained, sponsored, or endorsed by Meta Platforms, Inc.

== Installation ==

1. Upload the `wpcalibrate-catalog-sync-meta` plugin folder to your WordPress `/wp-content/plugins/` directory, or install via **Plugins > Add New > Upload Plugin**.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Verify that WooCommerce is installed and active.
4. Navigate to **WPCalibrate > Catalog Sync for Meta** in your WordPress admin menu.
5. Configure your Meta connection under the **Meta Connection** tab:
   - Provide your Meta App ID, App Secret, and Business Manager credentials, or
   - Enter your Meta Business Manager System User Access Token and Product Catalog ID.
6. Review your product eligibility rules under the **Product Rules** tab.
7. Configure attribute mapping under the **Field Mapping** tab.
8. Click **Sync Changed Products** or **Full Catalog Sync** to begin synchronization.

== External Configuration Dependencies ==

* **Meta OAuth Application:** Direct 1-click OAuth authorization requires a registered Meta App on developers.facebook.com with approved `catalog_management` and `business_management` permissions. While external OAuth credentials are being configured, administrators can connect immediately using a standard Meta Business Manager System User Access Token.
* **WPCalibrate Licensing Server:** Commercial license key validation depends on the official WPCalibrate licensing server. The licensing interface safely reports when external licensing endpoints are not configured without simulating false activation.

== Frequently Asked Questions ==

= Does this plugin slow down product saving in WooCommerce? =
No. The plugin never performs blocking remote Meta API calls during normal WooCommerce product saves. Product edits enqueue lightweight jobs into Action Scheduler that execute in the background.

= How are variable products handled? =
Variable products are handled according to Meta Catalog standards. Each eligible variation is created as an individual catalog item with a stable identifier (`wc_var_{id}`) linked to the parent product group (`wc_{parent_id}`) via `item_group_id`.

= What happens when a product is deleted or trashed in WooCommerce? =
When a product is trashed or deleted, the plugin detects the removal and sends a remote `DELETE` batch request to Meta to ensure your Facebook and Instagram catalog remains synchronized with your store inventory.

= What are the requirements for Action Scheduler and WP-Cron? =
The plugin uses WooCommerce Action Scheduler for background processing. For optimal performance on high-traffic stores, we recommend disabling standard WP-Cron in `wp-config.php` (`define('DISABLE_WP_CRON', true);`) and configuring a system server cron job to run `wp-cron.php` every minute.

= What data is deleted when the plugin is uninstalled? =
By default, all plugin settings and sync history are preserved if you deactivate or delete the plugin. If you check **Delete all plugin data when uninstalled** under the Logs tab before deletion, all plugin options, custom event tables, product metadata, and scheduled jobs are completely removed. WooCommerce products, orders, categories, and customer data are never deleted.

== Changelog ==

= 1.0.0 =
* Initial production release.
* Direct Meta Graph API v21.0 batch synchronization.
* Streamed, token-protected Meta XML feed.
* Incremental change detection and full catalog reconciliation.
* Granular category and product eligibility engine.
* Per-product status panel in WooCommerce product editor.
* HPOS and Cart/Checkout Blocks compatibility.
* Sanitized diagnostics reporter with automatic credential redaction.
