<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Admin;

use ReviewCaptain\Admin\ReviewActions;
use ReviewCaptain\Admin\ReviewEditor;
use ReviewCaptain\Admin\ReviewsListTable;
use ReviewCaptain\Admin\ReviewsPage;
use ReviewCaptain\Domain\Kind;
use ReviewCaptain\Domain\Origin;
use ReviewCaptain\Domain\Platform;
use ReviewCaptain\Domain\Review;
use ReviewCaptain\Domain\ReviewStatus;
use ReviewCaptain\Domain\Verification;
use ReviewCaptain\Security\Capabilities;

final class ReviewActionsTest extends AdminTestCase {

	private function make( array $overrides = array() ): Review {
		$r              = new Review();
		$r->body        = 'Sehr gute Beratung und schnelle Lieferung.';
		$r->author_name = 'Anna';
		$r->rating_value = 4.0;
		foreach ( $overrides as $k => $v ) {
			$r->$k = $v;
		}
		return $this->plugin()->reviews()->insert( $r );
	}

	private function actions(): ReviewActions {
		return new ReviewActions( $this->plugin() );
	}

	public function test_bulk_without_capability_is_rejected(): void {
		$id = (int) $this->make()->id;
		$this->login_with( 'edit_posts' );
		$this->post( 'bulk_reviews', array( 'rc_bulk' => '1', 'action' => 'approve', 'review' => array( $id ) ) );
		$msg = $this->run_handler( array( $this->actions(), 'maybe_handle_bulk' ) );
		$this->assertStringNotContainsString( 'redirect:', $msg );
		$this->assertSame( ReviewStatus::PENDING, $this->plugin()->reviews()->find( $id )->status );
	}

	public function test_bulk_without_nonce_is_rejected(): void {
		$id = (int) $this->make()->id;
		$this->login_with( Capabilities::MODERATE );
		$this->post( 'bulk_reviews', array( 'rc_bulk' => '1', 'action' => 'approve', 'review' => array( $id ) ), false );
		$this->assertSame( 'nonce_failed', $this->run_handler( array( $this->actions(), 'maybe_handle_bulk' ) ) );
		$this->assertSame( ReviewStatus::PENDING, $this->plugin()->reviews()->find( $id )->status );
	}

	public function test_bulk_approve_and_reject_with_reason(): void {
		$a = (int) $this->make()->id;
		$b = (int) $this->make()->id;
		$this->login_with( Capabilities::MODERATE );

		$this->post( 'bulk_reviews', array( 'rc_bulk' => '1', 'action' => '-1', 'action2' => 'approve', 'review' => array( $a, $b ) ) );
		$msg = $this->run_handler( array( $this->actions(), 'maybe_handle_bulk' ) );
		$this->assertStringContainsString( 'rc_notice=bulk_done', $msg );
		$this->assertStringContainsString( 'rc_count=2', $msg );

		$this->post( 'bulk_reviews', array( 'rc_bulk' => '1', 'action' => 'reject', 'review' => array( $a ) ) );
		$this->assertStringContainsString( 'reason_required', $this->run_handler( array( $this->actions(), 'maybe_handle_bulk' ) ) );
		$this->assertSame( ReviewStatus::APPROVED, $this->plugin()->reviews()->find( $a )->status );

		$this->post( 'bulk_reviews', array( 'rc_bulk' => '1', 'action' => 'reject', 'bulk_reason' => 'Spam', 'review' => array( $a ) ) );
		$this->run_handler( array( $this->actions(), 'maybe_handle_bulk' ) );
		$this->assertSame( ReviewStatus::REJECTED, $this->plugin()->reviews()->find( $a )->status );
		$this->assertSame( 'Spam', $this->plugin()->reviews()->history( $a )[0]['reason'] );
	}

	public function test_non_bulk_request_is_ignored(): void {
		$this->login_with();
		$_POST = array();
		$this->actions()->maybe_handle_bulk();
		$this->addToAssertionCount( 1 );
	}

	public function test_status_change_requires_reason_for_archive_and_respects_matrix(): void {
		$id = (int) $this->make()->id;
		$this->login_with( Capabilities::MODERATE );
		$this->assertSame( 'reason_required', $this->actions()->change_status( $id, ReviewStatus::ARCHIVED, ' ' ) );
		$this->assertSame( 'status_failed', $this->actions()->change_status( $id, ReviewStatus::REMOVED, 'x' ) );
		$this->assertSame( 'status_changed', $this->actions()->change_status( $id, ReviewStatus::ARCHIVED, 'Veraltet' ) );
		$this->assertSame( 'status_failed', $this->actions()->change_status( $id, ReviewStatus::REJECTED, 'x' ), 'archived → rejected not allowed' );

		$this->post( 'review_status', array( 'id' => $id, 'to' => 'approved' ) );
		$this->assertStringContainsString( 'status_changed', $this->run_handler( array( $this->actions(), 'handle_status' ) ) );
		$this->assertSame( ReviewStatus::APPROVED, $this->plugin()->reviews()->find( $id )->status );
	}

	public function test_verification_cannot_be_set_to_purchase_verified_in_admin(): void {
		[ $review, $errors ] = ReviewEditor::create(
			array(
				'type'         => ReviewEditor::TYPE_LOCAL,
				'body'         => 'Toller Kurs.',
				'author_name'  => 'Ben',
				'verification' => 'purchase_verified',
			)
		);
		$this->assertSame( array(), $errors );
		$this->assertSame( Verification::UNVERIFIED, $review->verification );

		$this->login_with( Capabilities::MODERATE );
		$id = (int) $this->make()->id;
		$this->post( 'save_review', array( 'id' => $id, 'review' => array( 'body' => 'Neuer Text', 'author_name' => 'Anna', 'verification' => 'purchase_verified' ) ) );
		$this->run_handler( array( $this->actions(), 'handle_save' ) );
		$saved = $this->plugin()->reviews()->find( $id );
		$this->assertSame( Verification::UNVERIFIED, $saved->verification );
		$this->assertSame( 'Neuer Text', $saved->body );
	}

	public function test_local_edit_writes_audit_with_field_names_only(): void {
		$this->login_with( Capabilities::MODERATE );
		$id = (int) $this->make()->id;
		$this->post( 'save_review', array( 'id' => $id, 'review' => array( 'body' => 'Geheimer neuer Inhalt', 'author_name' => 'Anna', 'rating_value' => '4' ) ) );
		$this->assertStringContainsString( 'review_saved', $this->run_handler( array( $this->actions(), 'handle_save' ) ) );
		$rows = $this->audit_rows( 'review.edited' );
		$this->assertCount( 1, $rows );
		$context = json_decode( $rows[0]['context'], true );
		$this->assertContains( 'body', $context['fields'] );
		$this->assertStringNotContainsString( 'Geheimer', $rows[0]['context'] );
	}

	public function test_manual_external_review_requires_https_link_and_provenance(): void {
		$base = array(
			'type'             => ReviewEditor::TYPE_EXTERNAL,
			'platform'         => 'google',
			'rating_value'     => '4',
			'rating_scale_min' => '1',
			'rating_scale_max' => '5',
			'body'             => 'Freundliches Team.',
			'author_name'      => 'C. D.',
			'published_at'     => '2026-11-01',
			'source_note'      => 'Auszug aus Google',
		);
		[ $review, $errors ] = ReviewEditor::create( $base + array( 'source_url' => 'http://maps.example.test/review/1' ) );
		$this->assertNull( $review );
		$this->assertContains( 'source_url_not_https', $errors );

		[ $review, $errors ] = ReviewEditor::create( array( 'type' => ReviewEditor::TYPE_EXTERNAL, 'body' => 'x', 'author_name' => 'y' ) );
		$this->assertNull( $review );
		foreach ( array( 'missing_platform', 'missing_rating', 'missing_source_url', 'missing_source_note', 'missing_date' ) as $code ) {
			$this->assertContains( $code, $errors );
		}

		$this->login_with( Capabilities::MODERATE );
		$this->post( 'save_review', array( 'review' => $base + array( 'source_url' => 'javascript:alert(1)' ) ) );
		$this->assertStringContainsString( 'review_invalid', $this->run_handler( array( $this->actions(), 'handle_save' ) ) );
		$this->assertSame( 0, $this->rows( 'reviews' ) );

		$this->post( 'save_review', array( 'review' => $base + array( 'source_url' => 'https://maps.example.test/review/1', 'verification' => 'purchase_verified' ) ) );
		$this->assertStringContainsString( 'review_created', $this->run_handler( array( $this->actions(), 'handle_save' ) ) );
		$saved = $this->plugin()->reviews()->query( array( 'limit' => 1 ) )[0];
		$this->assertSame( Origin::MANUAL, $saved->origin );
		$this->assertSame( Platform::GOOGLE, $saved->platform );
		$this->assertSame( Verification::EXTERNAL_PLATFORM, $saved->verification );
		$this->assertSame( 'Auszug aus Google', $saved->meta['source_note'] );
		$this->assertSame( '2026-11-01 00:00:00', $saved->published_at );
	}

	public function test_external_content_is_not_editable(): void {
		$this->login_with( Capabilities::MODERATE );
		$review = $this->make(
			array(
				'platform'     => Platform::TRUSTPILOT,
				'verification' => Verification::EXTERNAL_PLATFORM,
				'source_url'   => 'https://www.trustpilot.example/review/1',
				'body'         => 'Original',
				'rating_value' => 3.0,
			)
		);
		$this->post( 'save_review', array( 'id' => $review->id, 'review' => array( 'body' => 'Geändert', 'rating_value' => '5', 'context_type' => 'product', 'context_id' => '12' ) ) );
		$this->run_handler( array( $this->actions(), 'handle_save' ) );
		$saved = $this->plugin()->reviews()->find( (int) $review->id );
		$this->assertSame( 'Original', $saved->body );
		$this->assertSame( 3.0, $saved->rating_value );
		$this->assertSame( 'product', $saved->context_type );
	}

	public function test_testimonial_is_marked_as_website_own(): void {
		[ $review ] = ReviewEditor::create( array( 'type' => ReviewEditor::TYPE_TESTIMONIAL, 'body' => 'Wir sind begeistert.', 'author_name' => 'Firma X', 'platform' => 'google' ) );
		$this->assertSame( Kind::TESTIMONIAL, $review->kind );
		$this->assertSame( Platform::LOCAL, $review->platform );
		$this->assertTrue( $review->meta['website_testimonial'] );
	}

	public function test_list_and_edit_screens_render(): void {
		$this->login_with( Capabilities::MODERATE );
		$review = $this->make( array( 'body' => '<script>x</script>Hallo' ) );
		$this->make( array( 'platform' => Platform::GOOGLE, 'verification' => Verification::EXTERNAL_PLATFORM, 'source_url' => 'https://g.example/r', 'rating_value' => 9.0, 'rating_scale_max' => 10.0 ) );

		$_GET = array( 'page' => ReviewsPage::SLUG );
		ob_start();
		( new ReviewsPage( $this->plugin() ) )->render();
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( '9/10', $html, 'original scale' );
		$this->assertStringContainsString( '4/5', $html );
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( wp_create_nonce( 'review_captain_bulk_reviews' ), $html, 'bulk form carries nonce' );

		$_GET = array( 'page' => ReviewsPage::SLUG, 'action' => 'edit', 'id' => (string) $review->id );
		ob_start();
		( new ReviewsPage( $this->plugin() ) )->render();
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'Änderungsverlauf', $html );
		$this->assertStringContainsString( 'review_captain_review_status', $html );
		$this->assertStringNotContainsString( 'name="review[verification]"', $html );

		$_GET = array( 'page' => ReviewsPage::SLUG, 'action' => 'new', 'type' => 'external' );
		ob_start();
		( new ReviewsPage( $this->plugin() ) )->render();
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'name="review[source_note]"', $html );
	}

	public function test_list_query_args_from_filters(): void {
		$args = ReviewsListTable::query_args( array( 'status' => 'pending', 'kind' => 'testimonial', 'platform' => 'evil', 'rating' => '4', 'period' => '30', 's' => 'Lieferung' ) );
		$this->assertSame( 'pending', $args['status'] );
		$this->assertSame( 'testimonial', $args['kind'] );
		$this->assertArrayNotHasKey( 'platform', $args );
		$this->assertSame( 0.75, $args['min_normalized'] );
		$this->assertSame( 'Lieferung', $args['search'] );
		$this->assertArrayHasKey( 'date_from', $args );
		$all = ReviewsListTable::query_args( array() );
		$this->assertNotContains( 'removed', $all['status'] );
	}
}
