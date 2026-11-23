<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\M7;

use ReviewCaptain\Admin\QuestionsPage;
use ReviewCaptain\Frontend\FormHandler;
use ReviewCaptain\Plugin;
use ReviewCaptain\Privacy\PersonalData;
use ReviewCaptain\Questions\QuestionHandler;
use ReviewCaptain\Questions\QuestionRepository;
use ReviewCaptain\Security\Capabilities;
use ReviewCaptain\Seo\SeoModule;

/**
 * RC-122: product Q&A separate from reviews, moderated, without schema markup.
 */
final class QuestionsTest extends M7TestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->enable( array( 'submissions' => array( 'qa_enabled' => true ) ) );
	}

	private function handler(): QuestionHandler {
		/** @var QuestionHandler */
		return Plugin::instance()->get( 'question_handler' );
	}

	private function ask( array $o = array() ): array {
		return $this->handler()->process(
			$o + array(
				'_rc_q_nonce'     => wp_create_nonce( QuestionHandler::NONCE_ACTION ),
				'rc_ts'           => FormHandler::sign_timestamp( time() - 10 ),
				'rc_context_type' => 'product',
				'rc_context_id'   => '42',
				'rc_context_sig'  => FormHandler::sign_context( 'product', '42' ),
				'rc_return'       => 'https://example.test/produkt/42/',
				'rc_question'     => 'Ist das Gerät auch für Linkshänder geeignet?',
				'rc_name'         => 'Tom',
				'rc_email'        => 'tom@example.test',
				'rc_consent'      => '1',
			)
		);
	}

	public function test_question_is_stored_separately_and_pending(): void {
		$out = $this->ask();
		$this->assertTrue( $out['ok'], implode( ',', $out['errors'] ) );
		$this->assertSame( 'pending', $out['question']['status'] );
		$this->assertSame( 0, $this->rows( 'reviews' ), 'never mixed into reviews' );
		$this->assertSame( 1, $this->rows( 'questions' ) );
		$this->assertSame( 0, did_action( 'review_captain_review_created_v1' ) );
		$this->assertSame( 'https://example.test/produkt/42/?rc_question=1#rc-qa', $out['redirect'] );
	}

	public function test_validation_and_protection(): void {
		$this->assertSame( array( 'nonce' ), $this->ask( array( '_rc_q_nonce' => 'x' ) )['errors'] );
		$this->assertSame( array( 'context' ), $this->ask( array( 'rc_context_id' => '43' ) )['errors'] );
		$this->assertSame( array( 'question' ), $this->ask( array( 'rc_question' => 'Warum?' ) )['errors'] );
		$this->assertSame( array( 'email' ), $this->ask( array( 'rc_email' => 'kaputt' ) )['errors'] );
		$this->assertSame( array( 'consent' ), $this->ask( array( 'rc_consent' => '' ) )['errors'] );
		$this->assertTrue( $this->ask( array( 'rc_website' => 'spam' ) )['ok'], 'honeypot pretends success' );
		$this->assertSame( 0, $this->rows( 'questions' ) );
		$this->assertTrue( $this->ask( array( 'rc_email' => '' ) )['ok'], 'e-mail is optional' );
	}

	public function test_rate_limit(): void {
		$this->enable( array( 'submissions' => array( 'rate_limit_per_hour' => 2 ) ) );
		$this->ask( array( 'rc_question' => 'Ist das Gerät auch für Linkshänder geeignet?' ) );
		$this->ask( array( 'rc_question' => 'Gibt es das Gerät auch in Blau zu kaufen?' ) );
		$this->assertSame( array( 'rate_limited' ), $this->ask( array( 'rc_question' => 'Wie lange hält der Akku bei Dauerbetrieb?' ) )['errors'] );
	}

	public function test_double_click_stores_one_question_and_notifies_once(): void {
		$post   = array( 'rc_ts' => FormHandler::sign_timestamp( time() - 10 ) );
		$first  = $this->ask( $post );
		$mails  = count( \RC_Test_State::$mails );
		$second = $this->ask( $post );
		$this->assertTrue( $second['ok'] );
		$this->assertSame( $first['redirect'], $second['redirect'] );
		$this->assertSame( $first['question']['id'], $second['question']['id'] );
		$this->assertSame( 1, $this->rows( 'questions' ) );
		$this->assertSame( $mails, count( \RC_Test_State::$mails ), 'no second notification' );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-a[0-9a-f]{3}-[0-9a-f]{12}$/', (string) $first['question']['uuid'] );

		$other = $this->ask( $post + array( 'rc_question' => 'Und passt das Gerät in eine normale Schublade?' ) );
		$this->assertNotSame( $first['question']['id'], $other['question']['id'], 'another question from the same page is stored' );
	}

	public function test_only_approved_questions_are_public_without_schema(): void {
		$repo    = new QuestionRepository();
		$pending = $this->ask()['question'];
		$second  = $this->ask( array( 'rc_question' => 'Wie lange hält der Akku ungefähr?' ) )['question'];
		$repo->change_status( (int) $second['id'], 'pending', 'approved' );
		$repo->answer( (int) $second['id'], 'Etwa acht Stunden im Normalbetrieb.', 1 );

		$html = $this->module->shortcode_qa( array( 'v' => '1', 'context_type' => 'product', 'context_id' => '42' ) );
		$this->assertStringContainsString( 'Wie lange hält der Akku', $html );
		$this->assertStringContainsString( 'Etwa acht Stunden', $html );
		$this->assertStringNotContainsString( 'Linkshänder', $html );
		$this->assertStringNotContainsString( 'application/ld+json', $html );
		$this->assertStringNotContainsString( 'itemprop', $html );
		$this->assertStringContainsString( 'name="_rc_q_nonce"', $html );
		$this->assertStringNotContainsString( 'tom@example.test', $html );
		$other = $this->module->shortcode_qa( array( 'v' => '1', 'context_type' => 'product', 'context_id' => '7' ) );
		$this->assertStringNotContainsString( 'Akku', $other );
		$this->assertStringContainsString( 'Review Captain: Q&A', $this->module->shortcode_qa( array( 'v' => '1' ) ) );

		$seo = new SeoModule();
		$seo->register( Plugin::instance() );
		$this->assertSame( '', $seo->fallback_schema(), 'questions never feed the SEO collector' );
		$this->assertSame( 'pending', $repo->find( (int) $pending['id'] )['status'] );
	}

	public function test_admin_moderation_requires_capability_and_nonce(): void {
		\RC_Test_State::$is_admin = true;
		$q = $this->ask()['question'];
		$this->login_with( Capabilities::MODERATE );
		$post              = array( 'id' => (string) $q['id'], 'from' => 'pending', 'to' => 'approved', 'answer' => 'Ja, beidseitig nutzbar.' );
		$post['_rc_nonce'] = wp_create_nonce( 'review_captain_moderate_question' );
		$_POST             = $post;
		$_REQUEST          = $post;
		try {
			( new QuestionsPage( Plugin::instance() ) )->handle();
		} catch ( \RC_Test_Die $e ) {
			$this->assertStringContainsString( 'question_saved', $e->getMessage() );
		}
		$row = ( new QuestionRepository() )->find( (int) $q['id'] );
		$this->assertSame( 'approved', $row['status'] );
		$this->assertSame( 'Ja, beidseitig nutzbar.', $row['answer'] );

		$_POST['_rc_nonce'] = 'bad';
		$_REQUEST           = $_POST;
		$this->expectException( \RC_Test_Die::class );
		( new QuestionsPage( Plugin::instance() ) )->handle();
	}

	public function test_privacy_export_and_erase_cover_questions(): void {
		$this->ask();
		$pd     = new PersonalData( Plugin::instance()->reviews(), Plugin::instance()->settings() );
		$export = $pd->export( 'tom@example.test' );
		$this->assertSame( 'review-captain-questions', $export['data'][0]['group_id'] );
		$erase = $pd->erase( 'tom@example.test' );
		$this->assertTrue( $erase['items_removed'] );
		$row = ( new QuestionRepository() )->query()[0];
		$this->assertSame( 'Anonym', $row['author_name'] );
		$this->assertNull( $row['author_email'] );
	}
}
