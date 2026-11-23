<?php
/**
 * Author corrections of approved local reviews (RC-125A, docs/decisions/author-correction.md).
 *
 * - Identity: one-time link to the stored author e-mail (channel "link") or the logged-in author
 *   (channel "user"). Never by name, cookie or IP.
 * - A correction is stored as a proposed revision; the public review stays unchanged until a
 *   moderator approves. Approval applies the fields with a revision CAS and marks the card
 *   "bearbeitet am …". Withdrawal by the author archives the review at once.
 * - Audit only IDs/codes, never texts.
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Moderation;

use ReviewCaptain\Domain\Origin;
use ReviewCaptain\Domain\Platform;
use ReviewCaptain\Domain\Review;
use ReviewCaptain\Domain\ReviewStatus;
use ReviewCaptain\Install\Schema;
use ReviewCaptain\Plugin;

/**
 * Correction workflow.
 */
class CorrectionService {

	// Defaults; effective values come from submissions.correction_link_hours and
	// privacy.retention_corrections_days (SET-RC-21).
	public const TOKEN_TTL     = HOUR_IN_SECONDS;
	public const MAX_LINKS     = 5;
	public const RETENTION_DAYS = 90;
	private const TOKEN_PREFIX = 'rc_corr_';

	/** How long a used one-time link answers a repeated request with its first result. */
	public const USED_TTL = 10 * MINUTE_IN_SECONDS;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( private Plugin $plugin ) {}

	/** Validity of a one-time link in seconds (setting submissions.correction_link_hours, 1–72 h). */
	public static function token_ttl(): int {
		$hours = (int) Plugin::instance()->settings()->get( 'submissions', 'correction_link_hours', 1 );
		return max( 1, min( 72, $hours ) ) * HOUR_IN_SECONDS;
	}

	/** Whether the operator enabled corrections. */
	public function enabled(): bool {
		return (bool) $this->plugin->settings()->get( 'submissions', 'corrections_enabled', false );
	}

	/** Only approved local reviews written on this site can be corrected. */
	public static function correctable( ?Review $r ): bool {
		return null !== $r && Platform::LOCAL === $r->platform && ReviewStatus::APPROVED === $r->status
			&& in_array( $r->origin, array( Origin::LOCAL_SUBMISSION, Origin::CONNECTOR ), true );
	}

	/**
	 * Create one-time links for all correctable reviews of an e-mail address. The caller always
	 * shows the same neutral message (no account enumeration).
	 *
	 * @param string $email E-mail.
	 * @return array<int, string> review_id => token (sent by mail; returned for tests).
	 */
	public function issue_links( string $email ): array {
		$email = sanitize_email( $email );
		if ( ! $this->enabled() || '' === $email || ! is_email( $email ) ) {
			return array();
		}
		$out = array();
		foreach ( $this->plugin->reviews()->query( array( 'author_email' => $email, 'status' => ReviewStatus::APPROVED->value, 'platform' => 'local', 'limit' => self::MAX_LINKS ) ) as $r ) {
			if ( ! self::correctable( $r ) ) {
				continue;
			}
			$token = rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			set_transient( self::TOKEN_PREFIX . hash( 'sha256', $token ), array( 'review' => (int) $r->id ), self::token_ttl() );
			$out[ (int) $r->id ] = $token;
		}
		return $out;
	}

	/**
	 * Review for a token (not consumed).
	 *
	 * @param string $token Token.
	 */
	public function review_for_token( string $token ): ?Review {
		if ( '' === $token || strlen( $token ) > 100 ) {
			return null;
		}
		$data = get_transient( self::TOKEN_PREFIX . hash( 'sha256', $token ) );
		$id   = is_array( $data ) && ! isset( $data['used'] ) ? (int) ( $data['review'] ?? 0 ) : 0;
		$r    = $id > 0 ? $this->plugin->reviews()->find( $id ) : null;
		return self::correctable( $r ) ? $r : null;
	}

	/**
	 * Consume a token (single use). With a result code the used token is remembered for a few
	 * minutes, so a double click or a resent form gets the same answer instead of "link expired"
	 * (review P2); it never opens the review again.
	 *
	 * @param string $token  Token.
	 * @param string $result Result code of the use (proposed|withdrawn), '' = forget at once.
	 */
	public function consume_token( string $token, string $result = '' ): void {
		$key = self::TOKEN_PREFIX . hash( 'sha256', $token );
		if ( '' === $result ) {
			delete_transient( $key );
			return;
		}
		set_transient( $key, array( 'used' => sanitize_key( $result ) ), self::USED_TTL );
	}

	/**
	 * Result code of an already used token, null if the token is unused, unknown or expired.
	 *
	 * @param string $token Token.
	 */
	public function used_result( string $token ): ?string {
		if ( '' === $token || strlen( $token ) > 100 ) {
			return null;
		}
		$data = get_transient( self::TOKEN_PREFIX . hash( 'sha256', $token ) );
		return is_array( $data ) && isset( $data['used'] ) && '' !== (string) $data['used'] ? (string) $data['used'] : null;
	}

	/**
	 * Review the current user may correct (channel "user").
	 *
	 * @param int $review_id Review ID.
	 */
	public function review_for_user( int $review_id ): ?Review {
		$uid = get_current_user_id();
		$r   = $uid > 0 ? $this->plugin->reviews()->find( $review_id ) : null;
		return self::correctable( $r ) && (int) $r->author_user_id === $uid ? $r : null;
	}

	/**
	 * Store a proposed correction. Older open proposals of the same review are superseded.
	 *
	 * @param Review               $r       Review.
	 * @param array<string, mixed> $fields  rating_value, title, body.
	 * @param string               $channel link|user.
	 * @return int|\WP_Error Revision ID.
	 */
	public function propose( Review $r, array $fields, string $channel ) {
		global $wpdb;
		// Titles off (submissions.allow_title): the stored title stays, a correction cannot add one.
		$settings = $this->plugin->settings();
		$rating   = isset( $fields['rating_value'] ) ? (float) $fields['rating_value'] : (float) $r->rating_value;
		$title    = $settings->get( 'submissions', 'allow_title', true ) ? sanitize_text_field( (string) ( $fields['title'] ?? $r->title ) ) : $r->title;
		$body     = trim( sanitize_textarea_field( (string) ( $fields['body'] ?? $r->body ) ) );
		$limits   = \ReviewCaptain\Frontend\FormHandler::text_limits( $settings );
		$min      = $limits[0];
		$max      = $limits[1];
		if ( $rating < 1 || $rating > 5 || fmod( $rating * 2, 1.0 ) !== 0.0 ) {
			return new \WP_Error( 'rc.form.invalid_input', 'rating' );
		}
		if ( $title !== $r->title && mb_strlen( $title ) > 150 ) {
			// Same limit as the form; never cut in bytes (a cut multibyte character breaks the row).
			return new \WP_Error( 'rc.form.invalid_input', 'title' );
		}
		if ( mb_strlen( $body ) < $min || mb_strlen( $body ) > $max ) {
			return new \WP_Error( 'rc.form.invalid_input', 'body' );
		}
		if ( $rating === (float) $r->rating_value && $title === $r->title && $body === $r->body ) {
			return new \WP_Error( 'rc.form.invalid_input', 'unchanged' );
		}
		$table = Schema::table( 'review_revisions' );
		$now   = gmdate( 'Y-m-d H:i:s' );
		$wpdb->update( $table, array( 'status' => 'superseded', 'decided_at' => $now, 'reason' => 'new_proposal' ), array( 'review_id' => (int) $r->id, 'status' => 'proposed' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ok = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'review_id'     => (int) $r->id,
				'base_revision' => (int) $r->revision,
				'status'        => 'proposed',
				'rating_value'  => $rating,
				'title'         => $title,
				'body'          => $body,
				'channel'       => 'user' === $channel ? 'user' : 'link',
				'created_at'    => $now,
			)
		);
		if ( ! $ok ) {
			return new \WP_Error( 'rc.internal.error', 'insert' );
		}
		$id = (int) $wpdb->insert_id;
		$this->plugin->audit()->record( 'review.correction_requested', 'review', (int) $r->id, array( 'mode' => $channel ) );
		return $id;
	}

	/**
	 * Revision row.
	 *
	 * @param int $id Revision ID.
	 * @return array<string, mixed>|null
	 */
	public function find( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Schema::table( 'review_revisions' ) . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Open proposals (moderation list).
	 *
	 * @return list<array<string, mixed>>
	 */
	public function open( int $limit = 50 ): array {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Schema::table( 'review_revisions' ) . " WHERE status = 'proposed' ORDER BY id ASC LIMIT %d", $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Approve: apply the proposal to the review (revision CAS) and keep the old text as superseded.
	 *
	 * @param int $id Revision ID.
	 * @return true|\WP_Error
	 */
	public function approve( int $id ) {
		global $wpdb;
		$rev = $this->find( $id );
		if ( null === $rev || 'proposed' !== $rev['status'] ) {
			return new \WP_Error( 'rc.request.duplicate', 'not_open' );
		}
		$repo = $this->plugin->reviews();
		$r    = $repo->find( (int) $rev['review_id'] );
		if ( ! self::correctable( $r ) ) {
			return new \WP_Error( 'rc.form.invalid_input', 'not_correctable' );
		}
		$table = Schema::table( 'review_revisions' );
		$now   = gmdate( 'Y-m-d H:i:s' );
		// Keep the published text as an earlier version (export/audit trail, retention 90 days).
		$wpdb->insert( $table, array( 'review_id' => (int) $r->id, 'base_revision' => (int) $r->revision, 'status' => 'superseded', 'rating_value' => $r->rating_value, 'title' => $r->title, 'body' => $r->body, 'channel' => (string) $rev['channel'], 'decided_at' => $now, 'reason' => 'replaced', 'created_at' => $now ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$r->rating_value         = (float) $rev['rating_value'];
		$r->title                = (string) $rev['title'];
		$r->body                 = (string) $rev['body'];
		$r->meta['edited_at']    = $now;
		$r->meta['edited_count'] = (int) ( $r->meta['edited_count'] ?? 0 ) + 1;
		if ( ! $repo->update( $r, false, true ) ) {
			return new \WP_Error( 'rc.lock.busy', 'revision_conflict' );
		}
		$wpdb->update( $table, array( 'status' => 'approved', 'decided_by' => get_current_user_id(), 'decided_at' => $now ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$this->plugin->audit()->record( 'review.correction_approved', 'review', (int) $r->id, array( 'from' => (int) $rev['base_revision'], 'to' => (int) $r->revision ) );
		return true;
	}

	/**
	 * Reject a proposal (public review unchanged).
	 *
	 * @param int    $id     Revision ID.
	 * @param string $reason Reason code.
	 */
	public function reject( int $id, string $reason = 'moderation' ): bool {
		global $wpdb;
		$rev = $this->find( $id );
		if ( null === $rev || 'proposed' !== $rev['status'] ) {
			return false;
		}
		$wpdb->update( Schema::table( 'review_revisions' ), array( 'status' => 'rejected', 'decided_by' => get_current_user_id(), 'decided_at' => gmdate( 'Y-m-d H:i:s' ), 'reason' => substr( sanitize_key( $reason ), 0, 40 ) ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$this->plugin->audit()->record( 'review.correction_rejected', 'review', (int) $rev['review_id'], array( 'reason' => sanitize_key( $reason ) ) );
		return true;
	}

	/**
	 * Withdrawal by the author: archived at once (consent withdrawn), open proposals superseded.
	 *
	 * @param Review $r Review.
	 */
	public function withdraw( Review $r ): bool {
		global $wpdb;
		if ( ! $this->plugin->reviews()->change_status( (int) $r->id, ReviewStatus::ARCHIVED, 'author_withdrawn', true ) ) {
			return false;
		}
		$wpdb->update( Schema::table( 'review_revisions' ), array( 'status' => 'superseded', 'decided_at' => gmdate( 'Y-m-d H:i:s' ), 'reason' => 'withdrawn' ), array( 'review_id' => (int) $r->id, 'status' => 'proposed' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$this->plugin->audit()->record( 'review.withdrawn_by_author', 'review', (int) $r->id, array( 'reason' => 'author_withdrawn' ) );
		return true;
	}

	/**
	 * Retention: decided revisions older than $days (privacy.retention_corrections_days) are deleted.
	 *
	 * @param int $days Days after the decision (default RETENTION_DAYS).
	 */
	public static function purge_old( int $days = self::RETENTION_DAYS ): int {
		global $wpdb;
		$cut = gmdate( 'Y-m-d H:i:s', time() - max( 1, $days ) * DAY_IN_SECONDS );
		return max( 0, (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . Schema::table( 'review_revisions' ) . " WHERE status <> 'proposed' AND decided_at IS NOT NULL AND decided_at < %s", $cut ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * All versions of a review (privacy export).
	 *
	 * @param int $review_id Review ID.
	 * @return list<array<string, mixed>>
	 */
	public static function versions( int $review_id ): array {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT status, rating_value, title, body, created_at, decided_at FROM ' . Schema::table( 'review_revisions' ) . ' WHERE review_id = %d ORDER BY id ASC', $review_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		return is_array( $rows ) ? $rows : array();
	}
}
