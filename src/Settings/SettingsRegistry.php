<?php
/**
 * Settings Registry and Configuration Manager.
 *
 * @package WPCalibrate\CatalogSyncMeta
 */

namespace WPCalibrate\CatalogSyncMeta\Settings;

use WPCalibrate\CatalogSyncMeta\Security\SecurityHelper;

defined( 'ABSPATH' ) || exit;

/**
 * Class SettingsRegistry
 */
class SettingsRegistry {

	/**
	 * Option name stored in wp_options.
	 *
	 * @var string
	 */
	public const OPTION_NAME = 'wpcalibrate_csm_settings';

	/**
	 * Schema version for settings.
	 *
	 * @var string
	 */
	public const SETTINGS_VERSION = '1.0.0';

	/**
	 * Cached settings.
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $cached_settings = null;

	/**
	 * Get default settings values.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_defaults(): array {
		return array(
			// Meta Connection.
			'meta_app_id'               => '',
			'meta_app_secret'           => '',
			'meta_access_token'         => '',
			'meta_business_id'          => '',
			'meta_catalog_id'           => '',
			'meta_connection_status'    => 'not_configured',
			'meta_last_connection_check'=> 0,

			// Synchronization Controls.
			'enable_auto_sync'          => true,
			'is_sync_paused'            => false,
			'batch_size'                => 50,
			'throttle_delay_ms'         => 100,
			'max_retries'               => 3,
			'reconciliation_interval'   => 'daily',
			'last_reconciliation_time'  => 0,
			'last_incremental_sync'     => 0,

			// Product Eligibility Rules.
			'sync_simple_products'      => true,
			'sync_variable_products'    => true,
			'sync_variations'           => true,
			'exclude_out_of_stock'      => false,
			'exclude_hidden_catalog'    => true,
			'exclude_without_price'     => true,
			'exclude_without_image'     => false,
			'excluded_categories'       => array(),
			'included_categories'       => array(),
			'excluded_tags'             => array(),

			// Field Mapping.
			'identifier_type'           => 'product_id', // 'product_id' or 'sku'.
			'brand_source'              => 'attribute',  // 'attribute', 'custom_meta', 'static'.
			'brand_field'               => 'pa_brand',
			'gtin_source'               => 'custom_meta',
			'gtin_field'                => '_gtin',
			'mpn_source'                => 'sku',
			'mpn_field'                 => '',
			'condition_source'          => 'static',
			'condition_default'         => 'new',
			'condition_field'           => '',
			'description_source'        => 'full_description', // 'full_description', 'short_description', 'both'.
			'category_source'           => 'woocommerce_category',

			// XML Feed.
			'enable_feed'               => true,
			'feed_token'                => wp_generate_password( 32, false ),
			'feed_cache_ttl'            => 3600,
			'feed_last_generated'       => 0,
			'feed_item_count'           => 0,

			// Logging and Retention.
			'log_level'                 => 'info', // 'error', 'info', 'debug'.
			'data_retention_days'       => 30,

			// Lifecycle / Data Removal.
			'delete_data_on_uninstall'  => false,

			// Licensing.
			'license_key'               => '',
			'license_status'            => 'unconfigured',
			'license_expiry'            => '',
			'license_last_check'        => 0,
		);
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @param bool $refresh Force refresh from database.
	 * @return array<string, mixed>
	 */
	public static function get_all( bool $refresh = false ): array {
		if ( null !== self::$cached_settings && ! $refresh ) {
			return self::$cached_settings;
		}

		$stored = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$defaults = self::get_defaults();
		self::$cached_settings = wp_parse_args( $stored, $defaults );

		return self::$cached_settings;
	}

	/**
	 * Get a single setting value.
	 *
	 * @param string $key     Setting key name.
	 * @param mixed  $default Optional default if not found.
	 * @return mixed
	 */
	public static function get( string $key, mixed $default = null ): mixed {
		$all = self::get_all();
		if ( array_key_exists( $key, $all ) ) {
			return $all[ $key ];
		}
		return $default ?? ( self::get_defaults()[ $key ] ?? null );
	}

	/**
	 * Update settings.
	 *
	 * @param array<string, mixed> $new_values New settings array.
	 * @return bool
	 */
	public static function update_all( array $new_values ): bool {
		$current = self::get_all( true );
		$sanitized = self::sanitize_settings( wp_parse_args( $new_values, $current ) );

		self::$cached_settings = $sanitized;
		return update_option( self::OPTION_NAME, $sanitized, false );
	}

	/**
	 * Update a single setting value.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value New value.
	 * @return bool
	 */
	public static function set( string $key, mixed $value ): bool {
		$all = self::get_all( true );
		$all[ $key ] = $value;
		return self::update_all( $all );
	}

	/**
	 * Sanitize all settings entries according to their expected types and constraints.
	 *
	 * @param array<string, mixed> $input Raw input settings.
	 * @return array<string, mixed> Sanitized settings.
	 */
	public static function sanitize_settings( array $input ): array {
		$defaults = self::get_defaults();
		$clean    = array();

		// Meta Connection fields.
		$clean['meta_app_id']            = sanitize_text_field( $input['meta_app_id'] ?? '' );
		$clean['meta_app_secret']        = sanitize_text_field( $input['meta_app_secret'] ?? '' );
		$clean['meta_access_token']      = sanitize_text_field( $input['meta_access_token'] ?? '' );
		$clean['meta_business_id']       = sanitize_text_field( $input['meta_business_id'] ?? '' );
		$clean['meta_catalog_id']        = sanitize_text_field( $input['meta_catalog_id'] ?? '' );
		$clean['meta_connection_status'] = sanitize_key( $input['meta_connection_status'] ?? 'not_configured' );
		$clean['meta_last_connection_check'] = absint( $input['meta_last_connection_check'] ?? 0 );

		// Sync controls.
		$clean['enable_auto_sync']         = ! empty( $input['enable_auto_sync'] );
		$clean['is_sync_paused']           = ! empty( $input['is_sync_paused'] );
		$batch_size                        = absint( $input['batch_size'] ?? 50 );
		$clean['batch_size']               = max( 5, min( 250, $batch_size ) );
		$throttle                          = absint( $input['throttle_delay_ms'] ?? 100 );
		$clean['throttle_delay_ms']        = max( 0, min( 5000, $throttle ) );
		$retries                           = absint( $input['max_retries'] ?? 3 );
		$clean['max_retries']              = max( 1, min( 10, $retries ) );
		$valid_intervals                   = array( 'hourly', 'twicedaily', 'daily', 'weekly' );
		$clean['reconciliation_interval']  = in_array( $input['reconciliation_interval'] ?? '', $valid_intervals, true )
			? $input['reconciliation_interval']
			: 'daily';
		$clean['last_reconciliation_time'] = absint( $input['last_reconciliation_time'] ?? 0 );
		$clean['last_incremental_sync']    = absint( $input['last_incremental_sync'] ?? 0 );

		// Product Rules.
		$clean['sync_simple_products']   = ! empty( $input['sync_simple_products'] );
		$clean['sync_variable_products'] = ! empty( $input['sync_variable_products'] );
		$clean['sync_variations']        = ! empty( $input['sync_variations'] );
		$clean['exclude_out_of_stock']   = ! empty( $input['exclude_out_of_stock'] );
		$clean['exclude_hidden_catalog'] = ! empty( $input['exclude_hidden_catalog'] );
		$clean['exclude_without_price']  = ! empty( $input['exclude_without_price'] );
		$clean['exclude_without_image']  = ! empty( $input['exclude_without_image'] );

		// Categories & Tags arrays.
		$clean['excluded_categories'] = self::sanitize_int_array( $input['excluded_categories'] ?? array() );
		$clean['included_categories'] = self::sanitize_int_array( $input['included_categories'] ?? array() );
		$clean['excluded_tags']       = self::sanitize_int_array( $input['excluded_tags'] ?? array() );

		// Field Mapping.
		$clean['identifier_type']    = in_array( $input['identifier_type'] ?? '', array( 'product_id', 'sku' ), true )
			? $input['identifier_type']
			: 'product_id';
		$clean['brand_source']       = sanitize_key( $input['brand_source'] ?? 'attribute' );
		$clean['brand_field']        = sanitize_text_field( $input['brand_field'] ?? 'pa_brand' );
		$clean['gtin_source']        = sanitize_key( $input['gtin_source'] ?? 'custom_meta' );
		$clean['gtin_field']         = sanitize_text_field( $input['gtin_field'] ?? '_gtin' );
		$clean['mpn_source']         = sanitize_key( $input['mpn_source'] ?? 'sku' );
		$clean['mpn_field']          = sanitize_text_field( $input['mpn_field'] ?? '' );
		$clean['condition_source']   = sanitize_key( $input['condition_source'] ?? 'static' );
		$clean['condition_default']  = in_array( $input['condition_default'] ?? '', array( 'new', 'refurbished', 'used' ), true )
			? $input['condition_default']
			: 'new';
		$clean['condition_field']    = sanitize_text_field( $input['condition_field'] ?? '' );
		$clean['description_source'] = in_array( $input['description_source'] ?? '', array( 'full_description', 'short_description', 'both' ), true )
			? $input['description_source']
			: 'full_description';
		$clean['category_source']    = sanitize_key( $input['category_source'] ?? 'woocommerce_category' );

		// Feed.
		$clean['enable_feed']         = ! empty( $input['enable_feed'] );
		$clean['feed_token']          = ! empty( $input['feed_token'] ) ? sanitize_text_field( $input['feed_token'] ) : ( $defaults['feed_token'] );
		$clean['feed_cache_ttl']      = max( 300, absint( $input['feed_cache_ttl'] ?? 3600 ) );
		$clean['feed_last_generated'] = absint( $input['feed_last_generated'] ?? 0 );
		$clean['feed_item_count']     = absint( $input['feed_item_count'] ?? 0 );

		// Logging & Retention.
		$clean['log_level']           = in_array( $input['log_level'] ?? '', array( 'error', 'info', 'debug' ), true )
			? $input['log_level']
			: 'info';
		$retention                    = absint( $input['data_retention_days'] ?? 30 );
		$clean['data_retention_days'] = in_array( $retention, array( 7, 14, 30, 60, 90 ), true ) ? $retention : 30;

		// Lifecycle / Uninstall.
		$clean['delete_data_on_uninstall'] = ! empty( $input['delete_data_on_uninstall'] );

		// Licensing.
		$clean['license_key']        = sanitize_text_field( $input['license_key'] ?? '' );
		$clean['license_status']     = sanitize_key( $input['license_status'] ?? 'unconfigured' );
		$clean['license_expiry']     = sanitize_text_field( $input['license_expiry'] ?? '' );
		$clean['license_last_check'] = absint( $input['license_last_check'] ?? 0 );

		return $clean;
	}

	/**
	 * Sanitize array of integers.
	 *
	 * @param mixed $raw Raw input.
	 * @return array<int>
	 */
	private static function sanitize_int_array( mixed $raw ): array {
		if ( is_string( $raw ) ) {
			$raw = explode( ',', $raw );
		}
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$integers = array_map( 'absint', $raw );
		return array_values( array_filter( $integers, fn( $v ) => $v > 0 ) );
	}
}
