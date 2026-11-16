<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Races;

use ReviewCaptain\Domain\ReviewStatus;
use ReviewCaptain\Plugin;

/**
 * Workflow draft cancellation vs. moderation (RC-WF-03): cancel_draft deletes only while the draft
 * is still pending (CAS delete). A moderator approving at the same moment wins.
 */
final class WorkflowDraftRaceTest extends RaceTestCase {

	public function test_cancel_racing_an_approval_keeps_the_approved_review(): void {
		$wf    = Plugin::instance()->workflow();
		$repo  = Plugin::instance()->reviews();
		$draft = $wf->create_draft(
			array(
				'idempotency_key' => 'form-captain:1:77',
				'rating'          => 5,
				'review_text'     => 'Sehr hilfreiche Beratung im Kurs.',
				'display_name'    => 'Jo',
			)
		);
		$id = (int) $draft['data']['review_id'];
		$this->race->before(
			'/^DELETE FROM wp_review_captain_reviews /',
			static function () use ( $repo, $id ): void {
				$repo->change_status( $id, ReviewStatus::APPROVED );
			}
		);
		$this->assertFalse( $wf->cancel_draft( $draft['data']['review_uuid'] ) );
		$this->assertSame( ReviewStatus::APPROVED, $repo->find( $id )->status );
		$this->assertSame( 0, $this->orphans() );
	}
}
