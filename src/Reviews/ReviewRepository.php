<?php
/**
 * Data access for rc_reviews (RC-018). All queries prepared; idempotent writes via unique keys (TEC-004).
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Reviews;

use ReviewCaptain\Database\Schema;

defined( 'ABSPATH' ) || exit;

final class ReviewRepository {

	public const STATUSES      = array( 'pending', 'approved', 'rejected', 'archived', 'spam' );
	public const ORIGINS       = array( 'local', 'manual', 'import', 'sync', 'connector' );
	public const TYPES         = array( 'service', 'product', 'app', 'event', 'voucher', 'general' );
	public const VERIFICATIONS = array( 'unverified', 'purchase', 'attendance', 'external' );

	/** Columns that may be written through insert/update. */
	private const WRITABLE = array(
		'origin', 'provider', 'profile_id', 'external_id', 'external_key', 'source_entry_key', 'type', 'rating_value', 'rating_min', 'rating_max',
		'title', 'content', 'language', 'author_name', 'author_avatar_url', 'author_user_id', 'author_email_hash', 'context_type', 'context_id',
		'source_url', 'attribution', 'reply_text', 'reply_at', 'status', 'verification', 'verification_ref', 'reported_count', 'published_at',
		'source_updated_at', 'fetched_at', 'tombstoned_at', 'import_batch_id', 'meta',
	);

	private static function table(): string {
		return Schema::table( 'rc_reviews' );
	}

	/**
	 * @param array<string,mixed> $data Column values.
	 * @return int|\WP_Error New ID, or WP_Error with code 'duplicate'.
	 */
	public static function insert( array $data ) {
		global $wpdb;
		$row               = array_intersect_key( $data, array_flip( self::WRITABLE ) );
		$now               = current_time( 'mysql', true );
		$row['uuid']       = wp_generate_uuid4();
		$row['created_at'] = $now;
		$row['updated_at'] = $now;
		if ( isset( $row['meta'] ) && is_array( $row['meta'] ) ) {
			$row['meta'] = wp_json_encode( $row['meta'] );
		}
		$suppress = $wpdb->suppress_errors( true );
		$ok       = $wpdb->insert( self::table(), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->suppress_errors( $suppress );
		if ( false === $ok ) {
			if ( false !== stripos( (string) $wpdb->last_error, 'Duplicate' ) ) {
				return new \WP_Error( 'duplicate', 'Review already exists.' );
			}
			return new \WP_Error( 'db_error', 'Could not save review.' );
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * @param array<string,mixed> $data Column values.
	 */
	public static function update( int $id, array $data ): bool {
		global $wpdb;
		$row = array_intersect_key( $data, array_flip( self::WRITABLE ) );
		if ( isset( $row['meta'] ) && is_array( $row['meta'] ) ) {
			$row['meta'] = wp_json_encode( $row['meta'] );
		}
		$row['updated_at'] = current_time( 'mysql', true );
		return false !== $wpdb->update( self::table(), $row, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public static function find( int $id ): ?array {
		global $wpdb;
		$table = self::table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		return $row ? $row : null;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public static function find_by_external_key( string $key ): ?array {
		global $wpdb;
		$table = self::table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE external_key = %s", $key ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		return $row ? $row : null;
	}

	public static function delete( int $id ): bool {
		global $wpdb;
		$wpdb->delete( Schema::table( 'rc_review_log' ), array( 'review_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (bool) $wpdb->delete( self::table(), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Build WHERE clause from filters.
	 *
	 * @param array<string,mixed> $f Filters: status, provider, type, rating_min, origin, date_from, date_to, language, search, context_type, context_id, public, batch.
	 * @return array{0:string,1:array<int,mixed>}
	 */
	private static function where( array $f ): array {
		$where = array( '1=1' );
		$args  = array();
		foreach ( array( 'status', 'provider', 'type', 'origin', 'language', 'context_type', 'context_id', 'verification' ) as $col ) {
			if ( isset( $f[ $col ] ) && '' !== $f[ $col ] ) {
				if ( is_array( $f[ $col ] ) ) {
					$values = array_values( array_filter( array_map( 'strval', $f[ $col ] ) ) );
					if ( $values ) {
						$where[] = "{$col} IN (" . implode( ',', array_fill( 0, count( $values ), '%s' ) ) . ')';
						$args    = array_merge( $args, $values );
					}
				} else {
					$where[] = "{$col} = %s";
					$args[]  = (string) $f[ $col ];
				}
			}
		}
		if ( isset( $f['rating_min'] ) && '' !== $f['rating_min'] ) {
			// Compare on the normalized scale so 1-10 and 1-5 scales are filtered consistently.
			$where[] = '(rating_value IS NOT NULL AND (rating_value - rating_min) / NULLIF(rating_max - rating_min, 0) >= %f)';
			$args[]  = max( 0, min( 1, ( (float) $f['rating_min'] - 1 ) / 4 ) );
		}
		if ( ! empty( $f['date_from'] ) ) {
			$where[] = 'COALESCE(published_at, created_at) >= %s';
			$args[]  = gmdate( 'Y-m-d 00:00:00', strtotime( (string) $f['date_from'] ) );
		}
		if ( ! empty( $f['date_to'] ) ) {
			$where[] = 'COALESCE(published_at, created_at) <= %s';
			$args[]  = gmdate( 'Y-m-d 23:59:59', strtotime( (string) $f['date_to'] ) );
		}
		if ( ! empty( $f['search'] ) ) {
			global $wpdb;
			$like    = '%' . $wpdb->esc_like( (string) $f['search'] ) . '%';
			$where[] = '(title LIKE %s OR content LIKE %s OR author_name LIKE %s)';
			array_push( $args, $like, $like, $like );
		}
		if ( ! empty( $f['batch'] ) ) {
			$where[] = 'import_batch_id = %d';
			$args[]  = (int) $f['batch'];
		}
		if ( ! empty( $f['ids'] ) && is_array( $f['ids'] ) ) {
			$ids     = array_map( 'intval', $f['ids'] );
			$where[] = 'id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')';
			$args    = array_merge( $args, $ids );
		}
		if ( ! empty( $f['public'] ) ) {
			// Public output rule (docs/m1/datenmodell.md).
			$where[] = "status = 'approved' AND tombstoned_at IS NULL AND (origin <> 'sync' OR fetched_at >= %s)";
			$args[]  = gmdate( 'Y-m-d H:i:s', time() - (int) apply_filters( 'review_captain_external_max_age_seconds', DAY_IN_SECONDS ) );
		}
		return array( implode( ' AND ', $where ), $args );
	}

	/**
	 * @param array<string,mixed> $filters Filters.
	 * @return array<int,array<string,mixed>>
	 */
	public static function query( array $filters = array(), int $limit = 20, int $offset = 0, string $orderby = 'date', string $order = 'DESC' ): array {
		global $wpdb;
		list( $where, $args ) = self::where( $filters );
		$columns              = array(
			'date'   => 'COALESCE(published_at, created_at)',
			'rating' => 'rating_value',
			'id'     => 'id',
		);
		$order_sql            = ( $columns[ $orderby ] ?? $columns['date'] ) . ( 'ASC' === strtoupper( $order ) ? ' ASC' : ' DESC' ) . ', id DESC';
		$table                = self::table();
		$sql                  = "SELECT * FROM {$table} WHERE {$where} ORDER BY {$order_sql} LIMIT %d OFFSET %d";
		$args[]               = max( 1, $limit );
		$args[]               = max( 0, $offset );
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * @param array<string,mixed> $filters Filters.
	 */
	public static function count( array $filters = array() ): int {
		global $wpdb;
		list( $where, $args ) = self::where( $filters );
		$table                = self::table();
		$sql                  = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
		return (int) ( $args ? $wpdb->get_var( $wpdb->prepare( $sql, $args ) ) : $wpdb->get_var( $sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * @return array<string,int>
	 */
	public static function status_counts(): array {
		global $wpdb;
		$table  = self::table();
		$rows   = (array) $wpdb->get_results( "SELECT status, COUNT(*) AS n FROM {$table} GROUP BY status", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$counts = array_fill_keys( self::STATUSES, 0 );
		foreach ( $rows as $row ) {
			$counts[ $row['status'] ] = (int) $row['n'];
		}
		return $counts;
	}

	/**
	 * Per-provider summary of public reviews (never a cross-platform average).
	 *
	 * @param array<string,mixed> $filters Extra filters.
	 * @return array<string,array{count:int,average:float|null,scale_max:int}>
	 */
	public static function summary_by_provider( array $filters = array() ): array {
		global $wpdb;
		$filters['public']    = true;
		list( $where, $args ) = self::where( $filters );
		$table                = self::table();
		$sql                  = "SELECT provider, rating_max, COUNT(*) AS n, AVG(rating_value) AS avg_rating FROM {$table} WHERE {$where} GROUP BY provider, rating_max";
		$rows                 = (array) $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		$out                  = array();
		foreach ( $rows as $row ) {
			$key         = $row['provider'] . ( '5' === (string) $row['rating_max'] ? '' : ':' . $row['rating_max'] );
			$out[ $key ] = array(
				'count'     => (int) $row['n'],
				'average'   => null !== $row['avg_rating'] ? round( (float) $row['avg_rating'], 1 ) : null,
				'scale_max' => (int) $row['rating_max'],
			);
		}
		return $out;
	}

	public static function log( int $review_id, string $action, ?string $reason = null ): void {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			Schema::table( 'rc_review_log' ),
			array(
				'review_id'  => $review_id,
				'action'     => substr( $action, 0, 20 ),
				'reason'     => null !== $reason ? substr( sanitize_text_field( $reason ), 0, 255 ) : null,
				'user_id'    => get_current_user_id() ? get_current_user_id() : null,
				'created_at' => current_time( 'mysql', true ),
			)
		);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function history( int $review_id ): array {
		global $wpdb;
		$table = Schema::table( 'rc_review_log' );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE review_id = %d ORDER BY id ASC", $review_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	}
}
