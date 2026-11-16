<?php
/**
 * Review persistence.
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Repository;

use ReviewCaptain\Domain\Review;
use ReviewCaptain\Domain\ReviewStatus;
use ReviewCaptain\Install\Schema;
use ReviewCaptain\Plugin;
use ReviewCaptain\Support\Uuid;

/**
 * CRUD and query access to the reviews table. All SQL is prepared.
 */
class ReviewRepository {

	public const MAX_LIMIT = 200;

	/**
	 * Table name.
	 */
	public function table(): string {
		return Schema::table( 'reviews' );
	}

	/**
	 * Find by ID.
	 *
	 * @param int $id ID.
	 */
	public function find( int $id ): ?Review {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table()} WHERE id = %d", $id ), ARRAY_A );
		return is_array( $row ) ? Review::from_row( $row ) : null;
	}

	/**
	 * Find by UUID.
	 *
	 * @param string $uuid UUID.
	 */
	public function find_by_uuid( string $uuid ): ?Review {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table()} WHERE uuid = %s", $uuid ), ARRAY_A );
		return is_array( $row ) ? Review::from_row( $row ) : null;
	}

	/**
	 * Find by dedupe key.
	 *
	 * @param string $dedupe_key Key.
	 */
	public function find_by_dedupe_key( string $dedupe_key ): ?Review {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table()} WHERE dedupe_key = %s", $dedupe_key ), ARRAY_A );
		return is_array( $row ) ? Review::from_row( $row ) : null;
	}

	/**
	 * Insert a new review. Fires review_created after commit.
	 *
	 * @param Review $review Review (id must be null).
	 * @throws \InvalidArgumentException On validation error.
	 * @throws \RuntimeException On DB error (e.g. duplicate dedupe key).
	 */
	public function insert( Review $review ): Review {
		global $wpdb;
		$errors = $review->validate();
		if ( $errors ) {
			throw new \InvalidArgumentException( implode( ',', $errors ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		$now                = gmdate( 'Y-m-d H:i:s' );
		$review->uuid       = '' !== $review->uuid ? $review->uuid : Uuid::v4();
		$review->created_at = '' !== $review->created_at ? $review->created_at : $now;
		$review->updated_at = $now;

		$ok = $wpdb->insert( $this->table(), self::nullify( $review->to_row() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		if ( false === $ok ) {
			throw new \RuntimeException( 'review_insert_failed: ' . $wpdb->last_error ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		$review->id = (int) $wpdb->insert_id;
		$this->log_event( $review->id, 'created', null, $review->status->value, '' );
		Plugin::instance()->events()->review_created( $review );
		return $review;
	}

	/**
	 * Insert or update an external record by its dedupe key (provider sync / CSV import).
	 * Moderation status of existing records is preserved.
	 *
	 * @param Review $review Review with external_id.
	 * @return array{review: Review, created: bool}
	 */
	public function upsert_external( Review $review ): array {
		$key = $review->dedupe_key();
		if ( null === $key ) {
			return array(
				'review'  => $this->insert( $review ),
				'created' => true,
			);
		}
		$existing = $this->find_by_dedupe_key( $key );
		if ( null === $existing ) {
			try {
				return array(
					'review'  => $this->insert( $review ),
					'created' => true,
				);
			} catch ( \RuntimeException $e ) {
				// Lost a race against a parallel insert: fall through to update.
				$existing = $this->find_by_dedupe_key( $key );
				if ( null === $existing ) {
					throw $e;
				}
			}
		}
		$review->id         = $existing->id;
		$review->uuid       = $existing->uuid;
		$review->status     = ReviewStatus::REMOVED === $existing->status ? $review->status : $existing->status;
		$review->created_at = $existing->created_at;
		$this->update( $review );
		return array(
			'review'  => $review,
			'created' => false,
		);
	}

	/**
	 * Update an existing review (content fields). Status changes must use change_status().
	 *
	 * @param Review $review Review with id.
	 * @throws \InvalidArgumentException On validation error.
	 */
	public function update( Review $review ): void {
		global $wpdb;
		if ( null === $review->id ) {
			throw new \InvalidArgumentException( 'missing_id' );
		}
		$errors = $review->validate();
		if ( $errors ) {
			throw new \InvalidArgumentException( implode( ',', $errors ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		$review->updated_at = gmdate( 'Y-m-d H:i:s' );
		$row                = $review->to_row(); // wpdb writes PHP null as SQL NULL.
		unset( $row['uuid'], $row['created_at'] );
		$wpdb->update( $this->table(), $row, array( 'id' => $review->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Change moderation status with reason (RC-040). Returns false if transition not allowed.
	 *
	 * @param int          $id     Review ID.
	 * @param ReviewStatus $to     Target status.
	 * @param string       $reason Reason.
	 * @param bool         $system System transition (sync/eraser) bypasses the moderation matrix.
	 */
	public function change_status( int $id, ReviewStatus $to, string $reason = '', bool $system = false ): bool {
		global $wpdb;
		$review = $this->find( $id );
		if ( null === $review ) {
			return false;
		}
		$from = $review->status;
		if ( $from === $to ) {
			return true;
		}
		if ( ! $system && ! $from->can_transition_to( $to ) ) {
			return false;
		}
		$data = array(
			'status'     => $to->value,
			'updated_at' => gmdate( 'Y-m-d H:i:s' ),
		);
		if ( ReviewStatus::REMOVED === $to ) {
			$data['removed_at'] = gmdate( 'Y-m-d H:i:s' );
		}
		// Optimistic concurrency: only update if status is still the one we read.
		$updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			$data,
			array(
				'id'     => $id,
				'status' => $from->value,
			)
		);
		if ( 1 !== $updated ) {
			return false;
		}
		$review->status = $to;
		$this->log_event( $id, 'status_changed', $from->value, $to->value, $reason );
		Plugin::instance()->audit()->record(
			'review.status_changed',
			'review',
			$id,
			array(
				'from'   => $from->value,
				'to'     => $to->value,
				'reason' => $reason,
			)
		);
		Plugin::instance()->events()->review_status_changed( $review, $from->value );
		if ( ReviewStatus::REMOVED === $to ) {
			Plugin::instance()->events()->review_removed( $review );
		}
		return true;
	}

	/**
	 * Hard delete (eraser/rollback/retention). Also deletes history.
	 *
	 * @param int $id ID.
	 */
	public function delete( int $id ): bool {
		global $wpdb;
		$review = $this->find( $id );
		if ( null === $review ) {
			return false;
		}
		$wpdb->delete( Schema::table( 'review_events' ), array( 'review_id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$deleted = (bool) $wpdb->delete( $this->table(), array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $deleted ) {
			Plugin::instance()->events()->review_removed( $review );
		}
		return $deleted;
	}

	/**
	 * Query reviews.
	 *
	 * Supported args: kind, platform, status (string|list), origin, verification, profile_id,
	 * context_type, context_id, min_normalized (0..1), search, date_from, date_to (Y-m-d),
	 * import_batch_id, author_email, ids (list<int>), orderby (created_at|published_at|rating|id),
	 * order (ASC|DESC), limit, offset.
	 *
	 * @param array<string, mixed> $args Args.
	 * @return list<Review>
	 */
	public function query( array $args = array() ): array {
		global $wpdb;
		[ $where, $params ] = $this->build_where( $args );
		$orderby            = match ( $args['orderby'] ?? 'created_at' ) {
			'published_at' => 'COALESCE(published_at, created_at)',
			'rating'       => '((rating_value - rating_scale_min) / NULLIF(rating_scale_max - rating_scale_min, 0))',
			'id'           => 'id',
			default        => 'created_at',
		};
		$order              = 'ASC' === strtoupper( (string) ( $args['order'] ?? 'DESC' ) ) ? 'ASC' : 'DESC';
		$limit              = max( 1, min( self::MAX_LIMIT, (int) ( $args['limit'] ?? 20 ) ) );
		$offset             = max( 0, (int) ( $args['offset'] ?? 0 ) );
		$sql                = "SELECT * FROM {$this->table()} WHERE {$where} ORDER BY {$orderby} {$order}, id {$order} LIMIT %d OFFSET %d";
		$params[]           = $limit;
		$params[]           = $offset;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		return array_map( array( Review::class, 'from_row' ), is_array( $rows ) ? $rows : array() );
	}

	/**
	 * Count matching reviews.
	 *
	 * @param array<string, mixed> $args Same as query().
	 */
	public function count( array $args = array() ): int {
		global $wpdb;
		[ $where, $params ] = $this->build_where( $args );
		$sql                = "SELECT COUNT(*) FROM {$this->table()} WHERE {$where}";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		return (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_var( $sql ) );
	}

	/**
	 * Per-platform summary – never a cross-platform score (RC-114).
	 *
	 * @param array<string, mixed> $args Filter args (status defaults to approved).
	 * @return list<array{platform: string, count: int, average: ?float, scale_min: float, scale_max: float}>
	 */
	public function summary_by_platform( array $args = array() ): array {
		global $wpdb;
		$args['status']     = $args['status'] ?? ReviewStatus::APPROVED->value;
		[ $where, $params ] = $this->build_where( $args );
		$sql                = "SELECT platform, rating_scale_min, rating_scale_max, COUNT(*) AS cnt, AVG(rating_value) AS avg_rating
			FROM {$this->table()} WHERE {$where} GROUP BY platform, rating_scale_min, rating_scale_max ORDER BY cnt DESC";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		$rows = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[] = array(
				'platform'  => (string) $row['platform'],
				'count'     => (int) $row['cnt'],
				'average'   => null !== $row['avg_rating'] ? round( (float) $row['avg_rating'], 2 ) : null,
				'scale_min' => (float) $row['rating_scale_min'],
				'scale_max' => (float) $row['rating_scale_max'],
			);
		}
		return $out;
	}

	/**
	 * Moderation history for a review.
	 *
	 * @param int $review_id Review ID.
	 * @return list<array<string, mixed>>
	 */
	public function history( int $review_id ): array {
		global $wpdb;
		$table = Schema::table( 'review_events' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE review_id = %d ORDER BY id DESC LIMIT 200", $review_id ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Build WHERE clause.
	 *
	 * @param array<string, mixed> $args Args.
	 * @return array{0: string, 1: list<mixed>}
	 */
	public function build_where( array $args ): array {
		global $wpdb;
		$where  = array( '1=1' );
		$params = array();

		foreach ( array( 'kind', 'platform', 'status', 'origin', 'verification', 'context_type', 'context_id', 'author_email' ) as $col ) {
			if ( ! isset( $args[ $col ] ) || '' === $args[ $col ] || array() === $args[ $col ] ) {
				continue;
			}
			$values = array_values( array_map( 'strval', (array) $args[ $col ] ) );
			if ( 1 === count( $values ) ) {
				$where[]  = "{$col} = %s";
				$params[] = $values[0];
			} else {
				$where[] = "{$col} IN (" . implode( ',', array_fill( 0, count( $values ), '%s' ) ) . ')';
				$params  = array_merge( $params, $values );
			}
		}
		foreach ( array( 'profile_id', 'import_batch_id' ) as $col ) {
			if ( isset( $args[ $col ] ) && '' !== $args[ $col ] ) {
				$where[]  = "{$col} = %d";
				$params[] = (int) $args[ $col ];
			}
		}
		if ( ! empty( $args['ids'] ) && is_array( $args['ids'] ) ) {
			$ids     = array_values( array_filter( array_map( 'intval', $args['ids'] ) ) );
			$where[] = $ids ? 'id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')' : '1=0';
			$params  = array_merge( $params, $ids );
		}
		if ( isset( $args['min_normalized'] ) && '' !== $args['min_normalized'] ) {
			$where[]  = 'rating_value IS NOT NULL AND (rating_value - rating_scale_min) / NULLIF(rating_scale_max - rating_scale_min, 0) >= %f';
			$params[] = max( 0.0, min( 1.0, (float) $args['min_normalized'] ) );
		}
		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$where[]  = '(title LIKE %s OR body LIKE %s OR author_name LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}
		if ( ! empty( $args['date_from'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $args['date_from'] ) ) {
			$where[]  = 'created_at >= %s';
			$params[] = $args['date_from'] . ' 00:00:00';
		}
		if ( ! empty( $args['date_to'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $args['date_to'] ) ) {
			$where[]  = 'created_at <= %s';
			$params[] = $args['date_to'] . ' 23:59:59';
		}
		return array( implode( ' AND ', $where ), $params );
	}

	/**
	 * Write a moderation history entry.
	 *
	 * @param int         $review_id Review ID.
	 * @param string      $action    Action.
	 * @param string|null $from      From status.
	 * @param string|null $to        To status.
	 * @param string      $reason    Reason.
	 */
	private function log_event( int $review_id, string $action, ?string $from, ?string $to, string $reason ): void {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			Schema::table( 'review_events' ),
			array(
				'review_id'   => $review_id,
				'action'      => $action,
				'from_status' => $from,
				'to_status'   => $to,
				'reason'      => mb_substr( sanitize_text_field( $reason ), 0, 500 ),
				'user_id'     => get_current_user_id() ? get_current_user_id() : null,
				'created_at'  => gmdate( 'Y-m-d H:i:s' ),
			)
		);
	}

	/**
	 * Remove null values so wpdb inserts SQL NULL defaults correctly.
	 *
	 * @param array<string, mixed> $row Row.
	 * @return array<string, mixed>
	 */
	private static function nullify( array $row ): array {
		return array_filter( $row, static fn( $v ) => null !== $v );
	}
}
