<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Frontend;

use ReviewCaptain\Frontend\FormHandler;
use ReviewCaptain\Frontend\RequestResponse;
use ReviewCaptain\Plugin;

/**
 * P2-10: a review over the signed link of an invitation is linked to its request and fires
 * review_captain_request_responded_v1; the core never checks the token itself.
 */
final class RequestResponseTest extends FrontendTestCase {

	private const UUID = '7a8b9c0d-1e2f-4a3b-8c4d-5e6f7a8b9c0d';

	/**
	 * Payloads of review_captain_request_responded_v1.
	 *
	 * @var list<array<string, mixed>>
	 */
	private array $events = array();

	protected function setUp(): void {
		parent::setUp();
		$this->events = array();
		unset( $_GET[ RequestResponse::FIELD ] );
		add_action(
			'review_captain_request_responded_v1',
			function ( $payload ): void {
				$this->events[] = $payload;
			}
		);
	}

	protected function tearDown(): void {
		unset( $_GET[ RequestResponse::FIELD ] );
		parent::tearDown();
	}

	private function handler(): FormHandler {
		/** @var FormHandler */
		return Plugin::instance()->get( 'form_handler' );
	}

	/**
	 * Valid POST payload.
	 *
	 * @param array<string, mixed> $o Overrides.
	 * @return array<string, mixed>
	 */
	private function post( array $o = array() ): array {
		return array_merge(
			array(
				'_rc_nonce'       => wp_create_nonce( FormHandler::NONCE_ACTION ),
				'rc_form'         => '1',
				'rc_ts'           => FormHandler::sign_timestamp( time() - 10 ),
				'rc_context_type' => '',
				'rc_context_id'   => '',
				'rc_context_sig'  => FormHandler::sign_context( '', '' ),
				'rc_return'       => 'https://example.test/bewerten/?rc_request=abc',
				'rc_rating'       => '5',
				'rc_body'         => 'Alles bestens, die Beratung war ausführlich und freundlich.',
				'rc_name'         => 'Carla',
				'rc_email'        => 'carla@example.test',
				'rc_consent'      => '1',
				'rc_website'      => '',
			),
			$o
		);
	}

	/**
	 * Token filter like Review Captain Pro: only "good-token" belongs to a request.
	 */
	private function answer_tokens(): void {
		add_filter(
			'review_captain_request_token_v1',
			static function ( $ref, $token ) {
				return 'good-token' === $token ? self::UUID : $ref;
			},
			10,
			2
		);
	}

	public function test_form_passes_the_token_of_the_page_address_on(): void {
		$_GET[ RequestResponse::FIELD ] = 'good-token';
		$out                            = $this->module->shortcode_form( array() );
		$this->assertStringContainsString( 'name="rc_request" value="good-token"', $out );

		$_GET[ RequestResponse::FIELD ] = 'bad token<script>';
		$this->assertStringNotContainsString( 'name="rc_request"', $this->module->shortcode_form( array() ) );
		unset( $_GET[ RequestResponse::FIELD ] );
		$this->assertStringNotContainsString( 'name="rc_request"', $this->module->shortcode_form( array() ) );
	}

	public function test_valid_token_links_the_review_and_fires_responded(): void {
		$this->answer_tokens();
		$result = $this->handler()->process( $this->post( array( 'rc_request' => 'good-token' ) ) );
		$this->assertTrue( $result['ok'], implode( ',', $result['errors'] ) );
		$review = Plugin::instance()->reviews()->find( (int) $result['review']->id );
		$this->assertSame( self::UUID, $review->meta[ RequestResponse::META_KEY ] ?? null );

		$this->assertCount( 1, $this->events );
		$payload = $this->events[0];
		$this->assertSame( RequestResponse::SCHEMA, $payload['schema'] );
		$this->assertSame( self::UUID, $payload['request_uuid'] );
		$this->assertSame( $review->uuid, $payload['review_uuid'] );
		$this->assertSame( array( $review->uuid ), $payload['review_uuids'] );
		$this->assertSame( 'pending', $payload['review_status'] );
		$encoded = (string) wp_json_encode( $payload );
		$this->assertStringNotContainsString( 'carla', $encoded );
		$this->assertStringNotContainsString( 'good-token', $encoded );
		$this->assertStringNotContainsString( 'Beratung', $encoded );
	}

	public function test_unknown_token_or_no_add_on_changes_nothing(): void {
		// Without an answer of the add-on the token is ignored, the review is stored as usual.
		$result = $this->handler()->process( $this->post( array( 'rc_request' => 'good-token' ) ) );
		$this->assertTrue( $result['ok'] );
		$this->assertArrayNotHasKey( RequestResponse::META_KEY, Plugin::instance()->reviews()->find( (int) $result['review']->id )->meta );

		$this->answer_tokens();
		$forged = $this->handler()->process(
			$this->post(
				array(
					'rc_request' => 'forged-token',
					'rc_body'    => 'Ein zweiter Text, ebenfalls lang genug für das Formular.',
				)
			)
		);
		$this->assertTrue( $forged['ok'] );
		$this->assertArrayNotHasKey( RequestResponse::META_KEY, Plugin::instance()->reviews()->find( (int) $forged['review']->id )->meta );
		$this->assertSame( array(), $this->events );
	}

	public function test_failed_submission_and_invalid_answers_fire_nothing(): void {
		$this->answer_tokens();
		$this->assertFalse( $this->handler()->process( $this->post( array( 'rc_request' => 'good-token', 'rc_rating' => '' ) ) )['ok'] );
		$this->assertSame( array(), $this->events );

		add_filter(
			'review_captain_request_token_v1',
			static function () {
				return 'not a ref';
			},
			20
		);
		$this->assertNull( RequestResponse::resolve( 'good-token' ) );
		$this->assertNull( RequestResponse::resolve( '' ) );
		$this->assertSame( '', RequestResponse::clean( str_repeat( 'a', 201 ) ) );
	}
}
