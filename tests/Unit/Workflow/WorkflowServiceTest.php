<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Workflow;

use ReviewCaptain\Domain\Origin;
use ReviewCaptain\Domain\Platform;
use ReviewCaptain\Domain\Review;
use ReviewCaptain\Domain\ReviewStatus;
use ReviewCaptain\Domain\Verification;
use ReviewCaptain\Plugin;
use ReviewCaptain\Support\ErrorCode;
use ReviewCaptain\Tests\TestCase;
use ReviewCaptain\Workflow\RequestPlannerInterface;
use ReviewCaptain\Workflow\WorkflowService;

/**
 * RC-WF-03 core services (docs/contracts/workflow-provider-v1.md).
 */
final class WorkflowServiceTest extends TestCase {

	private function wf(): WorkflowService {
		/** @var WorkflowService */
		return Plugin::instance()->get( 'workflow' );
	}

	/**
	 * @param array<string, mixed> $extra Extra input.
	 * @return array<string, mixed>
	 */
	private function draft( array $extra = array() ): array {
		return $extra + array(
			'contract'        => '1.0',
			'idempotency_key' => 'form-captain:1:5531',
			'rating'          => 4,
			'review_text'     => 'Kurs war gut strukturiert und praxisnah.',
			'title'           => 'Guter Einstieg',
			'display_name'    => 'Lena',
			'review_type'     => 'service',
			'context_type'    => 'form',
			'context_id'      => 'contact-form-3',
			'source_plugin'   => 'form-captain',
			'source_entry_id' => '5531',
			'verified'        => false,
		);
	}

	private function set( string $tab, string $key, mixed $value ): void {
		$settings              = Plugin::instance()->settings();
		$all                   = $settings->all();
		$all[ $tab ][ $key ]   = $value;
		$settings->save( $all );
	}

	public function test_service_is_registered_in_the_container(): void {
		$this->assertTrue( Plugin::instance()->has( 'workflow' ) );
		$this->assertInstanceOf( WorkflowService::class, Plugin::instance()->workflow() );
	}

	public function test_draft_is_always_local_pending_unverified_connector(): void {
		$this->set( 'general', 'new_review_status', 'approved' ); // Must not apply to workflow drafts.
		$out = $this->wf()->create_draft( $this->draft() );
		$this->assertTrue( $out['success'] );
		$this->assertSame( 'created', $out['status'] );
		$this->assertNull( $out['code'] );
		$r = Plugin::instance()->reviews()->find( $out['data']['review_id'] );
		$this->assertSame( ReviewStatus::PENDING, $r->status );
		$this->assertSame( Verification::UNVERIFIED, $r->verification );
		$this->assertSame( Origin::CONNECTOR, $r->origin );
		$this->assertSame( Platform::LOCAL, $r->platform );
		$this->assertSame( 'form-captain:1:5531', $r->external_id );
		$this->assertSame( 'form-captain', $r->meta['source_plugin'] );
		$this->assertSame( '5531', $r->meta['source_entry_id'] );
		$this->assertSame( 'service', $r->meta['review_type'] );
		$this->assertFalse( $r->consent_publish, 'no consent unless documented' );
		$this->assertSame( 0, did_action( 'review_captain_review_status_changed_v1' ), 'never published' );
	}

	public function test_documented_consent_is_stored_but_does_not_publish(): void {
		$out = $this->wf()->create_draft( $this->draft( array( 'consent' => array( 'publish' => true, 'given_at' => '2026-11-15T10:00:00Z' ) ) ) );
		$r   = Plugin::instance()->reviews()->find( $out['data']['review_id'] );
		$this->assertTrue( $r->consent_publish );
		$this->assertSame( '2026-11-15 10:00:00', $r->consent_at );
		$this->assertSame( ReviewStatus::PENDING, $r->status );
		$bad = $this->wf()->create_draft( $this->draft( array( 'idempotency_key' => 'k2', 'consent' => array( 'publish' => true ) ) ) );
		$this->assertSame( ErrorCode::DATA_INVALID_PAYLOAD, $bad['code'] );
	}

	/**
	 * @return array<string, array{0: string, 1: mixed}>
	 */
	public static function bypass_attempts(): array {
		return array(
			'status approved'      => array( 'status', 'approved' ),
			'publish flag'         => array( 'publish', true ),
			'verification'         => array( 'verification', 'purchase_verified' ),
			'platform google'      => array( 'platform', 'google' ),
			'origin manual'        => array( 'origin', 'manual' ),
			'consent column'       => array( 'consent_publish', 1 ),
			'contact data'         => array( 'author_email', 'lena@example.test' ),
			'experience binding'   => array( 'experience_id', 'pass-captain:1:x' ),
			'verified true'        => array( 'verified', true ),
		);
	}

	/**
	 * @dataProvider bypass_attempts
	 */
	public function test_workflow_cannot_bypass_moderation_consent_or_verification( string $field, mixed $value ): void {
		$out = $this->wf()->create_draft( $this->draft( array( $field => $value ) ) );
		$this->assertFalse( $out['success'] );
		$this->assertSame( ErrorCode::DATA_INVALID_PAYLOAD, $out['code'] );
		$this->assertSame( 0, $this->rows( 'reviews' ) );
	}

	public function test_invalid_input_is_rejected(): void {
		foreach ( array(
			array( 'rating' => 6 ),
			array( 'rating' => '4.5' ),
			array( 'review_text' => 'kurz' ),
			array( 'display_name' => '' ),
			array( 'idempotency_key' => 'a b' ),
			array( 'idempotency_key' => 'x@y' ),
			array( 'context_type' => 'spaceship' ),
		) as $bad ) {
			$this->assertSame( ErrorCode::DATA_INVALID_PAYLOAD, $this->wf()->create_draft( array_merge( $this->draft(), $bad ) )['code'], wp_json_encode( $bad ) );
		}
		$this->assertSame( ErrorCode::CONTRACT_INCOMPATIBLE, $this->wf()->create_draft( $this->draft( array( 'contract' => '2.0' ) ) )['code'] );
		$this->assertSame( 0, $this->rows( 'reviews' ) );
	}

	public function test_idempotent_duplicate_returns_same_review(): void {
		$a = $this->wf()->create_draft( $this->draft() );
		$b = $this->wf()->create_draft( $this->draft( array( 'review_text' => 'Andere Fassung desselben Eintrags.' ) ) );
		$this->assertSame( 'duplicate', $b['status'] );
		$this->assertTrue( $b['success'] );
		$this->assertSame( $a['data']['review_id'], $b['data']['review_id'] );
		$this->assertSame( 1, $this->rows( 'reviews' ) );
	}

	public function test_key_colliding_with_a_non_workflow_record_is_not_reported_as_own_draft(): void {
		$r              = new Review();
		$r->origin      = Origin::CSV_IMPORT;
		$r->external_id = 'form-captain:1:5531';
		$r->body        = 'Importierter Text';
		$r->rating_value = 3.0;
		Plugin::instance()->reviews()->insert( $r );
		$out = $this->wf()->create_draft( $this->draft() );
		$this->assertFalse( $out['success'] );
		$this->assertSame( ErrorCode::DATA_DUPLICATE, $out['code'] );
	}

	public function test_cancel_draft_only_while_pending_and_only_own_drafts(): void {
		$wf  = $this->wf();
		$one = $wf->create_draft( $this->draft() );
		$this->assertTrue( $wf->cancel_draft( $one['data']['review_uuid'] ) );
		$this->assertSame( 0, $this->rows( 'reviews' ) );
		$this->assertFalse( $wf->cancel_draft( $one['data']['review_uuid'] ), 'already gone' );

		$two = $wf->create_draft( $this->draft( array( 'idempotency_key' => 'k:2' ) ) );
		Plugin::instance()->reviews()->change_status( $two['data']['review_id'], ReviewStatus::APPROVED );
		$this->assertFalse( $wf->cancel_draft( $two['data']['review_uuid'] ), 'approved reviews follow the privacy rules' );

		$local              = new Review();
		$local->origin      = Origin::LOCAL_SUBMISSION;
		$local->body        = 'Eigene Einreichung auf der Website.';
		$local->rating_value = 5.0;
		$local              = Plugin::instance()->reviews()->insert( $local );
		$this->assertFalse( $wf->cancel_draft( $local->uuid ), 'workflow cannot delete foreign pending reviews' );
		$this->assertFalse( $wf->cancel_draft( 'not-a-uuid' ) );
		$this->assertSame( 2, $this->rows( 'reviews' ) );
	}

	private function seed( string $platform, ReviewStatus $status, string $date, float $rating = 4.0, int $n = 1 ): void {
		for ( $i = 0; $i < $n; $i++ ) {
			$r               = new Review();
			$r->platform     = Platform::from( $platform );
			$r->external_id  = 'local' === $platform ? null : uniqid( $platform, true );
			$r->source_url   = 'local' === $platform ? null : 'https://example.com/r/' . $i;
			$r->verification = 'local' === $platform ? Verification::UNVERIFIED : Verification::EXTERNAL_PLATFORM;
			$r->origin       = 'local' === $platform ? Origin::LOCAL_SUBMISSION : Origin::PROVIDER_SYNC;
			$r->status       = $status;
			$r->rating_value = $rating;
			$r->body         = 'Geheimer Text ' . $i;
			$r->author_name  = 'Person ' . $i;
			$r->published_at = $date . ' 12:00:00';
			$r->context_type = 'event';
			$r->context_id   = 'OCC-55';
			Plugin::instance()->reviews()->insert( $r );
		}
	}

	public function test_aggregates_mask_small_cells_and_never_expose_single_reviews(): void {
		$this->seed( 'local', ReviewStatus::APPROVED, '2026-10-03', 5.0, 6 );
		$this->seed( 'local', ReviewStatus::PENDING, '2026-10-04', 3.0, 2 );
		$this->seed( 'local', ReviewStatus::APPROVED, '2026-11-02', 4.0, 7 );
		$this->seed( 'google', ReviewStatus::APPROVED, '2026-11-02', 5.0, 3 );

		$out = $this->wf()->aggregates( array( 'contract' => '1.0', 'context_type' => 'event', 'context_id' => 'OCC-55' ) );
		$this->assertTrue( $out['success'] );
		$data = $out['data'];
		$this->assertSame( 5, $data['min_sample'] );
		$this->assertSame( 'month', $data['granularity'] );

		$cells = array();
		foreach ( $data['buckets'] as $b ) {
			$cells[ $b['platform'] . '|' . $b['status'] . '|' . $b['period'] ] = $b['count'];
		}
		$this->assertSame( '<5', $cells['local|pending|2026-10'] );
		// Complementary suppression: the smallest other local cell is masked too.
		$this->assertSame( '<5', $cells['local|approved|2026-10'] );
		$this->assertSame( 7, $cells['local|approved|2026-11'] );
		$this->assertSame( '<5', $cells['google|approved|2026-11'] );

		$platforms = array_column( $data['by_platform'], null, 'platform' );
		$this->assertSame( 15, $platforms['local']['count'] );
		$this->assertSame( 4.46, $platforms['local']['rating_value'], 'average of approved only' );
		$this->assertTrue( $platforms['local']['sample_ok'] );
		$this->assertSame( '<5', $platforms['google']['count'] );
		$this->assertNull( $platforms['google']['rating_value'] );
		$this->assertArrayNotHasKey( 'cross_platform', $data, 'never a combined score' );

		$json = (string) wp_json_encode( $out );
		$this->assertStringNotContainsString( 'Geheimer', $json );
		$this->assertStringNotContainsString( 'Person', $json );
		$this->assertStringNotContainsString( 'review_id', $json );
		$this->assertStringNotContainsString( 'uuid', $json );
	}

	public function test_aggregates_period_granularity_and_min_sample_floor(): void {
		$this->seed( 'local', ReviewStatus::APPROVED, '2025-12-30', 5.0, 5 );
		$this->seed( 'local', ReviewStatus::APPROVED, '2026-02-01', 5.0, 5 );
		$out = $this->wf()->aggregates( array( 'granularity' => 'year', 'min_sample' => 2, 'period_from' => '2026-01-01' ) );
		$this->assertSame( 5, $out['data']['min_sample'], 'cannot be lowered below 5' );
		$this->assertSame( array( array( 'platform' => 'local', 'status' => 'approved', 'period' => '2026', 'count' => 5 ) ), $out['data']['buckets'] );
		$day = $this->wf()->aggregates( array( 'granularity' => 'day' ) );
		$this->assertSame( 'month', $day['data']['granularity'], 'no finer resolution than a month' );
		$this->assertSame( ErrorCode::CONTRACT_INCOMPATIBLE, $this->wf()->aggregates( array( 'contract' => '3' ) )['code'] );
	}

	public function test_request_actions_without_pro_planner_are_unavailable(): void {
		$caps = $this->wf()->request_capabilities();
		$this->assertFalse( $caps['planner'] );
		$this->assertFalse( $caps['plan_request'] );
		$this->assertSame( 'source_unavailable', $caps['status'] );
		$out = $this->wf()->plan_request( array( 'experience_id' => 'x' ) );
		$this->assertFalse( $out['success'] );
		$this->assertSame( ErrorCode::CONTRACT_UNAVAILABLE, $out['code'] );
	}

	public function test_registered_planner_is_used_and_results_are_passed_through(): void {
		$planner = new class() implements RequestPlannerInterface {
			public array $seen = array();
			public string $version = '1.0';
			public function contract_version(): string {
				return $this->version;
			}
			public function plan_request( array $input ): array {
				$this->seen[] = $input;
				return array( 'success' => false, 'status' => 'rejected', 'code' => 'rc.consent.missing', 'data' => array() );
			}
			public function cancel_request( array $input ): array {
				throw new \RuntimeException( 'boom' );
			}
		};
		add_filter( WorkflowService::PLANNER_FILTER, static fn() => $planner );
		$caps = $this->wf()->request_capabilities();
		$this->assertTrue( $caps['planner'] );
		$this->assertSame( 'available', $caps['status'] );
		$this->assertSame( '1.0', $caps['planner_contract'] );

		$out = $this->wf()->plan_request( array( 'contract' => '1.0', 'experience_id' => 'pass-captain:1:a' ) );
		$this->assertSame( 'rc.consent.missing', $out['code'], 'planner decision is never relaxed' );
		$this->assertCount( 1, $planner->seen );
		$this->assertSame( ErrorCode::INTERNAL, $this->wf()->cancel_request( array() )['code'] );

		$planner->version = '2.0';
		$this->assertSame( 'incompatible_contract', $this->wf()->request_capabilities()['status'] );
		$this->assertSame( ErrorCode::CONTRACT_UNAVAILABLE, $this->wf()->plan_request( array() )['code'] );
	}
}
