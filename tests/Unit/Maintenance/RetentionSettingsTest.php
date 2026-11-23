<?php
/**
 * SET-RC-08 contact/approved retention, SET-RC-21 correction deadlines.
 */

declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Maintenance;

use ReviewCaptain\Admin\SettingsPage;
use ReviewCaptain\Domain\Kind;
use ReviewCaptain\Domain\Origin;
use ReviewCaptain\Domain\Review;
use ReviewCaptain\Domain\ReviewStatus;
use ReviewCaptain\Install\Schema;
use ReviewCaptain\Jobs\JobQueue;
use ReviewCaptain\Maintenance\Retention;
use ReviewCaptain\Moderation\CorrectionHandler;
use ReviewCaptain\Moderation\CorrectionService;
use ReviewCaptain\Plugin;
use ReviewCaptain\Tests\TestCase;

final class RetentionSettingsTest extends TestCase {

	/**
	 * @param array<string, array<string, mixed>> $values Settings by tab.
	 */
	private function settings( array $values ): void {
		update_option( 'review_captain_settings', $values );
		Plugin::instance()->settings()->flush();
	}

	private function local( int $age_days, string $status = 'approved', Kind $kind = Kind::REVIEW ): int {
		$r               = new Review();
		$r->origin       = Origin::LOCAL_SUBMISSION;
		$r->kind         = $kind;
		$r->body         = 'Bewertung für den Aufbewahrungstest.';
		$r->rating_value = 5;
		$r->author_name  = 'Erika';
		$r->author_email = 'erika' . wp_generate_password( 4 ) . '@example.org';
		$r->status       = ReviewStatus::from( $status );
		$r->created_at   = gmdate( 'Y-m-d H:i:s', time() - $age_days * DAY_IN_SECONDS );
		return (int) Plugin::instance()->reviews()->insert( $r )->id;
	}

	private function run_retention(): array {
		return ( new Retention( Plugin::instance(), new JobQueue() ) )->run( 10 );
	}

	public function test_defaults_keep_contact_and_approved_reviews(): void {
		$s = Plugin::instance()->settings();
		$this->assertSame( 0, $s->get( 'privacy', 'retention_contact_days' ) );
		$this->assertSame( 0, $s->get( 'privacy', 'retention_approved_days' ) );
		$this->assertSame( 90, $s->get( 'privacy', 'retention_corrections_days' ) );
		$id  = $this->local( 4000 );
		$res = $this->run_retention();
		$this->assertSame( 0, $res['contacts_removed'] );
		$this->assertSame( 0, $res['approved_deleted'] );
		$this->assertNotNull( Plugin::instance()->reviews()->find( $id )->author_email );
	}

	public function test_contact_address_removed_after_period_review_stays(): void {
		$this->settings( array( 'privacy' => array( 'retention_contact_days' => 365 ) ) );
		$old   = $this->local( 400 );
		$young = $this->local( 10 );
		$this->assertSame( array( 'contacts' => 1, 'approved' => 0 ), Retention::preview( 365, 0 ) );

		$res = $this->run_retention();
		$this->assertSame( 1, $res['contacts_removed'] );
		$review = Plugin::instance()->reviews()->find( $old );
		$this->assertNull( $review->author_email );
		$this->assertNull( $review->submission_key );
		$this->assertSame( ReviewStatus::APPROVED, $review->status, 'review stays published' );
		$this->assertSame( 'Erika', $review->author_name );
		$this->assertNotNull( Plugin::instance()->reviews()->find( $young )->author_email );

		$history = array_column( Plugin::instance()->reviews()->history( $old ), 'action' );
		$this->assertContains( 'contact_removed', $history );
		$audit = $this->db->get_var( 'SELECT COUNT(*) FROM ' . Schema::table( 'audit_log' ) . " WHERE event = 'retention.contacts_removed'" );
		$this->assertSame( 1, (int) $audit );
		$this->assertSame( 0, $this->run_retention()['contacts_removed'], 'idempotent' );
	}

	public function test_approved_reviews_deleted_only_when_configured(): void {
		$this->settings( array( 'privacy' => array( 'retention_approved_days' => 730 ) ) );
		$old         = $this->local( 800 );
		$young       = $this->local( 100 );
		$pending     = $this->local( 800, 'pending' );
		$testimonial = $this->local( 800, 'approved', Kind::TESTIMONIAL );
		$this->assertSame( 1, Retention::preview( 0, 730 )['approved'] );

		$res = $this->run_retention();
		$this->assertSame( 1, $res['approved_deleted'] );
		$this->assertNull( Plugin::instance()->reviews()->find( $old ) );
		foreach ( array( $young, $pending, $testimonial ) as $id ) {
			$this->assertNotNull( Plugin::instance()->reviews()->find( $id ) );
		}
	}

	public function test_privacy_tab_shows_preview(): void {
		$this->login_with( 'review_captain_manage_settings', 'manage_options' );
		$this->settings( array( 'privacy' => array( 'retention_contact_days' => 30, 'retention_approved_days' => 30 ) ) );
		$this->local( 40 );
		$_GET['tab'] = 'privacy';
		ob_start();
		( new SettingsPage( Plugin::instance() ) )->render();
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'entfernt die E-Mail-Adresse bei 1 Bewertung', $html );
		$this->assertStringContainsString( 'löscht 1 Bewertung endgültig', $html );
	}

	public function test_correction_versions_follow_retention_setting(): void {
		$table = Schema::table( 'review_revisions' );
		$id    = $this->local( 10 );
		foreach ( array( 20, 50 ) as $days ) {
			$this->db->insert(
				$table,
				array(
					'review_id'    => $id,
					'status'       => 'rejected',
					'channel'      => 'link',
					'rating_value' => 3,
					'title'        => '',
					'body'         => 'alt',
					'created_at'   => gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ),
					'decided_at'   => gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ),
				)
			);
		}
		$this->assertSame( 0, $this->run_retention()['revisions_deleted'], 'default 90 days' );
		$this->settings( array( 'privacy' => array( 'retention_corrections_days' => 30 ) ) );
		$this->assertSame( 1, $this->run_retention()['revisions_deleted'] );
	}

	public function test_correction_link_validity_setting(): void {
		$this->assertSame( HOUR_IN_SECONDS, CorrectionService::token_ttl() );
		$this->assertStringContainsString( 'eine Stunde gültig', CorrectionHandler::message( 'link_sent' ) );
		$this->settings( array( 'submissions' => array( 'correction_link_hours' => 24, 'corrections_enabled' => true ) ) );
		$this->assertSame( 24 * HOUR_IN_SECONDS, CorrectionService::token_ttl() );
		$this->assertStringContainsString( '24 Stunden gültig', CorrectionHandler::message( 'link_sent' ) );

		$this->local( 1 );
		$email  = Plugin::instance()->reviews()->query( array( 'limit' => 1 ) )[0]->author_email;
		$tokens = ( new CorrectionService( Plugin::instance() ) )->issue_links( (string) $email );
		$this->assertCount( 1, $tokens );
		$stored = \RC_Test_State::$options[ '_transient_rc_corr_' . hash( 'sha256', (string) reset( $tokens ) ) ];
		$this->assertEqualsWithDelta( time() + 24 * HOUR_IN_SECONDS, $stored[1], 5 );
	}
}
