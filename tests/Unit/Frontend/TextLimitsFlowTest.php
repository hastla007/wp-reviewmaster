<?php
/**
 * Review P2: text length limits at 0 and in a contradictory stored combination, across the
 * whole flow (form, rendered form, author correction, workflow draft).
 */

declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Frontend;

use ReviewCaptain\Domain\Origin;
use ReviewCaptain\Domain\Review;
use ReviewCaptain\Domain\ReviewStatus;
use ReviewCaptain\Frontend\FormHandler;
use ReviewCaptain\Moderation\CorrectionService;
use ReviewCaptain\Plugin;

final class TextLimitsFlowTest extends FrontendTestCase {

	/**
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
				'rc_return'       => 'https://example.test/',
				'rc_rating'       => '4',
				'rc_body'         => 'Gut.',
				'rc_name'         => 'Carla',
				'rc_email'        => 'carla@example.test',
				'rc_consent'      => '1',
			),
			$o
		);
	}

	/**
	 * Store raw values like an import or an old backup does (no cross-field check).
	 *
	 * @param int $min min_text_length.
	 * @param int $max max_text_length.
	 */
	private function stored_limits( int $min, int $max ): void {
		$s                                   = Plugin::instance()->settings()->all();
		$s['submissions']['min_text_length'] = $min;
		$s['submissions']['max_text_length'] = $max;
		$s['submissions']['corrections_enabled'] = true;
		update_option( 'review_captain_settings', $s );
		Plugin::instance()->settings()->flush();
	}

	private function approved_review(): Review {
		$r                  = new Review();
		$r->origin          = Origin::LOCAL_SUBMISSION;
		$r->rating_value    = 4.0;
		$r->title           = 'Alt';
		$r->body            = 'Kurz.';
		$r->author_name     = 'Erika';
		$r->author_email    = 'erika@example.org';
		$r->status          = ReviewStatus::APPROVED;
		$r->consent_publish = true;
		return Plugin::instance()->reviews()->insert( $r );
	}

	public function test_minimum_zero_never_allows_an_empty_text(): void {
		$this->stored_limits( 0, 5000 );
		$this->assertSame( array( 1, 5000 ), FormHandler::text_limits( Plugin::instance()->settings() ) );
		$this->assertSame( array( 'body_short' ), $this->handler()->process( $this->post( array( 'rc_body' => '' ) ) )['errors'] );
		$this->assertTrue( $this->handler()->process( $this->post() )['ok'], 'short texts are fine with minimum 0' );

		// Author correction: emptying the text was possible with minimum 0.
		$service = new CorrectionService( Plugin::instance() );
		$res     = $service->propose( $this->approved_review(), array( 'body' => '   ' ), 'link' );
		$this->assertInstanceOf( \WP_Error::class, $res );
		$this->assertSame( 'body', $res->get_error_message() );
	}

	public function test_contradictory_stored_limits_keep_the_form_usable(): void {
		$this->stored_limits( 1000, 60 );
		$this->assertSame( array( 60, 60 ), FormHandler::text_limits( Plugin::instance()->settings() ) );

		$html = $this->module->shortcode_form( array( 'v' => '1' ) );
		$this->assertStringContainsString( 'minlength="60"', $html, 'the browser can still submit the form' );
		$this->assertStringContainsString( 'maxlength="60"', $html );

		$text = str_repeat( 'a', 60 );
		$this->assertTrue( $this->handler()->process( $this->post( array( 'rc_body' => $text ) ) )['ok'] );

		$draft = Plugin::instance()->get( 'workflow' )->create_draft(
			array(
				'contract'        => '1.0',
				'idempotency_key' => 'form-captain:1:entry:7',
				'rating'          => 5,
				'review_text'     => $text,
				'display_name'    => 'Ben',
			)
		);
		$this->assertTrue( $draft['success'], 'workflow drafts are not all rejected either' );
	}

	private function handler(): FormHandler {
		/** @var FormHandler */
		return Plugin::instance()->get( 'form_handler' );
	}
}
