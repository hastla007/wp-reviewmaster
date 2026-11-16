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
	);

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
				$out[ $key ] = array_slice( array_map( static fn( $v ) => is_scalar( $v ) ? mb_substr( (string) $v, 0, 100 ) : '', $value ), 0, 50 );
			}
		}
		return $out;
	}

	/**
	 * Recent entries.
	 *
	 * @param int $limit Limit.
	 * @param int $offset Offset.
	 * @return list<array<string, mixed>>
	 */
	public function recent( int $limit = 50, int $offset = 0 ): array {
		global $wpdb;
		$table = Schema::table( 'audit_log' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $limit, $offset ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Delete entries older than N days.
	 *
	 * @param int $days Days.
	 */
	public function purge_older_than( int $days ): int {
		global $wpdb;
		$table = Schema::table( 'audit_log' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s LIMIT 5000", gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) ) );
	}
}
