<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\M7;

use ReviewCaptain\Admin\ReviewsListTable;
use ReviewCaptain\Domain\ReviewStatus;
use ReviewCaptain\Moderation\ReportHandler;
use ReviewCaptain\Moderation\ReportStore;
use ReviewCaptain\Moderation\TagStore;
use ReviewCaptain\Plugin;
use ReviewCaptain\Providers\SyncPage;
use ReviewCaptain\Providers\SyncService;

/**
 * RC-124: internal tags, public report, admin filter.
 */
final class TagsAndReportsTest extends M7TestCase {

	private function report( string $uuid, string $reason = 'spam', array $o = array() ): array {
		return ( new ReportHandler( Plugin::instance() ) )->process(
			$o + array(
				'_rc_report_nonce' => wp_create_nonce( ReportHandler::NONCE_ACTION ),
				'rc_review'        => $uuid,
				'rc_reason'        => $reason,
				'rc_return'        => 'https://example.test/bewertungen/?rc_reported=old#x',
			)
		);
	}

	public function test_tags_are_normalised_unique_and_replaceable(): void {
		$id    = (int) $this->review()->id;
		$store = new TagStore();
		$this->assertSame( array( 'lieferung', 'rueckfrage' ), $store->set( $id, 'Lieferung, Rückfrage, lieferung,  ' ) );
		$this->assertSame( array( 'rueckfrage' ), $store->set( $id, array( 'Rückfrage' ) ) );
		$this->assertSame( array( 'rueckfrage' => 1 ), $store->all() );
		$this->assertCount( TagStore::MAX_TAGS, TagStore::normalize( implode( ',', range( 1, 40 ) ) ) );
	}

	public function test_tags_survive_a_provider_sync_and_are_never_public(): void {
		$repo = Plugin::instance()->reviews();
		$s    = new SyncService( $repo, 3600 );
		$ext  = $this->review( array( 'platform' => \ReviewCaptain\Domain\Platform::TRUSTPILOT, 'external_id' => 'tp-9', 'profile_id' => 4 ) );
		( new TagStore() )->set( (int) $ext->id, 'intern-pruefen' );
		$incoming              = clone $ext;
		$incoming->body        = 'Geänderter Text vom Anbieter';
		$s->apply( new SyncPage( array( $incoming ) ), 4 );
		$this->assertSame( array( 'intern-pruefen' ), ( new TagStore() )->for_review( (int) $ext->id ) );
		$this->enable( array( 'submissions' => array( 'tags_enabled' => true ) ) );
		$html = $this->shortcode( array( 'v' => '1' ) );
		$this->assertStringNotContainsString( 'intern-pruefen', $html );
	}

	public function test_admin_filters_by_tag_and_open_reports(): void {
		$a = (int) $this->review()->id;
		$b = (int) $this->review()->id;
		( new TagStore() )->set( $a, 'vip' );
		( new ReportStore() )->add( $b, 'spam' );
		$repo = Plugin::instance()->reviews();
		$this->assertSame( array( $a ), array_map( static fn( $r ) => $r->id, $repo->query( ReviewsListTable::query_args( array( 'tag' => 'vip' ) ) ) ) );
		$this->assertSame( array( $b ), array_map( static fn( $r ) => $r->id, $repo->query( ReviewsListTable::query_args( array( 'reported' => '1' ) ) ) ) );
	}

	public function test_report_link_renders_with_nonce_when_enabled(): void {
		$this->enable( array( 'submissions' => array( 'reports_enabled' => true ) ) );
		$r    = $this->review();
		$html = $this->shortcode( array( 'v' => '1' ) );
		$this->assertStringContainsString( '<details class="rc-report">', $html );
		$this->assertStringContainsString( 'name="_rc_report_nonce"', $html );
		$this->assertStringContainsString( 'value="' . $r->uuid . '"', $html );
		$this->assertStringContainsString( 'value="review_captain_report"', $html );
	}

	public function test_public_report_flags_review_and_hides_it(): void {
		$this->enable( array( 'submissions' => array( 'reports_enabled' => true ) ) );
		$r   = $this->review();
		$out = $this->report( $r->uuid, 'offensive' );
		$this->assertTrue( $out['ok'] );
		$this->assertTrue( $out['flagged'] );
		$this->assertSame( 'https://example.test/bewertungen/?rc_reported=1', $out['redirect'] );
		$this->assertSame( ReviewStatus::FLAGGED, Plugin::instance()->reviews()->find( (int) $r->id )->status );
		$this->assertStringNotContainsString( $r->uuid, $this->shortcode( array( 'v' => '1' ) ) );
		$this->assertSame( array( 'offensive' => 1 ), ( new ReportStore() )->open_reasons( (int) $r->id ) );
		// The moderator decides: reports are closed.
		Plugin::instance()->reviews()->change_status( (int) $r->id, ReviewStatus::APPROVED, 'geprüft' );
		$this->assertSame( 0, ( new ReportStore() )->open_count( (int) $r->id ) );
	}

	public function test_threshold_rate_limit_and_validation(): void {
		$this->enable( array( 'submissions' => array( 'reports_enabled' => true, 'report_threshold' => 2, 'report_rate_limit_per_hour' => 2 ) ) );
		$r  = $this->review();
		$r2 = $this->review();
		$r3 = $this->review();
		$this->assertFalse( $this->report( $r->uuid )['flagged'] );
		$this->assertTrue( $this->report( $r->uuid )['ok'], 'same address + review: acknowledged' );
		$this->assertSame( 1, ( new ReportStore() )->open_count( (int) $r->id ), 'but counted once' );
		$this->assertTrue( $this->report( $r2->uuid )['ok'] );
		$this->assertSame( 'rate_limited', $this->report( $r3->uuid )['error'] );

		$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
		$this->assertTrue( $this->report( $r->uuid )['flagged'], 'second address reaches the threshold' );

		$this->assertSame( 'nonce', $this->report( $r2->uuid, 'spam', array( '_rc_report_nonce' => 'bad' ) )['error'] );
		$this->assertSame( 'invalid', $this->report( $r2->uuid, 'because' )['error'] );
		$pending = $this->review( array( 'status' => ReviewStatus::PENDING ) );
		$this->assertSame( 'invalid', $this->report( $pending->uuid )['error'], 'only public reviews can be reported' );
	}

	public function test_report_stores_nothing_about_the_reporter(): void {
		$this->enable( array( 'submissions' => array( 'reports_enabled' => true ) ) );
		$r = $this->review();
		$this->report( $r->uuid );
		$row = $this->db->get_row( 'SELECT * FROM wp_review_captain_review_reports', 'ARRAY_A' );
		$this->assertSame( array( 'id', 'review_id', 'reason', 'created_at', 'resolved_at' ), array_keys( $row ) );
		$audit = (string) $this->db->get_var( "SELECT context FROM wp_review_captain_audit_log WHERE event = 'review.reported'" );
		$this->assertStringNotContainsString( '127.0.0.1', $audit );
	}

	public function test_deleting_a_review_removes_tags_and_reports(): void {
		$id = (int) $this->review()->id;
		( new TagStore() )->set( $id, 'a,b' );
		( new ReportStore() )->add( $id, 'spam' );
		Plugin::instance()->reviews()->delete( $id );
		$this->assertSame( 0, $this->rows( 'review_tags' ) );
		$this->assertSame( 0, $this->rows( 'review_reports' ) );
	}
}
