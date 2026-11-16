<?php
/**
 * Write handlers for reviews: create/edit, moderation, bulk moderation.
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Admin;

use ReviewCaptain\Domain\ReviewStatus;
use ReviewCaptain\Plugin;
use ReviewCaptain\Security\Capabilities;

/**
 * Every handler checks capability + nonce first (Capabilities::verify_request) and ends with
 * a safe redirect carrying a status code (PRG).
 */
final class ReviewActions {

	public const BULK_ACTIONS = array(
		'approve' => ReviewStatus::APPROVED,
		'reject'  => ReviewStatus::REJECTED,
		'archive' => ReviewStatus::ARCHIVED,
	);

	/**
	 * Transient prefix for re-populating a form after a validation error.
	 */
	private const DRAFT_TRANSIENT = 'review_captain_review_draft_';

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( private Plugin $plugin ) {}

	/**
	 * Whether a target status needs a reason.
	 *
	 * @param ReviewStatus $to Target.
	 */
	public static function needs_reason( ReviewStatus $to ): bool {
		return ReviewStatus::REJECTED === $to || ReviewStatus::ARCHIVED === $to;
	}

	/**
	 * Saved draft for the current user (consumed on read).
	 *
	 * @return array<string, mixed>
	 */
	public static function take_draft(): array {
		$key   = self::DRAFT_TRANSIENT . get_current_user_id();
		$draft = get_transient( $key );
		delete_transient( $key );
		return is_array( $draft ) ? $draft : array();
	}

	/**
	 * Handler: create or update a review (admin-post: review_captain_save_review).
	 */
	public function handle_save(): void {
		Capabilities::verify_request( Capabilities::MODERATE, 'save_review' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$input = isset( $_POST['review'] ) && is_array( $_POST['review'] ) ? wp_unslash( $_POST['review'] ) : array();
		$id    = absint( $_POST['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$repo  = $this->plugin->reviews();
		$list  = View::page_url( ReviewsPage::SLUG );

		if ( $id > 0 ) {
			$review = $repo->find( $id );
			if ( null === $review ) {
				View::redirect( Notices::url( $list, 'review_missing' ) );
				return;
			}
			[ $fields, $errors ] = ReviewEditor::apply_edit( $review, $input );
			$edit_url            = View::page_url( ReviewsPage::SLUG, array( 'action' => 'edit', 'id' => $id ) );
			if ( $errors ) {
				$this->store_draft( $input );
				View::redirect( Notices::url( $edit_url, 'review_invalid', $errors ) );
				return;
			}
			if ( ! $fields ) {
				View::redirect( Notices::url( $edit_url, 'review_unchanged' ) );
				return;
			}
			try {
				$repo->update( $review );
			} catch ( \InvalidArgumentException $e ) {
				$this->store_draft( $input );
				View::redirect( Notices::url( $edit_url, 'review_invalid', explode( ',', $e->getMessage() ) ) );
				return;
			}
			// History + audit: only field names, never content.
			$this->plugin->reviews()->record_edit( $id, $fields );
			$this->plugin->audit()->record(
				'review.edited',
				'review',
				$id,
				array(
					'fields'   => $fields,
					'platform' => $review->platform->value,
					'kind'     => $review->kind->value,
				)
			);
			View::redirect( Notices::url( $edit_url, 'review_saved' ) );
			return;
		}

		[ $review, $errors ] = ReviewEditor::create( $input );
		$type                = sanitize_key( (string) ( $input['type'] ?? ReviewEditor::TYPE_LOCAL ) );
		$new_url             = View::page_url( ReviewsPage::SLUG, array( 'action' => 'new', 'type' => $type ) );
		if ( null === $review ) {
			$this->store_draft( $input );
			View::redirect( Notices::url( $new_url, 'review_invalid', $errors ) );
			return;
		}
		try {
			$review = $repo->insert( $review );
		} catch ( \Throwable $e ) {
			$this->store_draft( $input );
			View::redirect( Notices::url( $new_url, 'review_invalid', array( 'save_failed' ) ) );
			return;
		}
		$this->plugin->audit()->record(
			'review.created_manual',
			'review',
			(int) $review->id,
			array(
				'platform' => $review->platform->value,
				'kind'     => $review->kind->value,
				'status'   => $review->status->value,
			)
		);
		View::redirect( Notices::url( View::page_url( ReviewsPage::SLUG, array( 'action' => 'edit', 'id' => $review->id ) ), 'review_created' ) );
	}

	/**
	 * Handler: change status of one review (admin-post: review_captain_review_status).
	 */
	public function handle_status(): void {
		Capabilities::verify_request( Capabilities::MODERATE, 'review_status' );
		// phpcs:disable WordPress.Security.NonceVerification -- verified above.
		$id     = absint( $_REQUEST['id'] ?? 0 );
		$to     = ReviewStatus::tryFrom( sanitize_key( wp_unslash( (string) ( $_REQUEST['to'] ?? '' ) ) ) );
		$reason = sanitize_text_field( wp_unslash( (string) ( $_REQUEST['reason'] ?? '' ) ) );
		$back   = sanitize_key( wp_unslash( (string) ( $_REQUEST['back'] ?? '' ) ) );
		// phpcs:enable
		$target = 'list' === $back ? View::page_url( ReviewsPage::SLUG ) : View::page_url( ReviewsPage::SLUG, array( 'action' => 'edit', 'id' => $id ) );
		$code   = $this->change_status( $id, $to, $reason );
		View::redirect( Notices::url( $target, $code ) );
	}

	/**
	 * Change status with moderation matrix + reason rule. Returns a notice code.
	 *
	 * @param int               $id     Review ID.
	 * @param ReviewStatus|null $to     Target.
	 * @param string            $reason Reason.
	 */
	public function change_status( int $id, ?ReviewStatus $to, string $reason ): string {
		$review = $this->plugin->reviews()->find( $id );
		if ( null === $review ) {
			return 'review_missing';
		}
		if ( null === $to || ReviewStatus::REMOVED === $to || ! $review->status->can_transition_to( $to ) ) {
			return 'status_failed';
		}
		if ( self::needs_reason( $to ) && '' === trim( $reason ) ) {
			return 'reason_required';
		}
		return $this->plugin->reviews()->change_status( $id, $to, $reason ) ? 'status_changed' : 'status_failed';
	}

	/**
	 * Handler: bulk moderation from the list table (load-{page} hook, POST).
	 * Returns silently if the request is not a bulk request.
	 */
	public function maybe_handle_bulk(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- only detection here; verified in handle_bulk().
		if ( ! isset( $_POST['rc_bulk'] ) ) {
			return;
		}
		// phpcs:enable
		$this->handle_bulk();
	}

	/**
	 * Bulk moderation. Capability + nonce are checked before anything is read.
	 */
	public function handle_bulk(): void {
		Capabilities::verify_request( Capabilities::MODERATE, 'bulk_reviews' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above.
		$action = sanitize_key( wp_unslash( (string) ( $_POST['action'] ?? '-1' ) ) );
		if ( '' === $action || '-1' === $action || ! isset( self::BULK_ACTIONS[ $action ] ) ) {
			$action = sanitize_key( wp_unslash( (string) ( $_POST['action2'] ?? '-1' ) ) );
		}
		$ids    = isset( $_POST['review'] ) && is_array( $_POST['review'] ) ? array_values( array_unique( array_filter( array_map( 'absint', wp_unslash( $_POST['review'] ) ) ) ) ) : array();
		$reason = sanitize_text_field( wp_unslash( (string) ( $_POST['bulk_reason'] ?? '' ) ) );
		$view   = sanitize_key( wp_unslash( (string) ( $_POST['status'] ?? '' ) ) );
		// phpcs:enable
		$list = View::page_url( ReviewsPage::SLUG, '' !== $view ? array( 'status' => $view ) : array() );

		if ( ! isset( self::BULK_ACTIONS[ $action ] ) && ! $ids ) {
			// Form submitted without a bulk action (e.g. the page-number field): just navigate.
			$paged = absint( $_POST['paged'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			View::redirect( $paged > 1 ? add_query_arg( 'paged', $paged, $list ) : $list );
			return;
		}
		if ( ! isset( self::BULK_ACTIONS[ $action ] ) || ! $ids ) {
			View::redirect( Notices::url( $list, 'bulk_none' ) );
			return;
		}
		$to = self::BULK_ACTIONS[ $action ];
		if ( self::needs_reason( $to ) && '' === trim( $reason ) ) {
			View::redirect( Notices::url( $list, 'reason_required' ) );
			return;
		}
		$changed = 0;
		foreach ( array_slice( $ids, 0, 200 ) as $id ) {
			if ( 'status_changed' === $this->change_status( $id, $to, $reason ) ) {
				++$changed;
			}
		}
		View::redirect( $changed > 0 ? Notices::url( $list, 'bulk_done', array(), $changed ) : Notices::url( $list, 'bulk_none' ) );
	}

	/**
	 * Keep sanitized form input for re-display (short-lived, per user).
	 *
	 * @param array<string, mixed> $input Input.
	 */
	private function store_draft( array $input ): void {
		$clean = array();
		foreach ( $input as $key => $value ) {
			if ( is_scalar( $value ) ) {
				$clean[ sanitize_key( (string) $key ) ] = 'body' === $key ? sanitize_textarea_field( (string) $value ) : sanitize_text_field( (string) $value );
			}
		}
		set_transient( self::DRAFT_TRANSIENT . get_current_user_id(), $clean, 5 * MINUTE_IN_SECONDS );
	}
}
