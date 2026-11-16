<?php
/**
 * Jobs, CSV, connectors, public API, frontend rendering, schema (TEC-003/006, RC-044/045, CON-007/008, RC-049–055, SEO-009).
 *
 * @package ReviewCaptain
 */

use ReviewCaptain\Api\PublicApi;
use ReviewCaptain\Connectors\Registry;
use ReviewCaptain\Contracts\ConnectorInterface;
use ReviewCaptain\Contracts\RecipientResolverInterface;
use ReviewCaptain\Frontend\Renderer;
use ReviewCaptain\Frontend\SubmissionController;
use ReviewCaptain\ImportExport\Csv;
use ReviewCaptain\Jobs\JobException;
use ReviewCaptain\Jobs\Queue;
use ReviewCaptain\Jobs\Runner;
use ReviewCaptain\Reviews\ReviewRepository;
use ReviewCaptain\Reviews\ReviewService;
use ReviewCaptain\Seo\SchemaOutput;
use ReviewCaptain\Settings\Settings;

class InfrastructureTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		Settings::reset_all();
		wp_cache_flush();
	}

	// ---- Jobs (TEC-003 / TEC-006) ----

	public function test_unique_jobs_are_idempotent(): void {
		$a = Queue::enqueue( 'test.job', array( 'x' => 1 ), array( 'unique_key' => 'same' ) );
		$b = Queue::enqueue( 'test.job', array( 'x' => 2 ), array( 'unique_key' => 'same' ) );
		$this->assertGreaterThan( 0, $a );
		$this->assertSame( 0, $b );
	}

	public function test_two_workers_never_claim_the_same_job(): void {
		for ( $i = 0; $i < 5; $i++ ) {
			Queue::enqueue( 'test.job', array( 'i' => $i ) );
		}
		$first  = Queue::claim( str_repeat( 'a', 32 ), 3 );
		$second = Queue::claim( str_repeat( 'b', 32 ), 10 );
		$ids1   = wp_list_pluck( $first, 'id' );
		$ids2   = wp_list_pluck( $second, 'id' );
		$this->assertCount( 3, $ids1 );
		$this->assertCount( 2, $ids2 );
		$this->assertEmpty( array_intersect( $ids1, $ids2 ) );
		// A worker cannot complete a job it does not own.
		$this->assertFalse( Queue::complete( (int) $ids1[0], str_repeat( 'b', 32 ) ) );
		$this->assertTrue( Queue::complete( (int) $ids1[0], str_repeat( 'a', 32 ) ) );
	}

	public function test_expired_lease_is_recovered(): void {
		global $wpdb;
		Queue::enqueue( 'test.job' );
		$job = Queue::claim( str_repeat( 'c', 32 ), 1 )[0];
		$wpdb->update( \ReviewCaptain\Database\Schema::table( 'rc_jobs' ), array( 'lease_until' => gmdate( 'Y-m-d H:i:s', time() - 10 ) ), array( 'id' => $job['id'] ) );
		$this->assertSame( 1, Queue::recover_expired() );
		$this->assertCount( 1, Queue::claim( str_repeat( 'd', 32 ), 1 ) );
	}

	public function test_retry_policy_by_error_class(): void {
		global $wpdb;
		$table = \ReviewCaptain\Database\Schema::table( 'rc_jobs' );
		$token = str_repeat( 'e', 32 );

		Queue::enqueue( 'test.temp' );
		$job = Queue::claim( $token, 1 )[0];
		$this->assertSame( 'queued', Queue::fail( $job, $token, new JobException( 'timeout' ) ) );

		$wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore
		Queue::enqueue( 'test.auth' );
		$job = Queue::claim( $token, 1 )[0];
		$this->assertSame( 'dead', Queue::fail( $job, $token, new JobException( 'auth_expired' ) ) );

		$wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore
		Queue::enqueue( 'test.paused' );
		$job = Queue::claim( $token, 1 )[0];
		$this->assertSame( 'paused', Queue::fail( $job, $token, new JobException( 'source_unavailable' ) ) );

		$wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore
		Queue::enqueue( 'test.rate', array(), array( 'max_attempts' => 1 ) );
		$job = Queue::claim( $token, 1 )[0];
		$this->assertSame( 'queued', Queue::fail( $job, $token, new JobException( 'quota_exceeded', '', 120 ) ) );
		$available = strtotime( $wpdb->get_var( "SELECT available_at FROM {$table}" ) . ' UTC' ); // phpcs:ignore
		$this->assertGreaterThanOrEqual( time() + 110, $available );

		$wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore
		Queue::enqueue( 'test.exhaust', array(), array( 'max_attempts' => 1 ) );
		$job = Queue::claim( $token, 1 )[0];
		$this->assertSame( 'dead', Queue::fail( $job, $token, new JobException( 'timeout' ) ) );
		$this->assertSame( 'retry_exhausted', $wpdb->get_var( "SELECT last_error_code FROM {$table}" ) ); // phpcs:ignore
	}

	public function test_runner_executes_registered_handlers(): void {
		$calls = 0;
		add_filter(
			'wp_review_captain_job_handlers_v1',
			static function ( $h ) use ( &$calls ) {
				$h['test.count'] = static function () use ( &$calls ) {
					++$calls;
				};
				$h['test.fail']  = static function () {
					throw new RuntimeException( 'boom secret' );
				};
				return $h;
			}
		);
		Queue::enqueue( 'test.count' );
		Queue::enqueue( 'test.count' );
		Queue::enqueue( 'test.fail' );
		$result = Runner::run( 10 );
		$this->assertSame( 2, $calls );
		$this->assertSame( 2, $result['processed'] );
		$this->assertSame( 1, $result['failed'] );
		global $wpdb;
		$msg = $wpdb->get_var( 'SELECT last_error_message FROM ' . \ReviewCaptain\Database\Schema::table( 'rc_jobs' ) . " WHERE type='test.fail'" ); // phpcs:ignore
		$this->assertStringNotContainsString( 'secret', (string) $msg );
	}

	// ---- CSV (RC-044 / RC-045) ----

	public function test_csv_formula_injection_is_neutralized(): void {
		$this->assertSame( "'=HYPERLINK(\"x\")", Csv::safe_cell( '=HYPERLINK("x")' ) );
		$this->assertSame( "'+1+1", Csv::safe_cell( '+1+1' ) );
		$this->assertSame( "'@SUM(A1)", Csv::safe_cell( '@SUM(A1)' ) );
		$this->assertSame( '-5', Csv::safe_cell( '-5' ) );
		$this->assertSame( 'Hello', Csv::safe_cell( 'Hello' ) );
	}

	public function test_csv_export_import_roundtrip_with_dry_run_and_rollback(): void {
		$file = wp_tempnam( 'rc.csv' );
		file_put_contents( // phpcs:ignore
			$file,
			"provider;external_id;rating_value;content;author_name;source_url;published_at;status\n" .
			"google;g1;5;=cmd|' /C calc'!A0;Anna;https://g.example/1;2026-10-01;approved\n" .
			"google;g1;4;dupe;Bob;https://g.example/2;2026-10-02;approved\n" .
			"google;g2;9;bad rating;Carl;https://g.example/3;2026-10-03;\n" .
			"trustpilot;t1;3;no link;Dora;;2026-10-04;\n" .
			"site;;4;local text;Eva;;2026-10-05;\n"
		);
		$parsed = Csv::parse( $file );
		$this->assertCount( 5, $parsed['rows'] );
		$dry = Csv::dry_run( $parsed['rows'] );
		$this->assertCount( 2, $dry['valid'] );
		$this->assertCount( 2, $dry['errors'] );
		$this->assertCount( 1, $dry['duplicates'] );
		$this->assertSame( 0, ReviewRepository::count( array( 'origin' => 'import' ) ), 'Dry run must not write.' );

		$res = Csv::import( $dry['valid'], 'test.csv' );
		$this->assertSame( 2, $res['imported'] );
		$imported = ReviewRepository::query( array( 'batch' => $res['batch_id'] ), 10, 0, 'id', 'ASC' );
		$this->assertSame( 'approved', $imported[0]['status'] );
		$this->assertSame( 'external', $imported[0]['verification'] );
		$this->assertSame( 'pending', $imported[1]['status'] );
		$this->assertSame( 'unverified', $imported[1]['verification'] );

		// Second dry run of the same file detects the existing platform ID.
		$this->assertArrayHasKey( 2, Csv::dry_run( $parsed['rows'] )['duplicates'] );

		// Export neutralizes the formula.
		$out = fopen( 'php://memory', 'w+' ); // phpcs:ignore
		Csv::export( $out, array( 'origin' => 'import' ) );
		rewind( $out );
		$csv = stream_get_contents( $out );
		$this->assertStringContainsString( "'=cmd", $csv );

		$this->assertSame( 2, Csv::rollback( $res['batch_id'] ) );
		$this->assertSame( 0, ReviewRepository::count( array( 'origin' => 'import' ) ) );
	}

	// ---- Connectors (CON-007 / CON-008) ----

	private function connector( array $meta, string $class = '' ) {
		if ( 'resolver' === $class ) {
			return new class( $meta ) implements ConnectorInterface, RecipientResolverInterface {
				public function __construct( private array $meta ) {}
				public function metadata(): array {
					return $this->meta;
				}
				public function resolve( string $recipient_ref, string $experience_id ): array {
					return array( 'status' => 'unknown', 'email' => null, 'first_name' => null, 'locale' => null, 'basis' => 'unknown' );
				}
			};
		}
		return new class( $meta ) implements ConnectorInterface {
			public function __construct( private array $meta ) {}
			public function metadata(): array {
				return $this->meta;
			}
		};
	}

	private function meta( array $over = array() ): array {
		return array_merge( array( 'key' => 'acme-booking', 'label' => 'ACME', 'version' => '1.0.0', 'contract' => '1.0', 'capabilities' => array() ), $over );
	}

	public function test_connector_registry_contract_validation(): void {
		Registry::reset();
		$reg = Registry::instance();
		$this->assertFalse( $reg->register( new stdClass() )['accepted'] );
		$this->assertSame( 'invalid_payload', $reg->register( $this->connector( $this->meta( array( 'key' => '' ) ) ) )['code'] ); // T1
		$this->assertSame( 'invalid_payload', $reg->register( $this->connector( $this->meta( array( 'key' => 'ACME Booking!' ) ) ) )['code'] ); // T2
		$this->assertSame( 'ok', $reg->register( $this->connector( $this->meta( array( 'capabilities' => array( 'recipient_resolver' ) ) ), 'resolver' ) )['code'] );
		$this->assertSame( 'duplicate', $reg->register( $this->connector( $this->meta() ) )['code'] ); // T7
		$this->assertSame( 'incompatible_contract', $reg->register( $this->connector( $this->meta( array( 'key' => 'v2-conn', 'contract' => '2.0' ) ) ) )['code'] ); // T3
		$this->assertSame( 'ok', $reg->register( $this->connector( $this->meta( array( 'key' => 'v15-conn', 'contract' => '1.5' ) ) ) )['code'] ); // T4
		$this->assertSame( 'invalid_payload', $reg->register( $this->connector( $this->meta( array( 'key' => 'missing-iface', 'capabilities' => array( 'recipient_resolver' ) ) ) ) )['code'] ); // T5
		$reg->register( $this->connector( $this->meta( array( 'key' => 'unknown-cap', 'capabilities' => array( 'teleport' ) ) ) ) ); // T6
		$this->assertSame( 'incompatible_contract', $reg->register( $this->connector( $this->meta( array( 'key' => 'too-new', 'requires_core' => '9.0.0' ) ) ) )['code'] ); // T11

		$all = $reg->all();
		$this->assertSame( 'paused', $all['v2-conn']['status'] );
		$this->assertSame( array(), $all['v2-conn']['capabilities'] );
		$this->assertNotEmpty( $all['unknown-cap']['notices'] );
		$this->assertSame( array( 'acme-booking' ), array_keys( $reg->with_capability( 'recipient_resolver' ) ) );
		Registry::reset();
	}

	// ---- Public event API (RC-021) ----

	private function event( array $over = array() ): array {
		return array_merge(
			array(
				'contract'      => '1.0',
				'source'        => 'pass-captain',
				'event_id'      => 'checkin-1',
				'experience_id' => 'pass-captain:1:ticket:1',
				'event_type'    => 'experience_completed',
				'occurred_at'   => '2026-11-14T19:30:00Z',
				'object'        => array( 'type' => 'event', 'id' => '321' ),
				'recipient_ref' => 'pass-captain:attendee:5',
				'communication' => array( 'basis' => 'consent' ),
			),
			$over
		);
	}

	public function test_event_validation(): void {
		$this->assertSame( 'ok', PublicApi::validate_event( $this->event() ) );
		$this->assertSame( 'incompatible_contract', PublicApi::validate_event( $this->event( array( 'contract' => '2.0' ) ) ) );
		$this->assertSame( 'invalid_payload', PublicApi::validate_event( $this->event( array( 'recipient_ref' => 'anna@example.org' ) ) ) );
		$this->assertSame( 'invalid_payload', PublicApi::validate_event( $this->event( array( 'communication' => null ) ) ) );
		$this->assertSame( 'invalid_payload', PublicApi::validate_event( $this->event( array( 'email' => 'x' ) ) ) );
		$this->assertSame( 'invalid_payload', PublicApi::validate_event( $this->event( array( 'verification' => 'gold' ) ) ) );
		$refund = $this->event( array( 'event_type' => 'experience_refunded' ) );
		unset( $refund['recipient_ref'], $refund['communication'] );
		$this->assertSame( 'ok', PublicApi::validate_event( $refund ), 'Refund events need no recipient.' );
	}

	public function test_event_without_processor_is_not_stored(): void {
		$res = wp_review_captain_submit_experience_event_v1( $this->event() );
		$this->assertFalse( $res['accepted'] );
		$this->assertSame( 'license_inactive', $res['code'] );
		$this->assertSame( hash( 'sha256', 'pass-captain|checkin-1' ), $res['event_key'] );
	}

	// ---- Frontend (RC-049 – RC-054) ----

	public function test_card_escapes_content_and_keeps_attribution(): void {
		$id   = ReviewService::create_manual_external(
			array(
				'provider'     => 'google',
				'rating_value' => 4,
				'content'      => 'Nice <img src=x onerror=alert(1)>',
				'author_name'  => 'Tom Kraus',
				'published_at' => '2026-10-01',
				'source_url'   => 'https://maps.google.com/x',
				'attribution'  => 'Review on Google',
			)
		);
		$html = Renderer::widget( array( 'providers' => array( 'google' ) ) );
		$this->assertStringNotContainsString( '<img src=x', $html );
		$this->assertStringContainsString( 'Review on Google', $html );
		$this->assertStringContainsString( 'rel="nofollow noopener noreferrer"', $html );
		$this->assertStringContainsString( '4 out of 5', $html );
		$this->assertStringContainsString( 'Tom K.', $html );
		$this->assertStringNotContainsString( 'Kraus', $html );
		$this->assertIsInt( $id );
	}

	public function test_pending_reviews_are_not_rendered_and_empty_widget_is_blank(): void {
		ReviewService::create_local( array( 'rating' => 5, 'content' => 'Pending text', 'author_name' => 'P' ) );
		$this->assertSame( '', Renderer::widget( array( 'providers' => array( 'site' ) ) ) );
	}

	public function test_shortcode_and_non_five_scale(): void {
		ReviewService::create_manual_external(
			array(
				'provider'     => 'other',
				'rating_value' => 9,
				'rating_max'   => 10,
				'content'      => 'Ten scale',
				'published_at' => '2026-10-01',
				'source_url'   => 'https://portal.example/r/1',
				'attribution'  => 'Review on Portal',
			)
		);
		$html = do_shortcode( '[review_captain_widget providers="other" layout="list"]' );
		$this->assertStringContainsString( 'rc-widget--list', $html );
		$this->assertStringContainsString( '9 out of 10', $html );
		$this->assertStringNotContainsString( 'rc-stars', $html );
		$this->assertSame( '', do_shortcode( '[review_captain_widget v="99"]' ) );
	}

	public function test_cta_has_no_star_preselection(): void {
		\ReviewCaptain\Profiles\ProfileRepository::save_link( 0, 'google', 'Store', 'https://g.page/r/example/review' );
		$html = do_shortcode( '[review_captain_cta]' );
		$this->assertStringContainsString( 'Review us on Google', $html );
		$this->assertStringNotContainsString( 'star', strtolower( wp_strip_all_tags( $html ) ) );
		$this->assertWPError( \ReviewCaptain\Profiles\ProfileRepository::save_link( 0, 'google', '', 'javascript:alert(1)' ) );
	}

	public function test_testimonials_are_labelled_and_require_consent(): void {
		$this->assertWPError( \ReviewCaptain\Testimonials\TestimonialRepository::save_text( 0, array( 'quote' => 'Great', 'status' => 'approved' ) ) );
		$id = \ReviewCaptain\Testimonials\TestimonialRepository::save_text( 0, array( 'quote' => 'Great workshop', 'display_name' => 'Maria Schmidt', 'display_name_mode' => 'first_name', 'status' => 'approved', 'consent_note' => 'Signed form' ) );
		$html = Renderer::widget( array( 'include_testimonials' => true, 'providers' => array( 'site' ) ) );
		$this->assertStringContainsString( 'Testimonial', $html );
		$this->assertStringContainsString( 'Maria', $html );
		$this->assertStringNotContainsString( 'Schmidt', $html );
		\ReviewCaptain\Testimonials\TestimonialRepository::revoke( $id );
		wp_cache_flush();
		$this->assertSame( '', Renderer::widget( array( 'include_testimonials' => true, 'providers' => array( 'site' ) ) ) );
	}

	public function test_submission_honeypot_nonce_and_rate_limit(): void {
		$nonce = wp_create_nonce( 'rc_submit_review' );
		$base  = array( 'rating' => 5, 'content' => 'ok', 'author_name' => 'Visitor' );
		$this->assertWPError( SubmissionController::process( $base, 'bad' ) );
		$this->assertTrue( SubmissionController::process( $base + array( 'rc_website' => 'spam' ), $nonce ) );
		$this->assertSame( 0, ReviewRepository::count( array( 'origin' => 'local' ) ) );
		for ( $i = 0; $i < 3; $i++ ) {
			$this->assertIsInt( SubmissionController::process( $base, $nonce ) );
		}
		$limited = SubmissionController::process( $base, $nonce );
		$this->assertWPError( $limited );
		$this->assertSame( 'rate_limited', $limited->get_error_code() );
	}

	// ---- Schema (RC-055 / SEO-009) ----

	public function test_schema_eligibility_excludes_external_and_self_serving(): void {
		$base = array( 'provider' => 'site', 'origin' => 'local', 'status' => 'approved', 'rating_value' => 5, 'author_name' => 'A', 'context_type' => 'product' );
		$this->assertTrue( SchemaOutput::is_eligible( $base ) );
		$this->assertFalse( SchemaOutput::is_eligible( array_merge( $base, array( 'provider' => 'google', 'origin' => 'sync' ) ) ) );
		$this->assertFalse( SchemaOutput::is_eligible( array_merge( $base, array( 'origin' => 'import' ) ) ) );
		$this->assertFalse( SchemaOutput::is_eligible( array_merge( $base, array( 'context_type' => '' ) ) ) ); // Organization/self review.
		$this->assertFalse( SchemaOutput::is_eligible( array_merge( $base, array( 'status' => 'pending' ) ) ) );
	}

	public function test_fallback_json_ld_only_for_matching_product_page_without_seo_plugin(): void {
		register_post_type( 'product', array( 'public' => true ) );
		$post_id = self::factory()->post->create( array( 'post_type' => 'product', 'post_title' => 'Widget' ) );
		$id      = ReviewService::create_local( array( 'rating' => 4, 'content' => 'Good product', 'author_name' => 'Anna B', 'context_type' => 'product', 'context_id' => (string) $post_id ) );
		ReviewService::moderate( $id, 'approved' );
		ReviewService::create_manual_external( array( 'provider' => 'google', 'rating_value' => 1, 'content' => 'x', 'published_at' => '2026-01-01', 'source_url' => 'https://g.example/x', 'attribution' => 'Google', 'context_type' => 'product', 'context_id' => (string) $post_id ) );
		update_option( 'blog_public', 1 );
		$this->go_to( get_permalink( $post_id ) );
		SchemaOutput::reset();
		Renderer::widget( array( 'context_type' => 'product', 'context_id' => (string) $post_id ) );
		ob_start();
		SchemaOutput::print_fallback();
		$out = ob_get_clean();
		$this->assertStringContainsString( 'application/ld+json', $out );
		$this->assertStringContainsString( '"reviewCount":1', $out );
		$this->assertStringNotContainsString( 'g.example', $out );
		$this->assertStringNotContainsString( '<title', $out );
	}
}
