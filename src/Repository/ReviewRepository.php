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
use ReviewCaptain\Media\PhotoStore;
use ReviewCaptain\Moderation\ReportStore;
use ReviewCaptain\Plugin;
use ReviewCaptain\Support\CacheGeneration;
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
	 * Find by submission key (author + context, RC-123).
	 *
	 * @param string $key Key.
	 */
	public function find_by_submission_key( string $key ): ?Review {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table()} WHERE submission_key = %s", $key ), ARRAY_A );
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

		$row              = $review->to_row();
		$row['sorted_at'] = self::sorted_at( $row['published_at'] ?? null, $review->created_at );
		// Duplicate dedupe/submission keys are expected when imports or syncs race (TEC-005); callers
		// handle the exception. Keep them out of the PHP error log, log anything else.
		$suppress = method_exists( $wpdb, 'suppress_errors' ) ? $wpdb->suppress_errors( true ) : null;
		$ok       = $wpdb->insert( $this->table(), self::nullify( $row ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		if ( null !== $suppress ) {
			$wpdb->suppress_errors( $suppress );
		}
		if ( false === $ok ) {
			\ReviewCaptain\Jobs\JobQueue::log_db_error( 'review_insert_failed', (string) $wpdb->last_error );
			throw new \RuntimeException( 'review_insert_failed: ' . $wpdb->last_error ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		$review->id = (int) $wpdb->insert_id;
		$this->log_event( $review->id, 'created', null, $review->status->value, '' );
		CacheGeneration::bump();
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
				// The winner's row may become visible a moment later under contention (TEC-005).
				for ( $i = 0; null === $existing && $i < 5; $i++ ) {
					usleep( 10000 * ( $i + 1 ) );
					$existing = $this->find_by_dedupe_key( $key );
				}
				if ( null === $existing ) {
					throw $e;
				}
			}
		}
		$review->id         = $existing->id;
		$review->uuid       = $existing->uuid;
		$review->created_at = $existing->created_at;
		// Never carry identity/uniqueness of the incoming object over the stored one.
		$review->submission_key = $existing->submission_key;
		// Content and moderation status are written independently (TEC-005 R-12b): the content
		// update never touches status, so a moderation decision taken concurrently survives.
		$incoming_status = $review->status;
		$review->status  = $existing->status;
		$this->update( $review );
		if ( ReviewStatus::REMOVED === $existing->status ) {
			// Reappeared at the source: restore via status CAS (removed → initial status).
			if ( $this->change_status( (int) $existing->id, $incoming_status, 'remote_restored', true ) ) {
				$review->status     = $incoming_status;
				$review->removed_at = null;
			} else {
				$current        = $this->find( (int) $existing->id );
				$review->status = null !== $current ? $current->status : $existing->status;
			}
		}
		return array(
			'review'  => $review,
			'created' => false,
		);
	}

	/**
	 * Update an existing review (content fields). Status changes must use change_status(); the
	 * status/removed_at columns are only written when $include_status is true, so a concurrent
	 * moderation change is never overwritten (security review SR-06).
	 *
	 * Every update increments `revision` in SQL. With $check_revision the write is a CAS on the
	 * revision the caller read (optimistic lock, TEC-005 R-12a): false = someone else changed the
	 * content in the meantime, nothing was written.
	 *
	 * @param Review $review         Review with id.
	 * @param bool   $include_status Also write status/removed_at.
	 * @param bool   $check_revision Only write if the stored revision still equals $review->revision.
	 * @throws \InvalidArgumentException On validation error.
	 */
	public function update( Review $review, bool $include_status = false, bool $check_revision = false ): bool {
		global $wpdb;
		if ( null === $review->id ) {
			throw new \InvalidArgumentException( 'missing_id' );
		}
		$errors = $review->validate();
		if ( $errors ) {
			throw new \InvalidArgumentException( implode( ',', $errors ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		$review->updated_at = gmdate( 'Y-m-d H:i:s' );
		$row                = $review->to_row();
		unset( $row['uuid'], $row['created_at'], $row['revision'] );
		if ( array_key_exists( 'published_at', $row ) && ( null !== $row['published_at'] || '' !== $review->created_at ) ) {
			$row['sorted_at'] = self::sorted_at( $row['published_at'], $review->created_at );
		}
		if ( ! $include_status ) {
			unset( $row['status'], $row['removed_at'] );
		}
		$set    = array();
		$params = array();
		foreach ( $row as $column => $value ) {
			if ( null === $value ) {
				$set[] = "{$column} = NULL";
				continue;
			}
			$set[]    = is_int( $value ) ? "{$column} = %d" : "{$column} = %s";
			$params[] = $value;
		}
		$set[]    = 'revision = revision + 1';
		$where    = 'id = %d';
		$params[] = $review->id;
		if ( $check_revision ) {
			$where   .= ' AND revision = %d';
			$params[] = $review->revision;
		}
		$sql = "UPDATE {$this->table()} SET " . implode( ', ', $set ) . " WHERE {$where}";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		$affected = $wpdb->query( $wpdb->prepare( $sql, $params ) );
		if ( 1 !== (int) $affected ) {
			return false;
		}
		CacheGeneration::bump();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$review->revision = (int) $wpdb->get_var( $wpdb->prepare( "SELECT revision FROM {$this->table()} WHERE id = %d", $review->id ) );
		return true;
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
		} elseif ( ReviewStatus::REMOVED === $from ) {
			$data['removed_at'] = null;
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
		if ( ReviewStatus::FLAGGED === $from ) {
			// A moderator (or the system) decided on a flagged review: close its public reports.
			ReportStore::resolve( $id );
		}
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
		CacheGeneration::bump();
		Plugin::instance()->events()->review_status_changed( $review, $from->value );
		if ( ReviewStatus::REMOVED === $to ) {
			Plugin::instance()->events()->review_removed( $review );
		}
		return true;
	}

	/**
	 * Hard delete (eraser/rollback/retention). Also deletes history, tags, reports and photos.
	 *
	 * @param int $id ID.
	 */
	public function delete( int $id ): bool {
		global $wpdb;
		$review = $this->find( $id );
		if ( null === $review ) {
			return false;
		}
		$deleted = (bool) $wpdb->delete( $this->table(), array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		self::purge_related( $id );
		CacheGeneration::bump();
		if ( $deleted ) {
			Plugin::instance()->events()->review_removed( $review );
		}
		return $deleted;
	}

	/**
	 * Delete a review only while it still has the expected status (CAS delete, e.g. cancelling a
	 * pending workflow draft that a moderator might approve at the same time).
	 *
	 * @param int          $id       ID.
	 * @param ReviewStatus $expected Expected status.
	 */
	public function delete_if_status( int $id, ReviewStatus $expected ): bool {
		global $wpdb;
		$review = $this->find( $id );
		if ( null === $review || $expected !== $review->status ) {
			return false;
		}
		$deleted = 1 === (int) $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			array(
				'id'     => $id,
				'status' => $expected->value,
			)
		);
		if ( ! $deleted ) {
			return false;
		}
		self::purge_related( $id );
		Plugin::instance()->events()->review_removed( $review );
		CacheGeneration::bump();
		return true;
	}

	/**
	 * Remove rows and files that belong to a (deleted) review. Safe to call repeatedly.
	 *
	 * @param int $id Review ID.
	 */
	public static function purge_related( int $id ): void {
		global $wpdb;
		foreach ( array( 'review_events', 'review_tags', 'review_reports' ) as $key ) {
			$wpdb->delete( Schema::table( $key ), array( 'review_id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		PhotoStore::delete_for_review( $id );
	}

	/**
	 * Query reviews.
	 *
	 * Supported args: kind, platform, status (string|list), origin, verification, profile_id,
	 * context_type, context_id, min_normalized (0..1), search, date_from, date_to (Y-m-d),
	 * import_batch_id, author_email, ids (list<int>), tag (internal tag, RC-124), reported (open public
	 * reports, RC-124), orderby (created_at|published_at|rating|id),
	 * order (ASC|DESC), limit, offset.
	 *
	 * @param array<string, mixed> $args Args.
	 * @return list<Review>
	 */
	public function query( array $args = array() ): array {
		global $wpdb;
		[ $where, $params ] = $this->build_where( $args );
		$orderby            = match ( $args['orderby'] ?? 'created_at' ) {
			'published_at' => 'sorted_at', // = COALESCE(published_at, created_at), indexed (status, sorted_at); TEC-014.
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
		$args['status'] = $args['status'] ?? ReviewStatus::APPROVED->value;
		// TEC-012/RC-136: aggregate per widget is a full scan of the matching rows; cache it per
		// generation (bumped on every review write). Short TTL bounds time-based visibility rules.
		return CacheGeneration::remember( 's', $args, 5 * MINUTE_IN_SECONDS, fn() => $this->summary_by_platform_uncached( $args ) );
	}

	/**
	 * Uncached aggregate (see summary_by_platform()).
	 *
	 * @param array<string, mixed> $args Filters incl. status.
	 * @return list<array{platform: string, count: int, average: float|null, scale_min: float, scale_max: float}>
	 */
	private function summary_by_platform_uncached( array $args ): array {
		global $wpdb;
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
	 * Sort date of a review: publication date, else creation date (TEC-014, schema v3).
	 *
	 * @param mixed  $published_at Published date or null.
	 * @param string $created_at   Created date.
	 */
	public static function sorted_at( $published_at, string $created_at ): ?string {
		if ( is_string( $published_at ) && '' !== $published_at ) {
			return $published_at;
		}
		return '' !== $created_at ? $created_at : null;
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
		if ( ! empty( $args['after_id'] ) ) {
			// Keyset pagination for large exports (TEC-014): constant cost per batch instead of OFFSET.
			$where[]  = 'id > %d';
			$params[] = (int) $args['after_id'];
		}
		foreach ( array( 'profile_id', 'import_batch_id' ) as $col ) {
			if ( isset( $args[ $col ] ) && '' !== $args[ $col ] ) {
				$where[]  = "{$col} = %d";
				$params[] = (int) $args[ $col ];
			}
		}
		if ( isset( $args['tag'] ) && is_string( $args['tag'] ) && '' !== $args['tag'] ) {
			$where[]  = 'id IN (SELECT review_id FROM ' . Schema::table( 'review_tags' ) . ' WHERE tag = %s)';
			$params[] = sanitize_key( $args['tag'] );
		}
		if ( ! empty( $args['reported'] ) ) {
			$where[] = 'id IN (SELECT review_id FROM ' . Schema::table( 'review_reports' ) . ' WHERE resolved_at IS NULL)';
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
	 * Record a content edit in the review history (field names only, never content).
	 *
	 * @param int          $review_id Review ID.
	 * @param list<string> $fields    Changed field names.
	 */
	public function record_edit( int $review_id, array $fields ): void {
		$this->log_event( $review_id, 'edited', null, null, implode( ',', array_map( 'sanitize_key', $fields ) ) );
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
