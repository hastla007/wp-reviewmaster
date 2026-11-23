<?php
/**
 * Product Q&A storage (RC-122). Separate from reviews by design.
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Questions;

use ReviewCaptain\Install\Schema;
use ReviewCaptain\Support\Uuid;

/**
 * Table `review_captain_questions`. Questions are never reviews: they have no rating, never
 * appear in review widgets, summaries, schema markup, aggregates or review events.
 *
 * Status: pending → approved | rejected (moderation); an answer can be added at any time,
 * public output shows approved questions only.
 */
class QuestionRepository {

	public const STATUSES = array( 'pending', 'approved', 'rejected' );

	/**
	 * Table.
	 */
	public function table(): string {
		return Schema::table( 'questions' );
	}

	/**
	 * Insert a question. Returns the stored row.
	 *
	 * Optional `uuid` (UUID format): a caller-derived ID makes a repeated insert fail on the UNIQUE
	 * key instead of storing the question twice (double click, review P2).
	 *
	 * @param array{context_type: string, context_id: string, question: string, author_name: string, author_email: ?string, uuid?: string} $data Data.
	 * @return array<string, mixed>|null
	 */
	public function insert( array $data ): ?array {
		global $wpdb;
		$now  = gmdate( 'Y-m-d H:i:s' );
		$uuid = isset( $data['uuid'] ) && preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', (string) $data['uuid'] ) ? (string) $data['uuid'] : Uuid::v4();
		// A duplicate uuid is an expected replay; keep it out of the output and the error log.
		$suppress = method_exists( $wpdb, 'suppress_errors' ) ? $wpdb->suppress_errors( true ) : null;
		$ok       = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$this->table(),
			array_filter(
				array(
					'uuid'         => $uuid,
					'context_type' => $data['context_type'],
					'context_id'   => $data['context_id'],
					'question'     => $data['question'],
					'author_name'  => $data['author_name'],
					'author_email' => $data['author_email'],
					'status'       => 'pending',
					'created_at'   => $now,
					'updated_at'   => $now,
				),
				static fn( $v ) => null !== $v
			)
		);
		if ( null !== $suppress ) {
			$wpdb->suppress_errors( $suppress );
		}
		return false === $ok ? null : $this->find_by_uuid( $uuid );
	}

	/**
	 * Find by ID.
	 *
	 * @param int $id ID.
	 * @return array<string, mixed>|null
	 */
	public function find( int $id ): ?array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table()} WHERE id = %d", $id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Find by UUID.
	 *
	 * @param string $uuid UUID.
	 * @return array<string, mixed>|null
	 */
	public function find_by_uuid( string $uuid ): ?array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table()} WHERE uuid = %s", $uuid ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Approved questions of a context (public output).
	 *
	 * @param string $type  Context type.
	 * @param string $id    Context ID.
	 * @param int    $limit Limit (≤ 50).
	 * @return list<array<string, mixed>>
	 */
	public function public_for_context( string $type, string $id, int $limit = 20 ): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->table()} WHERE context_type = %s AND context_id = %s AND status = 'approved' ORDER BY created_at DESC, id DESC LIMIT %d", $type, $id, max( 1, min( 50, $limit ) ) ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Admin list.
	 *
	 * @param string $status Status filter ('' = all).
	 * @param int    $limit  Limit.
	 * @param int    $offset Offset.
	 * @return list<array<string, mixed>>
	 */
	public function query( string $status = '', int $limit = 50, int $offset = 0 ): array {
		global $wpdb;
		$limit  = max( 1, min( 200, $limit ) );
		$offset = max( 0, $offset );
		if ( in_array( $status, self::STATUSES, true ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->table()} WHERE status = %s ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d", $status, $limit, $offset ), ARRAY_A );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->table()} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d", $limit, $offset ), ARRAY_A );
		}
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count by status.
	 *
	 * @return array<string, int>
	 */
	public function counts(): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( "SELECT status, COUNT(*) AS cnt FROM {$this->table()} GROUP BY status", ARRAY_A );
		$out  = array_fill_keys( self::STATUSES, 0 );
		foreach ( (array) $rows as $row ) {
			$out[ (string) $row['status'] ] = (int) $row['cnt'];
		}
		return $out;
	}

	/**
	 * Moderate (CAS on the current status).
	 *
	 * @param int    $id   ID.
	 * @param string $from Expected status.
	 * @param string $to   Target status.
	 */
	public function change_status( int $id, string $from, string $to ): bool {
		global $wpdb;
		if ( ! in_array( $to, self::STATUSES, true ) || $from === $to ) {
			return $from === $to;
		}
		return 1 === (int) $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			array(
				'status'     => $to,
				'updated_at' => gmdate( 'Y-m-d H:i:s' ),
			),
			array(
				'id'     => $id,
				'status' => $from,
			)
		);
	}

	/**
	 * Set or clear the answer.
	 *
	 * @param int    $id      ID.
	 * @param string $answer  Answer text (plain text; '' clears).
	 * @param int    $user_id Answering user.
	 */
	public function answer( int $id, string $answer, int $user_id ): bool {
		global $wpdb;
		$answer = trim( $answer );
		return false !== $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			array(
				'answer'      => '' !== $answer ? $answer : null,
				'answered_by' => '' !== $answer && $user_id > 0 ? $user_id : null,
				'answered_at' => '' !== $answer ? gmdate( 'Y-m-d H:i:s' ) : null,
				'updated_at'  => gmdate( 'Y-m-d H:i:s' ),
			),
			array( 'id' => $id )
		);
	}

	/**
	 * Delete.
	 *
	 * @param int $id ID.
	 */
	public function delete( int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( $this->table(), array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Questions asked with an e-mail address (privacy export/eraser).
	 *
	 * @param string $email E-mail.
	 * @param int    $limit Limit.
	 * @return list<array<string, mixed>>
	 */
	public function by_email( string $email, int $limit = 100 ): array {
		global $wpdb;
		$email = trim( $email );
		if ( '' === $email ) {
			return array();
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->table()} WHERE author_email = %s ORDER BY id ASC LIMIT %d", $email, max( 1, $limit ) ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Anonymise one question (name and e-mail removed; the text stays because it is shown with
	 * an operator answer – erase mode "delete" removes the row instead).
	 *
	 * @param int $id ID.
	 */
	public function anonymize( int $id ): bool {
		global $wpdb;
		return false !== $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			array(
				'author_name'  => __( 'Anonymous', 'review-captain' ),
				'author_email' => null,
				'updated_at'   => gmdate( 'Y-m-d H:i:s' ),
			),
			array( 'id' => $id )
		);
	}
}
