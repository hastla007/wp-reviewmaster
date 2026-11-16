<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Maintenance;

use ReviewCaptain\Domain\Origin;
use ReviewCaptain\Domain\Platform;
use ReviewCaptain\Domain\Review;
use ReviewCaptain\Domain\Verification;
use ReviewCaptain\Install\Schema;
use ReviewCaptain\Jobs\JobQueue;
use ReviewCaptain\Maintenance\Retention;
use ReviewCaptain\Plugin;
use ReviewCaptain\Tests\TestCase;

final class RetentionTest extends TestCase {

	private function review( Platform $platform, string $status, int $age_days, ?int $removed_days = null ): int {
		$r               = new Review();
		$r->origin       = Platform::LOCAL === $platform ? Origin::LOCAL_SUBMISSION : Origin::PROVIDER_SYNC;
		$r->platform     = $platform;
		$r->body         = 'Text für Aufbewahrungstest ' . wp_generate_password( 6 );
		$r->rating_value = 4;
		$r->author_name  = 'Test';
		if ( Platform::LOCAL !== $platform ) {
			$r->external_id  = 'ext-' . wp_generate_password( 8 );
			$r->verification = Verification::EXTERNAL_PLATFORM;
			$r->source_url   = 'https://example.test/r';
		}
		$r  = Plugin::instance()->reviews()->insert( $r );
		$at = gmdate( 'Y-m-d H:i:s', time() - $age_days * DAY_IN_SECONDS );
		$this->db->update(
			Schema::table( 'reviews' ),
			array(
				'status'     => $status,
				'updated_at' => $at,
				'removed_at' => null === $removed_days ? null : gmdate( 'Y-m-d H:i:s', time() - $removed_days * DAY_IN_SECONDS ),
			),
			array( 'id' => $r->id )
		);
		return (int) $r->id;
	}

	public function test_purges_according_to_settings(): void {
		update_option(
			'review_captain_settings',
			array(
				'privacy' => array(
					'retention_rejected_days' => 90,
					'retention_external_days' => 30,
					'retention_logs_days'     => 90,
					'retention_jobs_days'     => 30,
				),
			)
		);
		$old_rejected   = $this->review( Platform::LOCAL, 'rejected', 100 );
		$young_rejected = $this->review( Platform::LOCAL, 'rejected', 10 );
		$approved_old   = $this->review( Platform::LOCAL, 'approved', 400 );
		$ext_removed    = $this->review( Platform::GOOGLE, 'removed', 40, 40 );
		$ext_recent     = $this->review( Platform::GOOGLE, 'removed', 5, 5 );
		$ext_rejected   = $this->review( Platform::GOOGLE, 'rejected', 400 );
		$local_removed  = $this->review( Platform::LOCAL, 'removed', 400, 400 );

		$audit = Schema::table( 'audit_log' );
		$this->db->insert( $audit, array( 'event' => 'old', 'context' => '{}', 'created_at' => gmdate( 'Y-m-d H:i:s', time() - 100 * DAY_IN_SECONDS ) ) );
		$this->db->insert( $audit, array( 'event' => 'new', 'context' => '{}', 'created_at' => gmdate( 'Y-m-d H:i:s' ) ) );

		$q   = new JobQueue();
		$job = $q->enqueue( 'test.a', array(), 'a' );
		$q->claim( 'w', 60 );
		$q->complete( $job, 'w' );
		$this->db->update( Schema::table( 'jobs' ), array( 'updated_at' => gmdate( 'Y-m-d H:i:s', time() - 31 * DAY_IN_SECONDS ) ), array( 'id' => $job ) );
		$q->enqueue( 'test.b', array(), 'b' );

		$res = ( new Retention( Plugin::instance(), $q ) )->run( 10 );

		$this->assertSame( 1, $res['rejected_deleted'] );
		$this->assertSame( 1, $res['external_deleted'] );
		$this->assertSame( 1, $res['logs_deleted'] );
		$this->assertSame( 1, $res['jobs_deleted'] );
		$this->assertTrue( $res['complete'] );

		$repo = Plugin::instance()->reviews();
		$this->assertNull( $repo->find( $old_rejected ) );
		$this->assertNull( $repo->find( $ext_removed ) );
		foreach ( array( $young_rejected, $approved_old, $ext_recent, $ext_rejected, $local_removed ) as $kept ) {
			$this->assertNotNull( $repo->find( $kept ), 'kept #' . $kept );
		}
		$this->assertSame( 1, $this->rows( 'jobs' ) );
		// Only the new audit entry and the retention summary remain.
		$this->assertSame( 2, $this->rows( 'audit_log' ) );
	}
}
