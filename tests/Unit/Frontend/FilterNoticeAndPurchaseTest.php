<?php
/**
 * SET-RC-03 (owner decision E3) and SET-RC-32 (owner decision E6), 24.11.2026.
 *
 * Minimal WooCommerce stand-ins (wc_get_orders and order/item objects) live in the global
 * namespace; without orders registered in $GLOBALS['rc_test_wc_orders'] they find nothing.
 */

declare(strict_types=1);

namespace {
	if ( ! function_exists( 'wc_get_orders' ) ) {
		/**
		 * Test stand-in: orders of $args['customer_id'], newest first.
		 *
		 * @param array<string, mixed> $args Query.
		 * @return list<object>
		 */
		function wc_get_orders( $args ) {
			$GLOBALS['rc_test_wc_queries'][] = $args;
			return $GLOBALS['rc_test_wc_orders'][ (int) ( $args['customer_id'] ?? 0 ) ] ?? array();
		}
	}

	/**
	 * Test stand-in for WC_Order.
	 */
	final class RC_Test_WC_Order {
		/**
		 * @param list<RC_Test_WC_Item> $items Items.
		 * @param int                   $paid  Paid timestamp.
		 */
		public function __construct( private array $items, private int $paid ) {}
		public function get_items(): array {
			return $this->items;
		}
		public function get_date_paid(): \DateTimeImmutable {
			return ( new \DateTimeImmutable() )->setTimestamp( $this->paid );
		}
	}

	/**
	 * Test stand-in for WC_Order_Item_Product.
	 */
	final class RC_Test_WC_Item {
		public function __construct( private int $product, private int $variation = 0 ) {}
		public function get_product_id(): int {
			return $this->product;
		}
		public function get_variation_id(): int {
			return $this->variation;
		}
	}
}

namespace ReviewCaptain\Tests\Unit\Frontend {

	use ReviewCaptain\Admin\ConfigTransfer;
	use ReviewCaptain\Domain\Platform;
	use ReviewCaptain\Domain\Verification;
	use ReviewCaptain\Frontend\FormHandler;
	use ReviewCaptain\Frontend\PurchaseGate;
	use ReviewCaptain\Plugin;
	use ReviewCaptain\Settings\Settings;
	use ReviewCaptain\Tests\Unit\M7\M7TestCase;
	use ReviewCaptain\Widgets\WidgetDefinition;

	final class FilterNoticeAndPurchaseTest extends M7TestCase {

		protected function setUp(): void {
			parent::setUp();
			update_option( 'admin_email', 'admin@example.test' );
			$GLOBALS['rc_test_wc_orders']  = array();
			$GLOBALS['rc_test_wc_queries'] = array();
		}

		protected function tearDown(): void {
			$GLOBALS['rc_test_wc_orders'] = array();
			parent::tearDown();
		}

		// ------------------------------------------------------------ settings

		public function test_defaults_sanitize_export_and_reset(): void {
			$s = new Settings();
			$this->assertTrue( $s->get( 'display', 'allow_min_rating' ) );
			$this->assertSame( '', $s->get( 'display', 'filter_notice' ) );
			$this->assertFalse( $s->get( 'submissions', 'require_purchase' ) );
			$this->assertSame( 0, $s->get( 'submissions', 'purchase_max_age_days' ) );

			$out = $s->sanitize(
				array(
					'display'     => array( 'filter_notice' => '<b>Nur</b> ' . str_repeat( 'x', 400 ) ),
					'submissions' => array( 'purchase_max_age_days' => 99999 ),
				)
			);
			$this->assertStringNotContainsString( '<b>', $out['display']['filter_notice'] );
			$this->assertSame( 300, mb_strlen( $out['display']['filter_notice'] ) );
			$this->assertSame( 3650, $out['submissions']['purchase_max_age_days'] );
			$low = $s->sanitize( array( 'submissions' => array( 'purchase_max_age_days' => -3 ) ) );
			$this->assertGreaterThanOrEqual( 0, $low['submissions']['purchase_max_age_days'] );

			$this->enable(
				array(
					'display'     => array( 'allow_min_rating' => false, 'filter_notice' => 'Gefiltert ab {stars}' ),
					'submissions' => array( 'require_purchase' => true, 'purchase_max_age_days' => 30 ),
				)
			);
			$doc = ( new ConfigTransfer( Plugin::instance()->settings() ) )->export();
			$this->assertFalse( $doc['settings']['display']['allow_min_rating'] );
			$this->assertSame( 'Gefiltert ab {stars}', $doc['settings']['display']['filter_notice'] );
			$this->assertTrue( $doc['settings']['submissions']['require_purchase'] );
			$this->assertSame( 30, $doc['settings']['submissions']['purchase_max_age_days'] );

			$settings = Plugin::instance()->settings();
			$settings->reset( array( 'display', 'submissions' ) );
			$settings->flush();
			$this->assertTrue( $settings->get( 'display', 'allow_min_rating' ) );
			$this->assertSame( '', $settings->get( 'display', 'filter_notice' ) );
			$this->assertFalse( $settings->get( 'submissions', 'require_purchase' ) );
			$this->assertSame( 0, $settings->get( 'submissions', 'purchase_max_age_days' ) );
		}

		// ------------------------------------------------------------ SET-RC-03

		public function test_filter_notice_shown_and_summary_over_all_reviews(): void {
			$this->review( array( 'rating_value' => 5.0 ) );
			$this->review( array( 'rating_value' => 5.0 ) );
			$this->review( array( 'rating_value' => 2.0 ) );

			$plain = $this->shortcode( array( 'v' => '1' ) );
			$this->assertStringNotContainsString( 'rc-filter-notice', $plain, 'no filter, no notice' );

			$out = $this->shortcode( array( 'min_rating' => '4' ) );
			$this->assertStringContainsString( '<p class="rc-filter-notice">Es werden nur Bewertungen ab 4 Sternen angezeigt.</p>', $out );
			$this->assertSame( 2, substr_count( $out, '<article' ) );
			$this->assertStringContainsString( 'Diese Website: 4 von 5 (3 Bewertungen)', $out, 'summary unaffected by the star filter' );
			$this->assertLessThan( strpos( $out, 'rc-widget__items' ), strpos( $out, 'rc-filter-notice' ), 'notice above the cards' );

			$this->assertStringContainsString( 'ab 4,5 Sternen', $this->shortcode( array( 'min_rating' => '4.5' ) ) );
			$this->assertStringNotContainsString( 'rc-filter-notice', $this->shortcode( array( 'min_rating' => '1' ) ), '1 star = everything' );
		}

		public function test_filter_notice_for_platform_custom_text_and_public_text_filter(): void {
			$this->review( array( 'platform' => Platform::GOOGLE, 'rating_value' => 4.0 ) );
			$this->assertStringContainsString( 'Es werden nur Bewertungen von Google angezeigt.', $this->shortcode( array( 'platform' => 'google' ) ) );
			$this->assertStringContainsString( 'Es werden nur Bewertungen von Google ab 3 Sternen angezeigt.', $this->shortcode( array( 'platform' => 'google', 'min_rating' => '3' ) ) );

			$this->enable( array( 'display' => array( 'filter_notice' => 'Auswahl: {platform}, ab {stars} Sternen' ) ) );
			$this->assertStringContainsString( 'Auswahl: Google, ab 1 Sternen', $this->shortcode( array( 'platform' => 'google' ) ) );
			$this->assertStringContainsString( 'Auswahl: allen Plattformen, ab 4 Sternen', $this->shortcode( array( 'min_rating' => '4' ) ) );

			add_filter(
				'review_captain_public_text',
				static fn( string $text, string $id ): string => 'display.filter_notice' === $id ? '<i>Gefiltert</i> {stars}' : $text,
				10,
				2
			);
			$out = $this->shortcode( array( 'min_rating' => '4' ) );
			$this->assertStringContainsString( '&lt;i&gt;Gefiltert&lt;/i&gt; 4', $out, 'filter wins, output escaped' );
		}

		public function test_switch_off_ignores_min_rating_everywhere(): void {
			$this->review( array( 'rating_value' => 5.0 ) );
			$this->review( array( 'rating_value' => 2.0 ) );
			$this->enable( array( 'display' => array( 'allow_min_rating' => false ) ) );

			$out = $this->shortcode( array( 'min_rating' => '4' ) );
			$this->assertSame( 2, substr_count( $out, '<article' ), 'shortcode ignores min_rating' );
			$this->assertStringNotContainsString( 'rc-filter-notice', $out );

			$block = WidgetDefinition::from_block_attributes( array( 'minRating' => 4 ) );
			$this->assertNull( $block->min_rating, 'block (and Elementor, which maps to the same attributes) ignore it' );
			$this->assertContains( 'min_rating_disabled', $block->notices );
			$this->assertArrayNotHasKey( 'min_normalized', $block->query_args() );

			add_filter( 'review_captain_allow_min_rating', '__return_true' );
			$this->assertSame( 4.0, WidgetDefinition::from_atts( array( 'min_rating' => '4' ) )->min_rating, 'filter wins over the setting' );
		}

		// ------------------------------------------------------------ SET-RC-32

		/**
		 * Register a paid order of user 7 for a product.
		 *
		 * @param int $product  Product ID.
		 * @param int $days_ago Paid this many days ago.
		 */
		private function order( int $product, int $days_ago = 2 ): void {
			$GLOBALS['rc_test_wc_orders'][7][] = new \RC_Test_WC_Order( array( new \RC_Test_WC_Item( 99 ), new \RC_Test_WC_Item( $product ) ), time() - $days_ago * DAY_IN_SECONDS );
		}

		public function test_off_by_default_keeps_unverified_form(): void {
			$this->assertStringContainsString( 'name="rc_rating"', $this->module->shortcode_form( array( 'context_type' => 'product', 'context_id' => '42' ) ) );
			$result = $this->form()->process( $this->form_post( array(), 'product', '42' ) );
			$this->assertTrue( $result['ok'] );
			$this->assertSame( Verification::UNVERIFIED, $result['review']->verification );
		}

		public function test_require_purchase_blocks_guests_and_non_buyers_with_message(): void {
			$this->enable( array( 'submissions' => array( 'require_purchase' => true ) ) );

			$form = $this->module->shortcode_form( array( 'context_type' => 'product', 'context_id' => '42' ) );
			$this->assertStringNotContainsString( '<form', $form );
			$this->assertStringContainsString( 'rc-form--disabled', $form );
			$this->assertStringContainsString( 'melden Sie sich mit Ihrem Kundenkonto an', $form );

			$before = $this->rows( 'reviews' );
			$result = $this->form()->process( $this->form_post( array(), 'product', '42' ) );
			$this->assertFalse( $result['ok'] );
			$this->assertSame( array( 'purchase_login' ), $result['errors'] );
			$this->assertStringContainsString( 'rc_error=purchase_login', $result['redirect'] );
			$this->assertSame( $before, $this->rows( 'reviews' ), 'nothing stored, nothing dropped silently' );

			\RC_Test_State::$user_id = 7;
			$this->order( 41 );
			$form = $this->module->shortcode_form( array( 'context_type' => 'product', 'context_id' => '42' ) );
			$this->assertStringContainsString( 'kein bestätigter Kauf vor', $form, 'logged in, but bought another product' );
			$result = $this->form()->process( $this->form_post( array(), 'product', '42' ) );
			$this->assertSame( array( 'purchase_required' ), $result['errors'] );

			$this->assertStringContainsString( 'kein bestätigter Kauf vor', $this->module->shortcode_form( array() ), 'form without product only via invitation' );
			$this->assertSame( $before, $this->rows( 'reviews' ) );
		}

		public function test_require_purchase_accepts_buyer_and_marks_purchase_verified(): void {
			$this->enable( array( 'submissions' => array( 'require_purchase' => true ) ) );
			\RC_Test_State::$user_id = 7;
			$this->order( 42 );

			$form = $this->module->shortcode_form( array( 'context_type' => 'product', 'context_id' => '42' ) );
			$this->assertStringContainsString( 'name="rc_rating"', $form );
			$this->assertStringContainsString( '„Verifizierter Kauf“', $form, 'verification notice names the confirmed level' );

			$result = $this->form()->process( $this->form_post( array(), 'product', '42' ) );
			$this->assertTrue( $result['ok'] );
			$stored = Plugin::instance()->reviews()->find( (int) $result['review']->id );
			$this->assertSame( Verification::PURCHASE_VERIFIED, $stored->verification );
			$this->assertSame( 'woocommerce', $stored->meta['verification_source'] );
			$this->assertSame( 7, $GLOBALS['rc_test_wc_queries'][0]['customer_id'] );
			$this->assertNotEmpty( $GLOBALS['rc_test_wc_queries'][0]['status'], 'paid statuses only' );
		}

		public function test_purchase_age_limit(): void {
			$this->enable( array( 'submissions' => array( 'require_purchase' => true, 'purchase_max_age_days' => 30 ) ) );
			\RC_Test_State::$user_id = 7;
			$this->order( 42, 40 );

			$form = $this->module->shortcode_form( array( 'context_type' => 'product', 'context_id' => '42' ) );
			$this->assertStringNotContainsString( '<form', $form );
			$this->assertStringContainsString( 'nur bis 30 Tage nach dem Kauf möglich', $form );
			$result = $this->form()->process( $this->form_post( array(), 'product', '42' ) );
			$this->assertSame( array( 'purchase_too_old' ), $result['errors'] );

			$GLOBALS['rc_test_wc_orders'] = array();
			$this->order( 42, 10 );
			$this->assertTrue( $this->form()->process( $this->form_post( array( 'rc_email' => 'neu@example.test' ), 'product', '42' ) )['ok'] );

			$this->enable( array( 'submissions' => array( 'purchase_max_age_days' => 0 ) ) );
			$GLOBALS['rc_test_wc_orders'] = array();
			$this->order( 42, 2000 );
			$this->assertTrue( ( new PurchaseGate( Plugin::instance() ) )->check( 'product', '42' )['ok'], '0 = unlimited' );
		}

		public function test_invitation_link_via_trusted_source_filter(): void {
			$this->enable( array( 'submissions' => array( 'require_purchase' => true, 'purchase_max_age_days' => 30 ) ) );
			add_filter(
				'review_captain_purchase_proof',
				static function ( $proof, array $context ) {
					if ( 'tok-123' === $context['invite'] ) {
						return array( 'level' => 'attendance_confirmed', 'confirmed_at' => time() - DAY_IN_SECONDS, 'source' => 'invitation' );
					}
					if ( 'tok-forged' === $context['invite'] ) {
						return array( 'level' => 'unverified' );
					}
					if ( 'tok-nodate' === $context['invite'] ) {
						return array( 'level' => 'purchase_verified' );
					}
					return $proof;
				},
				10,
				2
			);

			$_GET[ PurchaseGate::INVITE_FIELD ] = 'tok-123';
			$form                              = $this->module->shortcode_form( array( 'context_type' => 'event', 'context_id' => 'e1' ) );
			$this->assertStringContainsString( 'name="rc_invite" value="tok-123"', $form );

			$result = $this->form()->process( $this->form_post( array( 'rc_invite' => 'tok-123' ), 'event', 'e1' ) );
			$this->assertTrue( $result['ok'] );
			$this->assertSame( Verification::ATTENDANCE_CONFIRMED, Plugin::instance()->reviews()->find( (int) $result['review']->id )->verification );

			$forged = $this->form()->process( $this->form_post( array( 'rc_invite' => 'tok-forged', 'rc_email' => 'b@example.test' ), 'event', 'e1' ) );
			$this->assertSame( array( 'purchase_required' ), $forged['errors'], 'only purchase/attendance levels count' );
			$nodate = $this->form()->process( $this->form_post( array( 'rc_invite' => 'tok-nodate', 'rc_email' => 'c@example.test' ), 'event', 'e1' ) );
			$this->assertSame( array( 'purchase_required' ), $nodate['errors'], 'an age limit needs a purchase date' );
			$this->assertSame( '', PurchaseGate::invite_token( array( 'rc_invite' => '<script>' ) ) );
		}

		public function test_messages_exist_for_all_codes(): void {
			foreach ( PurchaseGate::CODES as $code ) {
				$this->assertNotSame( '', FormHandler::error_message( $code ), $code );
			}
		}
	}
}
