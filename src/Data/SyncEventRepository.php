<?php
/**
 * Repository for Sync Events and Diagnostics.
 *
 * @package WPCalibrate\CatalogSyncMeta
 */

namespace WPCalibrate\CatalogSyncMeta\Data;

use WPCalibrate\CatalogSyncMeta\Security\SecurityHelper;

defined( 'ABSPATH' ) || exit;

/**
 * Class SyncEventRepository
 */
class SyncEventRepository {

	/**
	 * Table name without prefix.
	 *
	 * @var string
	 */
	public const TABLE_NAME = 'wpcalibrate_csm_sync_events';

	/**
	 * Get the full table name.
	 *
	 * @return string
	 */
	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Record a sync event.
	 *
	 * @param int         $product_id      Product ID.
	 * @param int         $variation_id    Variation ID (0 for simple or parent).
	 * @param string      $status          Status ('synced', 'failed', 'pending', 'skipped').
	 * @param string      $event_type      Event type ('sync', 'delete', 'reconcile').
	 * @param string|null $error_code      Error code if failed.
	 * @param string|null $error_message   Detailed error description if failed.
	 * @param array|null  $payload_summary Summary of payload sent (will be sanitized).
	 * @param int         $retry_count     Current retry count.
	 * @return int Insert ID or 0 on failure.
	 */
	public static function record_event(
		int $product_id,
		int $variation_id = 0,
		string $status = 'pending',
		string $event_type = 'sync',
		?string $error_code = null,
		?string $error_message = null,
		?array $payload_summary = null,
		int $retry_count = 0
	): int {
		global $wpdb;

		$table = self::get_table_name();

		$summary_json = null;
		if ( ! empty( $payload_summary ) ) {
			$redacted     = SecurityHelper::redact_sensitive_data( $payload_summary );
			$summary_json = wp_json_encode( $redacted );
		}

		$data = array(
			'product_id'      => $product_id,
			'variation_id'    => $variation_id,
			'event_type'      => sanitize_key( $event_type ),
			'status'          => sanitize_key( $status ),
			'error_code'      => $error_code ? sanitize_text_field( $error_code ) : null,
			'error_message'   => $error_message ? sanitize_textarea_field( $error_message ) : null,
			'payload_summary' => $summary_json,
			'retry_count'     => $retry_count,
			'created_at'      => current_time( 'mysql', true ),
		);

		$format = array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert( $table, $data, $format );

		return $result ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Get recent events with optional filtering and pagination.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 * @return array{items: array<object>, total: int}
	 */
	public static function get_events( array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'status'     => '',
			'event_type' => '',
			'product_id' => 0,
			'limit'      => 20,
			'offset'     => 0,
			'orderby'    => 'id',
			'order'      => 'DESC',
		);

		$params = wp_parse_args( $args, $defaults );
		$table  = self::get_table_name();

		$where_clauses = array( '1=1' );
		$values        = array();

		if ( ! empty( $params['status'] ) ) {
			$where_clauses[] = 'status = %s';
			$values[]        = sanitize_key( $params['status'] );
		}

		if ( ! empty( $params['event_type'] ) ) {
			$where_clauses[] = 'event_type = %s';
			$values[]        = sanitize_key( $params['event_type'] );
		}

		if ( ! empty( $params['product_id'] ) ) {
			$where_clauses[] = 'product_id = %d';
			$values[]        = absint( $params['product_id'] );
		}

		$where_sql = implode( ' AND ', $where_clauses );

		// Allowed orderby and order for safety.
		$allowed_orderby = array( 'id', 'created_at', 'status', 'product_id' );
		$orderby         = in_array( $params['orderby'], $allowed_orderby, true ) ? $params['orderby'] : 'id';
		$order           = 'ASC' === strtoupper( $params['order'] ) ? 'ASC' : 'DESC';

		$limit  = max( 1, min( 100, absint( $params['limit'] ) ) );
		$offset = max( 0, absint( $params['offset'] ) );

		// Count query.
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $values ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $wpdb->get_var( $count_sql );
		}

		// Items query.
		$items_sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$query_values   = array_merge( $values, array( $limit, $offset ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
		$items = $wpdb->get_results( $wpdb->prepare( $items_sql, $query_values ) );

		return array(
			'items' => is_array( $items ) ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * Get failure count or summary counts.
	 *
	 * @return array<string, int>
	 */
	public static function get_status_counts(): array {
		global $wpdb;
		$table = self::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$results = $wpdb->get_results(
			"SELECT status, COUNT(*) as count FROM {$table} GROUP BY status",
			ARRAY_A
		);

		$counts = array(
			'synced'  => 0,
			'failed'  => 0,
			'pending' => 0,
			'skipped' => 0,
		);

		if ( is_array( $results ) ) {
			foreach ( $results as $row ) {
				$status = $row['status'] ?? '';
				if ( isset( $counts[ $status ] ) ) {
					$counts[ $status ] = (int) $row['count'];
				}
			}
		}

		return $counts;
	}

	/**
	 * Prune old events according to retention policy.
	 *
	 * @param int $days Retention days.
	 * @return int Number of deleted rows.
	 */
	public static function prune_old_events( int $days = 30 ): int {
		global $wpdb;
		$table = self::get_table_name();
		$cutoff_date = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE created_at < %s",
				$cutoff_date
			)
		);

		return false !== $deleted ? (int) $deleted : 0;
	}
}
