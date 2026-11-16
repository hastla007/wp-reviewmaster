<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\M7;

use ReviewCaptain\Frontend\FormHandler;
use ReviewCaptain\Plugin;

/**
 * RC-123: one form rates several contexts (signed context_ids), one review per context and no
 * double reviews per author + context (UNIQUE submission_key).
 */
final class MultiProductFormTest extends M7TestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->enable( array( 'submissions' => array( 'multi_product_enabled' => true ) ) );
	}

	/**
	 * @param array<string, array<string, string>> $items Items.
	 * @param list<string>                         $ids   Signed IDs.
	 */
	private function multi( array $items, array $ids = array( '11', '12', '13' ), array $o = array() ): array {
		return $this->form()->process(
			$this->form_post(
				$o + array(
					'rc_context_ids'     => implode( ',', $ids ),
					'rc_context_ids_sig' => FormHandler::sign_context_list( 'product', $ids ),
					'rc_items'           => $items,
					'rc_rating'          => '',
					'rc_body'            => '',
				),
				'product',
				''
			)
		);
	}

	private function item( string $rating ): array {
		return array(
			'rating' => $rating,
			'body'   => 'Produkt entspricht der Beschreibung, gute Qualität.',
		);
	}

	public function test_form_renders_one_fieldset_per_signed_context(): void {
		$html = $this->module->shortcode_form( array( 'v' => '1', 'context_type' => 'product', 'context_ids' => '11, 12,bad id,11' ) );
		$this->assertStringContainsString( 'name="rc_items[11][rating]"', $html );
		$this->assertStringContainsString( 'name="rc_items[12][body]"', $html );
		$this->assertStringNotContainsString( 'name="rc_rating"', $html );
		$this->assertStringContainsString( 'name="rc_context_ids" value="11,12"', $html );
		$this->assertStringContainsString( 'value="' . FormHandler::sign_context_list( 'product', array( '11', '12' ) ) . '"', $html );
	}

	public function test_each_rated_context_gets_one_review_unrated_are_skipped(): void {
		$res = $this->multi( array( '11' => $this->item( '5' ), '13' => $this->item( '3' ) ) );
		$this->assertTrue( $res['ok'], implode( ',', $res['errors'] ) );
		$this->assertCount( 2, $res['reviews'] );
		$this->assertSame( array( '11', '13' ), array_map( static fn( $r ) => $r->context_id, $res['reviews'] ) );
		$this->assertSame( 'product', $res['reviews'][0]->context_type );
		$this->assertNotNull( $res['reviews'][0]->submission_key );
		$this->assertStringNotContainsString( 'carla', (string) $res['reviews'][0]->submission_key );
	}

	public function test_no_double_review_per_author_and_context(): void {
		$this->multi( array( '11' => $this->item( '5' ) ) );
		$_SERVER['REMOTE_ADDR'] = '198.51.100.20';
		$again = $this->multi( array( '11' => $this->item( '1' ), '12' => $this->item( '4' ) ) );
		$this->assertTrue( $again['ok'] );
		$this->assertCount( 1, $again['reviews'], 'only the new context is saved' );
		$_SERVER['REMOTE_ADDR'] = '198.51.100.21';
		$only_dup = $this->multi( array( '11' => $this->item( '2' ) ), array( '11', '12', '13' ), array( 'rc_email' => 'CARLA@example.test' ) );
		$this->assertSame( array( 'duplicate' ), $only_dup['errors'], 'e-mail case does not matter' );
		$this->assertSame( 2, $this->rows( 'reviews' ) );

		// The single-context form obeys the same rule while the feature is on.
		$_SERVER['REMOTE_ADDR'] = '198.51.100.22';
		$single = $this->form()->process( $this->form_post( array(), 'product', '12' ) );
		$this->assertSame( array( 'duplicate' ), $single['errors'] );
	}

	public function test_tampered_list_and_invalid_items_are_rejected(): void {
		$tampered = $this->form()->process(
			$this->form_post(
				array(
					'rc_context_ids'     => '11,99',
					'rc_context_ids_sig' => FormHandler::sign_context_list( 'product', array( '11', '12' ) ),
					'rc_items'           => array( '99' => $this->item( '5' ) ),
				),
				'product',
				''
			)
		);
		$this->assertSame( array( 'context' ), $tampered['errors'] );
		$this->assertSame( array( 'items_empty' ), $this->multi( array() )['errors'] );
		$this->assertSame( array( 'item_rating' ), $this->multi( array( '11' => $this->item( '9' ) ) )['errors'] );
		$this->assertSame( array( 'item_body_short' ), $this->multi( array( '11' => array( 'rating' => '4', 'body' => 'kurz' ) ) )['errors'] );
		$foreign = $this->multi( array( '77' => $this->item( '5' ) ) );
		$this->assertSame( array( 'items_empty' ), $foreign['errors'], 'items outside the signed list are ignored' );
		$this->assertSame( 0, $this->rows( 'reviews' ) );
	}

	public function test_anonymisation_clears_the_submission_key(): void {
		$res = $this->multi( array( '11' => $this->item( '5' ) ) );
		( new \ReviewCaptain\Privacy\PersonalData( Plugin::instance()->reviews(), Plugin::instance()->settings() ) )->erase( 'carla@example.test' );
		$this->assertNull( Plugin::instance()->reviews()->find( (int) $res['reviews'][0]->id )->submission_key );
	}
}
