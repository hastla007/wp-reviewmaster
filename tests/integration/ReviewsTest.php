<?php
/**
 * Reviews: creation paths, moderation, verification, external curation, privacy (RC-039 – RC-043, RC-047).
 *
 * @package ReviewCaptain
 */

use ReviewCaptain\Privacy\PrivacyIntegration;
use ReviewCaptain\Reviews\ReviewRepository;
use ReviewCaptain\Reviews\ReviewService;
use ReviewCaptain\Settings\Settings;

class ReviewsTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		Settings::reset_all();
	}

	private function local( array $over = array() ) {
		return ReviewService::create_local(
			array_merge(
				array(
					'rating'      => 5,
					'title'       => 'Great',
					'content'     => 'Very good service.',
					'author_name' => 'Anna Muster',
					'email'       => 'anna@example.org',
				),
				$over
			)
		);
	}

	public function test_local_review_is_pending_and_unverified_by_default(): void {
		$id = $this->local();
		$this->assertIsInt( $id );
		$r = ReviewRepository::find( $id );
		$this->assertSame( 'pending', $r['status'] );
		$this->assertSame( 'unverified', $r['verification'] );
		$this->assertSame( 'site', $r['provider'] );
		$this->assertNotEmpty( $r['author_email_hash'] );
		$this->assertStringNotContainsString( 'anna@', wp_json_encode( $r ) );
	}

	public function test_local_review_validation(): void {
		$this->assertWPError( $this->local( array( 'rating' => 7 ) ) );
		$this->assertWPError( $this->local( array( 'author_name' => '' ) ) );
		$this->assertWPError( $this->local( array( 'email' => 'not-an-email' ) ) );
		update_option( 'review_captain_submissions', array_merge( Settings::group( 'submissions' ), array( 'min_text_length' => 50 ) ) );
		$this->assertWPError( $this->local() );
	}

	public function test_html_is_stripped_from_local_review(): void {
		$id = $this->local( array( 'content' => '<script>alert(1)</script>Hello <b>world</b>' ) );
		$this->assertStringNotContainsString( '<script', (string) ReviewRepository::find( $id )['content'] );
	}

	public function test_moderation_transitions_and_history(): void {
		$id = $this->local();
		$this->assertTrue( ReviewService::moderate( $id, 'approved', 'ok' ) );
		$this->assertSame( 'approved', ReviewRepository::find( $id )['status'] );
		$this->assertWPError( ReviewService::moderate( $id, 'spam' ) ); // approved -> spam not allowed.
		$this->assertTrue( ReviewService::moderate( $id, 'archived' ) );
		$actions = wp_list_pluck( ReviewRepository::history( $id ), 'action' );
		$this->assertSame( array( 'created', 'approved', 'archived' ), $actions );
		$this->assertSame( 'ok', ReviewRepository::history( $id )[1]['reason'] );
	}

	public function test_tombstoned_review_cannot_be_published(): void {
		$id = $this->local();
		ReviewService::tombstone( $id );
		$this->assertWPError( ReviewService::moderate( $id, 'approved' ) );
	}

	public function test_verification_only_by_trusted_source(): void {
		$id = $this->local();
		$this->assertWPError( ReviewService::set_verification( $id, 'form-captain:entry', '1' ) );
		$this->assertTrue( ReviewService::set_verification( $id, 'woocommerce:order', '123' ) );
		$r = ReviewRepository::find( $id );
		$this->assertSame( 'purchase', $r['verification'] );
		$this->assertSame( 'woocommerce:order:123', $r['verification_ref'] );

		$ext = ReviewService::create_manual_external( $this->external() );
		$this->assertWPError( ReviewService::set_verification( $ext, 'woocommerce:order', '1' ) );
	}

	private function external( array $over = array() ): array {
		return array_merge(
			array(
				'provider'     => 'google',
				'rating_value' => 4,
				'rating_min'   => 1,
				'rating_max'   => 5,
				'content'      => 'Nice place',
				'author_name'  => 'T. K.',
				'published_at' => '2026-10-01',
				'source_url'   => 'https://maps.google.com/example',
				'attribution'  => 'Review on Google',
			),
			$over
		);
	}

	public function test_manual_external_requires_fields(): void {
		$this->assertWPError( ReviewService::create_manual_external( $this->external( array( 'source_url' => 'http://insecure.example' ) ) ) );
		$this->assertWPError( ReviewService::create_manual_external( $this->external( array( 'attribution' => '' ) ) ) );
		$this->assertWPError( ReviewService::create_manual_external( $this->external( array( 'rating_value' => 9 ) ) ) );
		$this->assertWPError( ReviewService::create_manual_external( $this->external( array( 'provider' => 'site' ) ) ) );
		$id = ReviewService::create_manual_external( $this->external( array( 'rating_value' => 8, 'rating_max' => 10 ) ) );
		$this->assertIsInt( $id );
		$r = ReviewRepository::find( $id );
		$this->assertSame( 'external', $r['verification'] );
		$this->assertSame( '10', (string) $r['rating_max'] );
		$this->assertSame( 'approved', $r['status'] );
	}

	public function test_imported_platform_reviews_never_touch_woocommerce_comments(): void {
		$before = (int) get_comments( array( 'count' => true ) );
		ReviewService::create_manual_external( $this->external() );
		$this->local();
		$this->assertSame( $before, (int) get_comments( array( 'count' => true ) ) );
	}

	public function test_connector_review_is_idempotent_pending_and_unverified(): void {
		$payload = array(
			'rating'          => 4,
			'review_text'     => 'From a form',
			'display_name'    => 'Max',
			'source_plugin'   => 'form-captain',
			'source_entry_id' => '55',
			'idempotency_key' => 'form-captain:1:55',
		);
		$first  = wp_review_captain_create_local_review_v1( $payload );
		$second = wp_review_captain_create_local_review_v1( $payload );
		$this->assertTrue( $first['success'] );
		$this->assertSame( 'duplicate', $second['code'] );
		$r = ReviewRepository::find( $first['review_id'] );
		$this->assertSame( 'pending', $r['status'] );
		$this->assertSame( 'unverified', $r['verification'] );

		$this->assertSame( 'invalid_payload', wp_review_captain_create_local_review_v1( array_merge( $payload, array( 'idempotency_key' => 'x2', 'verified' => true ) ) )['code'] );
		$this->assertSame( 'invalid_payload', wp_review_captain_create_local_review_v1( array_merge( $payload, array( 'idempotency_key' => 'x3', 'rating' => '4.5' ) ) )['code'] );

		$this->assertTrue( ReviewService::delete_pending_by_idempotency_key( 'form-captain:1:55' ) );
		$this->assertNull( ReviewRepository::find( $first['review_id'] ) );
	}

	public function test_public_filter_hides_tombstoned_and_stale_sync(): void {
		$ok    = ReviewRepository::insert( array( 'provider' => 'google', 'origin' => 'sync', 'status' => 'approved', 'fetched_at' => gmdate( 'Y-m-d H:i:s' ) ) );
		$stale = ReviewRepository::insert( array( 'provider' => 'google', 'origin' => 'sync', 'status' => 'approved', 'fetched_at' => gmdate( 'Y-m-d H:i:s', time() - 3 * DAY_IN_SECONDS ) ) );
		$tomb  = ReviewRepository::insert( array( 'provider' => 'google', 'origin' => 'sync', 'status' => 'approved', 'fetched_at' => gmdate( 'Y-m-d H:i:s' ), 'tombstoned_at' => gmdate( 'Y-m-d H:i:s' ) ) );
		$ids   = wp_list_pluck( ReviewRepository::query( array( 'public' => true ), 50 ), 'id' );
		$this->assertContains( (string) $ok, $ids );
		$this->assertNotContains( (string) $stale, $ids );
		$this->assertNotContains( (string) $tomb, $ids );
	}

	public function test_summary_is_per_provider_and_scale(): void {
		ReviewService::create_manual_external( $this->external( array( 'rating_value' => 4 ) ) );
		ReviewService::create_manual_external( $this->external( array( 'rating_value' => 2 ) ) );
		ReviewService::create_manual_external( $this->external( array( 'provider' => 'trustpilot', 'rating_value' => 5 ) ) );
		$s = ReviewRepository::summary_by_provider();
		$this->assertSame( 3.0, $s['google']['average'] );
		$this->assertSame( 2, $s['google']['count'] );
		$this->assertSame( 5.0, $s['trustpilot']['average'] );
	}

	public function test_privacy_export_and_erase(): void {
		$id      = $this->local();
		$pending = $this->local( array( 'title' => 'second' ) );
		ReviewService::moderate( $id, 'approved' );
		$export = PrivacyIntegration::export( 'anna@example.org' );
		$this->assertCount( 2, $export['data'] );
		$this->assertTrue( $export['done'] );

		update_option( 'review_captain_privacy', array_merge( Settings::group( 'privacy' ), array( 'erase_review_text_on_request' => false ) ) );
		$result = PrivacyIntegration::erase( 'anna@example.org' );
		$this->assertSame( 2, $result['items_removed'] );
		$this->assertNull( ReviewRepository::find( $pending ) ); // Pending is deleted.
		$kept = ReviewRepository::find( $id );
		$this->assertSame( 'Anonymous', $kept['author_name'] );
		$this->assertNull( $kept['author_email_hash'] );
		$this->assertCount( 0, PrivacyIntegration::export( 'anna@example.org' )['data'] );
	}

	public function test_retention_deletes_old_rejected_reviews(): void {
		global $wpdb;
		$id = $this->local();
		ReviewService::moderate( $id, 'rejected' );
		$wpdb->update( \ReviewCaptain\Database\Schema::table( 'rc_reviews' ), array( 'updated_at' => gmdate( 'Y-m-d H:i:s', time() - 40 * DAY_IN_SECONDS ) ), array( 'id' => $id ) );
		$keep = $this->local( array( 'title' => 'keep' ) );
		$result = \ReviewCaptain\Maintenance\Retention::run();
		$this->assertSame( 1, $result['rejected_reviews'] );
		$this->assertNull( ReviewRepository::find( $id ) );
		$this->assertNotNull( ReviewRepository::find( $keep ) );
	}
}
