<?php
/**
 * Data-minimising audit log (RC-037, SET-019).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Audit;

use ReviewCaptain\Install\Schema;

/**
 * Writes audit entries. Context is filtered through an allowlist of keys and
 * any key that looks like a secret or personal data is dropped.
 */
class AuditLog {

	/**
	 * Context keys that may be stored.
	 */
	private const ALLOWED_KEYS = array(
		'from',
		'to',
		'field',
		'fields',
		'reason',
		'count',
		'platform',
		'kind',
		'status',
		'batch_id',
		'job_type',
		'error_code',
		'connector',
		'version',
		'tab',
		'mode',
		'scope',
		'capability',
		'roles',
		'changes',
	);

	/**
	 * Object types of settings and role changes (SET-RC-17): own retention, own filter in the view.
	 */
	public const SETTINGS_OBJECT_TYPES = array( 'settings', 'capability' );

	/**
	 * Keys that are never stored, even if allowlisted by a filter.
	 */
	private const FORBIDDEN_PATTERN = '/(secret|token|password|passwd|key|email|mail|body|text|phone|address|ip)/i';

	/**
	 * Record an event.
	 *
	 * @param string               $event       Event name, e.g. review.status_changed.
	 * @param string               $object_type Object type.
	 * @param string|int           $object_id   Object ID.
	 * @param array<string, mixed> $context     Context.
	 */
	public function record( string $event, string $object_type = '', string|int $object_id = '', array $context = array() ): void {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			Schema::table( 'audit_log' ),
			array(
				'event'       => substr( sanitize_key( str_replace( '.', '_', $event ) ) === '' ? 'unknown' : $event, 0, 80 ),
				'object_type' => substr( $object_type, 0, 40 ),
				'object_id'   => substr( (string) $object_id, 0, 64 ),
				'user_id'     => get_current_user_id() ? get_current_user_id() : null,
				'context'     => wp_json_encode( (object) self::sanitize_context( $context ) ),
				'created_at'  => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s' )
		);
	}

	/**
	 * Filter context to the allowlist.
	 *
	 * @param array<string, mixed> $context Raw context.
	 * @return array<string, mixed>
	 */
	public static function sanitize_context( array $context ): array {
		$allowed = (array) apply_filters( 'review_captain_audit_allowed_keys', self::ALLOWED_KEYS );
		$out     = array();
		foreach ( $context as $key => $value ) {
			$key = (string) $key;
			if ( ! in_array( $key, $allowed, true ) || preg_match( self::FORBIDDEN_PATTERN, $key ) ) {
				continue;
			}
			if ( is_scalar( $value ) || null === $value ) {
				$out[ $key ] = is_string( $value ) ? mb_substr( $value, 0, 200 ) : $value;
			} elseif ( is_array( $value ) ) {
				$out[ $key ] = array_slice( array_map( array( self::class, 'sanitize_item' ), $value ), 0, 50 );
			}
		}
		return $out;
	}

	/**
	 * One list element of a context value: a scalar, or a flat record such as a settings change
	 * {field, from, to} (SET-RC-17) whose keys pass the same key filter.
	 *
	 * @param mixed $item Item.
	 * @return string|array<string, string|int|float|bool|null>
	 */
	private static function sanitize_item( mixed $item ): string|array {
		if ( is_scalar( $item ) ) {
			return mb_substr( (string) $item, 0, 100 );
		}
		if ( ! is_array( $item ) ) {
			return '';
		}
		$out = array();
		foreach ( $item as $k => $v ) {
			$k = (string) $k;
			if ( preg_match( self::FORBIDDEN_PATTERN, $k ) || ! ( is_scalar( $v ) || null === $v ) ) {
				continue;
			}
			$out[ $k ] = is_string( $v ) ? mb_substr( $v, 0, 100 ) : $v;
		}
		return $out;
	}

	/**
	 * Recent entries.
	 *
	 * @param int               $limit        Limit.
	 * @param int               $offset       Offset.
	 * @param list<string>|null $object_types Only these object types (null = all).
	 * @return list<array<string, mixed>>
	 */
	public function recent( int $limit = 50, int $offset = 0, ?array $object_types = null ): array {
		global $wpdb;
		$table = Schema::table( 'audit_log' );
		if ( null !== $object_types && $object_types ) {
			$in   = implode( ',', array_fill( 0, count( $object_types ), '%s' ) );
			$args = array_merge( array_values( array_map( 'strval', $object_types ) ), array( $limit, $offset ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE object_type IN ({$in}) ORDER BY id DESC LIMIT %d OFFSET %d", ...$args ), ARRAY_A );
			return is_array( $rows ) ? $rows : array();
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $limit, $offset ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Delete entries older than N days, optionally except some object types (those have their own
	 * retention, see purge_types_older_than()).
	 *
	 * @param int          $days         Days.
	 * @param list<string> $except_types Object types to keep.
	 */
	public function purge_older_than( int $days, array $except_types = array() ): int {
		global $wpdb;
		$table  = Schema::table( 'audit_log' );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		if ( $except_types ) {
			$in = implode( ',', array_fill( 0, count( $except_types ), '%s' ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
			return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s AND object_type NOT IN ({$in}) LIMIT 5000", $cutoff, ...array_values( array_map( 'strval', $except_types ) ) ) );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s LIMIT 5000", $cutoff ) );
	}

	/**
	 * Delete entries of the given object types older than N days (SET-RC-17).
	 *
	 * @param list<string> $types Object types.
	 * @param int          $days  Days.
	 */
	public function purge_types_older_than( array $types, int $days ): int {
		global $wpdb;
		if ( ! $types ) {
			return 0;
		}
		$table = Schema::table( 'audit_log' );
		$in    = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s AND object_type IN ({$in}) LIMIT 5000", gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ), ...array_values( array_map( 'strval', $types ) ) ) );
	}
}
