<?php
/**
 * Data-minimal audit log (RC-037, SET-019).
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Audit;

use ReviewCaptain\Database\Schema;

defined( 'ABSPATH' ) || exit;

final class AuditLog {

	/** Context keys that may be stored. Everything else is dropped (allowlist, TEC-008). */
	private const CONTEXT_ALLOWLIST = array( 'changed_keys', 'group', 'status_from', 'status_to', 'reason', 'count', 'code', 'batch', 'format', 'provider', 'connector', 'version' );

	/**
	 * @param array<string,mixed> $context Additional data (filtered by allowlist).
	 */
	public static function log( string $event, ?string $object_type = null, $object_id = null, array $context = array() ): void {
		global $wpdb;
		$context = array_intersect_key( $context, array_flip( self::CONTEXT_ALLOWLIST ) );
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			Schema::table( 'rc_audit_log' ),
			array(
				'event'       => substr( sanitize_key( $event ), 0, 64 ),
				'object_type' => $object_type ? substr( sanitize_key( $object_type ), 0, 32 ) : null,
				'object_id'   => null !== $object_id ? substr( (string) $object_id, 0, 64 ) : null,
				'user_id'     => get_current_user_id() ? get_current_user_id() : null,
				'context'     => $context ? wp_json_encode( $context ) : null,
				'created_at'  => current_time( 'mysql', true ),
			)
		);
	}

	/**
	 * Latest entries for diagnostics.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function latest( int $limit = 50 ): array {
		global $wpdb;
		$table = Schema::table( 'rc_audit_log' );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	}
}
