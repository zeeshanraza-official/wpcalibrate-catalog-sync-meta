# WPCalibrate Catalog Sync for Meta

[![WordPress Compatibility](https://img.shields.io/badge/WordPress-6.5%2B-blue.svg)](https://wordpress.org)
[![WooCommerce Compatibility](https://img.shields.io/badge/WooCommerce-8.5%2B%20%7C%20HPOS-96588a.svg)](https://woocommerce.com)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%20%7C%208.3-777bb4.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-green.svg)](license.txt)
[![Latest Release](https://img.shields.io/badge/Version-1.0.0-orange.svg)](https://github.com/zeeshanraza-official/wpcalibrate-catalog-sync-meta/releases)

**WPCalibrate Catalog Sync for Meta** is an enterprise-grade WooCommerce plugin that synchronizes your store inventory with **Meta (Facebook & Instagram) Product Catalogs**. Designed for high-velocity stores and large catalogs (up to 50,000+ items), it eliminates checkout slowdowns, memory spikes, and gateway timeouts through non-blocking asynchronous change detection, Action Scheduler batch coalescing, and streaming XML feeds.

---

## Table of Contents
1. [About](#about)
2. [Key Features](#key-features)
3. [Architecture & Synchronization Methods](#architecture--synchronization-methods)
4. [System Requirements](#system-requirements)
5. [Installation](#installation)
6. [5-Step Guided Setup Workflow](#5-step-guided-setup-workflow)
7. [Developer Documentation & Hooks](#developer-documentation--hooks)
8. [Changelog](#changelog)
9. [Support & Resources](#support--resources)
10. [Trademark Disclaimer](#trademark-disclaimer)

---

## About

Meta (Facebook & Instagram) is one of the highest-converting commerce and advertising platforms in the world. To showcase your products in **Instagram Shopping**, **Facebook Shops**, and run high-return **Dynamic Product Ads (DPA)** or **Advantage+ Catalog Ads**, your catalog must accurately reflect real-time stock status, current prices, and exact product variations.

Traditional catalog sync plugins make blocking external HTTP requests during product saves or checkout, or generate entire catalog files in single monolithic requests that crash PHP with `504 Gateway Timeout` or `Allowed memory size exhausted` errors.

**WPCalibrate Catalog Sync for Meta** is engineered from the ground up for modern WordPress (WP 6.5–7.1.3+), WooCommerce (8.5–11.1.2+), and PHP 8.2/8.3:
* **Zero Checkout Slowdown:** Product updates and stock decrements are queued in the background using WooCommerce's native **Action Scheduler**.
* **Zero Postmeta Bloat:** Audit events, retry states, and remote sync responses are stored in a dedicated indexed custom database table (`wpcalibrate_csm_sync_events`).
* **Meta Rate Limit Protection:** Inspects `x-business-use-case-usage` HTTP headers on every batch to prevent catalog API throttling.

---

## Key Features

### 🛒 Meta Commerce & Dynamic Ads Integration
* **Facebook & Instagram Shop Ready:** Syncs titles, descriptions, pricing, sale prices, stock status, availability, category paths, and primary/gallery images.
* **Advantage+ & Dynamic Product Ads (DPA):** Keeps ads updated with real-time pricing and out-of-stock exclusions.
* **Deterministic Identifier Strategy:** Stable retailer IDs (`wc_{id}`, `wc_var_{id}`, and group ID `wc_{parent_id}`) preserve pixel event matching and ad history across domain changes and store migrations.
* **Variation Support:** Seamlessly groups variations under parent product items with mapped attributes (size, color, material, etc.).

### ⚡ Enterprise Performance & Architecture
* **Non-Blocking Asynchronous Processing:** Listens to product saves, stock transitions, variation updates, and trashed items without delaying admin page loads or shopper checkouts.
* **Queue Coalescing & Batch Chunking:** Debounces repeated edits and slices payloads into configurable batches (5–250 items per payload) with transient execution locks (`wpcalibrate_csm_batch_lock`).
* **High-Volume Custom Table Storage:** All event logs, error messages, and payload audits are isolated in `{$wpdb->prefix}wpcalibrate_csm_sync_events`, preventing `wp_postmeta` fragmentation.
* **Automatic Secret Redaction:** Automatically masks Meta System User Tokens, App Secrets, and license keys in logs, system reports, and diagnostics exports.

---

## Architecture & Synchronization Methods

The plugin provides a dual-channel synchronization architecture:

```
┌────────────────────────────────────────────────────────┐
│               WooCommerce Store Catalog                │
└───────────────────────────┬────────────────────────────┘
                            │
               [Eligibility Engine: 5 Steps]
                            │
            ┌───────────────┴───────────────┐
            ▼                               ▼
    [Method A: Direct API]          [Method B: XML Feed]
  - Action Scheduler Queued       - Chunked RSS 2.0 / Google
  - Graph API v21.0 Batch         - Secure Token Protected
  - Sub-Minute Real-Time Push     - Scheduled Meta Pull
```

### Method A: Direct Meta Graph API Batch Sync (Recommended)
* **Endpoint:** `POST /{catalog_id}/items_batch` (Graph API v21.0)
* **Latency:** Sub-minute (near real-time)
* **Operations:** Sends atomic `CREATE`, `UPDATE`, and remote `DELETE` operations
* **Best For:** Live storefronts, active dynamic retargeting, and flash sales requiring instant stock accuracy.

### Method B: Streaming XML RSS 2.0 Feed
* **Endpoint:** `https://yourdomain.com/?wpcalibrate_csm_feed=xml&token={secure_token}`
* **Standard:** Google Shopping / Meta RSS 2.0 compliant
* **Performance:** Memory-bounded paginated streaming (handles 50,000+ items without memory spikes)
* **Best For:** Scheduled hourly/daily ingestion via Meta Commerce Manager Data Feeds, or stores operating without direct API app credentials.

---

## System Requirements

* **WordPress:** 6.5 or higher (Tested up to 7.1.3)
* **WooCommerce:** 8.5 or higher (Tested up to 11.1.2)
* **PHP:** 8.2 or 8.3 (Strict types, modern PSR-4 autoloading)
* **WooCommerce HPOS:** Compatible with High-Performance Order Storage (`custom_order_tables`)
* **Cart & Checkout Blocks:** Fully declared compatibility (`cart_checkout_blocks`)
* **Meta Business Manager:** Active Catalog in Meta Commerce Manager

---

## Installation

### Manual Installation via WordPress Dashboard
1. Download the latest release ZIP (`wpcalibrate-catalog-sync-meta-1.0.0.zip`) from the [GitHub Releases](https://github.com/zeeshanraza-official/wpcalibrate-catalog-sync-meta/releases) page.
2. In your WordPress admin, navigate to **Plugins > Add New > Upload Plugin**.
3. Choose the ZIP file and click **Install Now**.
4. Click **Activate Plugin**.

### Automated In-Dashboard Updates from GitHub
Because this repository is hosted publicly on GitHub, you can use tools like **Plugin Update Checker** or **GitHub Updater** to receive automatic update notifications and perform one-click updates directly from your WordPress admin dashboard!

---

## 5-Step Guided Setup Workflow

1. **Connect to Meta:**
   - In WordPress admin, go to **WPCalibrate > Catalog Sync for Meta > Meta Connection**.
   - Enter your **Catalog ID** and a **System User Access Token** generated in Meta Business Settings with `catalog_management` and `business_management` permissions.
   - Click **Test Connection** to confirm live API access.
2. **Configure Product Rules:**
   - Open the **Product Rules** tab.
   - Choose eligible product types (Simple, Variable).
   - Set stock status requirements and include or exclude specific product categories.
3. **Map Attributes & Taxonomies:**
   - Open the **Field Mapping** tab.
   - Map Brand, GTIN / Barcode, MPN, and Item Condition to your store's custom fields or product taxonomies.
4. **Trigger Initial Full Reconciliation:**
   - Go to the **Dashboard** tab.
   - Click **Full Reconciliation** to launch a resumable background audit that syncs your entire catalog.
5. **Monitor Live Status & Diagnostics:**
   - Monitor live synchronization counters on the **Dashboard**.
   - Check real-time audit logs in the **Logs** tab or export system health reports in the **Diagnostics** tab.

---

## Developer Documentation & Hooks

### Public Filter Hooks
* `wpcalibrate_csm_product_is_eligible` (`bool $is_eligible, WC_Product $product`): Customize whether a product is eligible for Meta synchronization.
* `wpcalibrate_csm_field_mapping_payload` (`array $payload, WC_Product $product`): Modify or extend the mapped catalog payload before dispatch.
* `wpcalibrate_csm_batch_size` (`int $batch_size`): Dynamically filter the Action Scheduler batch size (range: 5–250).

### Public Action Hooks
* `wpcalibrate_csm_before_batch_dispatch` (`array $items, string $catalog_id`): Fires immediately before a batch payload is sent to Meta.
* `wpcalibrate_csm_after_batch_dispatch` (`array $response, array $items`): Fires after receiving Meta's batch response.

---

## Changelog

### 1.0.0 (2026-10-08)
* **Initial Release:** Complete commercial catalog synchronization suite for WooCommerce & Meta.
* **Direct Graph API v21.0 Batch Sync:** Non-blocking Action Scheduler batch processing with transient execution locking.
* **Streaming XML Feed:** Google Shopping / Meta RSS 2.0 compliant chunked XML endpoint with token security.
* **Deterministic 5-Step Eligibility Engine:** Strict precedence rules for product exclusions, stock status, and category filtering.
* **High-Volume Event Repository:** Dedicated custom table (`wpcalibrate_csm_sync_events`) with retention pruning.
* **Admin Overview Hub:** Comprehensive onboarding guide detailing purpose, dual sync methods, and a 5-step guided setup.
* **Global Brand Styling:** Enforced 20x20px menu icon constraints in `admin_head` with dynamic dark icon swap for light admin themes.
* **HPOS & Blocks:** Fully declared compatibility with WooCommerce High-Performance Order Storage and Cart/Checkout Blocks.

---

## Support & Resources

* **Author:** WPCalibrate
* **Support Email:** support@wpcalibrate.com
* **Official Website:** [https://wpcalibrate.com](https://wpcalibrate.com)
* **Marketplace:** [https://marketplace.wpcalibrate.com/](https://marketplace.wpcalibrate.com/)
* **WhatsApp / Phone:** +447474795976

---

## Trademark Disclaimer

*Meta* and *Facebook* are registered trademarks of Meta Platforms, Inc. WPCalibrate Catalog Sync for Meta is an independent commercial WordPress plugin developed by WPCalibrate and is not officially affiliated with, endorsed by, or sponsored by Meta Platforms, Inc.
