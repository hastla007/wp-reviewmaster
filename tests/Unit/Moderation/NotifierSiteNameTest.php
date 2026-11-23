<?php
/**
 * Review P2: site name in plain-text mails (brand name, HTML-escaped site title).
 */

declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Moderation;

use ReviewCaptain\Domain\Origin;
use ReviewCaptain\Domain\Review;
use ReviewCaptain\Domain\ReviewStatus;
use ReviewCaptain\Moderation\CorrectionHandler;
use ReviewCaptain\Moderation\Notifier;
use ReviewCaptain\Plugin;
use ReviewCaptain\Tests\TestCase;

final class NotifierSiteNameTest extends TestCase {

	/**
	 * @param array<string, array<string, mixed>> $values Values per tab.
	 */
	private function settings( array $values ): void {
		$s = Plugin::instance()->settings()->all();
		foreach ( $values as $tab => $fields ) {
			$s[ $tab ] = array_merge( $s[ $tab ] ?? array(), $fields );
		}
		update_option( 'review_captain_settings', $s );
		Plugin::instance()->settings()->flush();
	}

	public function test_escaped_site_title_is_decoded_in_plain_text_mails(): void {
		update_option( 'blogname', 'Müller &amp; Söhne' ); // WordPress stores the title escaped.
		$mail = ( new Notifier( Plugin::instance()->settings() ) )->message( Notifier::EVENT_REVIEW, array( 'moderation_url' => 'https://example.test/wp-admin/' ) );
		$this->assertSame( '[Müller & Söhne] Neue Bewertung eingegangen', $mail['subject'] );

		$this->settings( array( 'submissions' => array( 'notify_subject' => 'Neu bei {site}' ) ) );
		$mail = ( new Notifier( Plugin::instance()->settings() ) )->message( Notifier::EVENT_REVIEW, array() );
		$this->assertSame( 'Neu bei Müller & Söhne', $mail['subject'] );
	}

	public function test_brand_name_wins_in_notifications_and_correction_mails(): void {
		$this->settings(
			array(
				'general'     => array( 'brand_name' => 'Kaffeerösterei Nord' ),
				'submissions' => array( 'corrections_enabled' => true ),
			)
		);
		$mail = ( new Notifier( Plugin::instance()->settings() ) )->message( Notifier::EVENT_REPORT, array( 'reason' => 'spam' ) );
		$this->assertSame( '[Kaffeerösterei Nord] Bewertung gemeldet', $mail['subject'] );

		$r                  = new Review();
		$r->origin          = Origin::LOCAL_SUBMISSION;
		$r->rating_value    = 4.0;
		$r->body            = 'Ursprünglicher Bewertungstext mit genug Zeichen.';
		$r->author_name     = 'Erika';
		$r->author_email    = 'erika@example.org';
		$r->status          = ReviewStatus::APPROVED;
		$r->consent_publish = true;
		Plugin::instance()->reviews()->insert( $r );
		( new CorrectionHandler( Plugin::instance() ) )->process(
			array(
				'op'                            => 'request',
				'rc_email'                      => 'erika@example.org',
				CorrectionHandler::NONCE_FIELD => wp_create_nonce( CorrectionHandler::NONCE_ACTION ),
			)
		);
		$this->assertCount( 1, \RC_Test_State::$mails );
		$this->assertStringContainsString( 'Kaffeerösterei Nord', \RC_Test_State::$mails[0]['subject'] );
	}
}
