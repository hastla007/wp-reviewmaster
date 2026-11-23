<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Frontend;

use ReviewCaptain\Domain\Kind;
use ReviewCaptain\Domain\Platform;
use ReviewCaptain\Domain\ReviewStatus;
use ReviewCaptain\Domain\Verification;
use ReviewCaptain\Frontend\FrontendModule;
use ReviewCaptain\Install\Schema;

final class RendererTest extends FrontendTestCase {

	public function test_shortcode_and_block_render_identically(): void {
		$this->review( array( 'title' => 'Top' ) );
		$this->review( array( 'platform' => Platform::GOOGLE, 'rating_value' => 4.0 ) );

		$short = $this->shortcode( array( 'v' => '1', 'layout' => 'list', 'limit' => '5', 'kind' => 'review', 'min_rating' => '3' ) );
		$this->renderer()->reset_instances();
		$block = $this->module->render_reviews_block(
			array(
				'v'           => '1',
				'layout'      => 'list',
				'limit'       => 5,
				'kind'        => 'review',
				'minRating'   => 3,
				'columns'     => 3,
				'platform'    => '',
				'contextType' => '',
				'contextId'   => '',
				'profile'     => 0,
				'showSource'  => true,
				'showSummary' => true,
				'paginate'    => false,
			)
		);
		$this->assertSame( $short, $block );
		$this->assertStringContainsString( 'rc-widget--list', $short );
		$this->assertSame( 2, substr_count( $short, '<article' ) );
	}

	public function test_blocks_registered_with_render_callback(): void {
		$this->module->on_init();
		$this->assertArrayHasKey( REVIEW_CAPTAIN_DIR . 'blocks/reviews', $GLOBALS['rc_test_blocks'] );
		$this->assertIsCallable( $GLOBALS['rc_test_blocks'][ REVIEW_CAPTAIN_DIR . 'blocks/form' ]['render_callback'] );
		$this->assertTrue( shortcode_exists( 'review_captain' ) );
		$this->assertTrue( shortcode_exists( 'review_captain_form' ) );
		$this->assertTrue( shortcode_exists( 'review_captain_cta' ) );
		$json = json_decode( (string) file_get_contents( REVIEW_CAPTAIN_DIR . 'blocks/reviews/block.json' ), true );
		$this->assertSame( 'review-captain/reviews', $json['name'] );
		$this->assertSame( 3, $json['apiVersion'] );
	}

	public function test_xss_in_review_fields_is_escaped(): void {
		$this->review(
			array(
				'title'       => '<img src=x onerror=alert(1)>',
				'body'        => "<script>alert('x')</script>\n\nZweiter <b>Absatz</b>",
				'author_name' => '"><svg onload=alert(2)>',
			)
		);
		$out = $this->shortcode();
		$this->assertStringNotContainsString( '<script', $out );
		$this->assertStringNotContainsString( '<img', $out );
		$this->assertStringNotContainsString( '<svg onload', $out );
		$this->assertStringNotContainsString( '<b>', $out );
		$this->assertStringContainsString( '&lt;script&gt;', $out );
		$this->assertStringContainsString( '&quot;&gt;&lt;svg onload=alert(2)&gt;', $out );
	}

	public function test_external_review_always_shows_source_and_link(): void {
		$this->review( array( 'platform' => Platform::TRUSTPILOT, 'source_url' => 'https://de.trustpilot.com/reviews/123' ) );
		$this->review( array( 'author_name' => 'Lokal' ) );
		$out = $this->shortcode( array( 'show_source' => '0' ) );
		$this->assertStringContainsString( 'via Trustpilot', $out );
		$this->assertStringContainsString( 'href="https://de.trustpilot.com/reviews/123"', $out );
		$this->assertStringContainsString( 'rel="nofollow noopener noreferrer ugc"', $out );
		$this->assertStringContainsString( 'rc-card--external', $out );
		$this->assertStringNotContainsString( 'Bewertung auf dieser Website', $out, 'local source label hidden with show_source=0' );

		$with = $this->shortcode( array( 'show_source' => '1' ) );
		$this->assertStringContainsString( 'Bewertung auf dieser Website', $with );
		$this->assertStringNotContainsString( 'target="_blank"', $with );

		// SET-RC-P5: "Links zum Original in neuem Tab" applies to the card link and is announced.
		$tab = $this->shortcode( array( 'new_tab' => '1' ) );
		$this->assertStringContainsString( 'rel="nofollow noopener noreferrer ugc" target="_blank">Original auf Trustpilot ansehen<span class="rc-visually-hidden"> (öffnet in neuem Tab)</span></a>', $tab );
	}

	public function test_report_status_message_is_a_focusable_jump_target(): void {
		$this->review( array( 'author_name' => 'Lokal' ) );
		$_GET['rc_reported'] = '1';
		$out = $this->shortcode();
		unset( $_GET['rc_reported'] );
		// SET-RC-P5: the report redirect ends in #rc-report-notice; the message takes focus.
		$this->assertStringContainsString( '<p class="rc-notice" id="rc-report-notice" role="status" tabindex="-1" autofocus>', $out );
	}

	public function test_testimonial_and_verified_badges(): void {
		$this->review( array( 'kind' => Kind::TESTIMONIAL, 'author_name' => 'T' ) );
		$this->review( array( 'verification' => Verification::PURCHASE_VERIFIED, 'author_name' => 'K' ) );
		$this->review( array( 'author_name' => 'U' ) );
		$out = $this->shortcode();
		// Count the badges only (the verification notice quotes the labels, SET-RC-01).
		$this->assertSame( 1, substr_count( $out, '<span class="rc-badge rc-badge--testimonial">Erfahrungsbericht dieser Website</span>' ) );
		$this->assertSame( 1, substr_count( $out, '<span class="rc-verified">Verifizierter Kauf</span>' ) );
		$this->assertStringContainsString( 'rc-card--testimonial', $out );

		$only = $this->shortcode( array( 'kind' => 'testimonial' ) );
		$this->assertSame( 1, substr_count( $only, '<article' ) );
	}

	public function test_only_approved_reviews_are_rendered(): void {
		$this->review( array( 'body' => 'Freigegebener Text hier', 'author_name' => 'A' ) );
		foreach ( array( ReviewStatus::PENDING, ReviewStatus::REJECTED, ReviewStatus::FLAGGED, ReviewStatus::ARCHIVED ) as $status ) {
			$this->review( array( 'status' => $status, 'body' => 'Geheim ' . $status->value ) );
		}
		$out = $this->shortcode( array( 'limit' => '50' ) );
		$this->assertSame( 1, substr_count( $out, '<article' ) );
		$this->assertStringNotContainsString( 'Geheim', $out );
	}

	public function test_summary_is_separate_per_platform(): void {
		$this->review( array( 'rating_value' => 4.0 ) );
		$this->review( array( 'rating_value' => 5.0 ) );
		$this->review( array( 'platform' => Platform::GOOGLE, 'rating_value' => 3.0 ) );
		$out = $this->shortcode();
		$this->assertStringContainsString( 'rc-summary', $out );
		$this->assertStringContainsString( 'Diese Website: 4,5 von 5 (2 Bewertungen)', $out );
		$this->assertStringContainsString( 'Google: 3 von 5 (1 Bewertung)', $out );
		$this->assertSame( 2, substr_count( $out, 'rc-summary__item' ), 'no combined score row' );
	}

	public function test_summary_ignores_min_rating_filter(): void {
		$this->review( array( 'rating_value' => 5.0 ) );
		$this->review( array( 'rating_value' => 5.0 ) );
		$this->review( array( 'rating_value' => 2.0 ) );
		$out = $this->shortcode( array( 'min_rating' => '4' ) );
		$this->assertSame( 2, substr_count( $out, '<article' ), 'the filter narrows the cards' );
		$this->assertStringContainsString( 'Diese Website: 4 von 5 (3 Bewertungen)', $out, 'average over all approved reviews, not over the filtered ones' );
	}

	public function test_accessible_stars_on_original_scale(): void {
		$this->review( array( 'rating_value' => 4.0 ) );
		$out = $this->shortcode();
		$this->assertStringContainsString( 'aria-label="4 von 5 Sternen"', $out );
		$this->assertSame( 4, substr_count( $out, 'rc-star--full' ) );
		$this->assertSame( 1, substr_count( $out, 'rc-star--empty' ) );
		$this->assertStringContainsString( '4 / 5', $out );
	}

	public function test_non_five_scale_not_converted_to_stars(): void {
		$this->review( array( 'rating_value' => 8.0, 'rating_scale_max' => 10.0 ) );
		$out = $this->shortcode();
		$this->assertStringContainsString( 'aria-label="8 von 10 Punkten"', $out );
		$this->assertStringNotContainsString( 'rc-star--', $out );
	}

	public function test_filters_platform_and_min_rating(): void {
		$this->review( array( 'rating_value' => 2.0, 'body' => 'Schlecht aber ehrlich bewertet' ) );
		$this->review( array( 'rating_value' => 5.0 ) );
		$this->review( array( 'platform' => Platform::GOOGLE, 'rating_value' => 5.0 ) );
		$this->assertSame( 2, substr_count( $this->shortcode( array( 'min_rating' => '4' ) ), '<article' ) );
		$this->assertSame( 1, substr_count( $this->shortcode( array( 'platform' => 'google' ) ), '<article' ) );
		$this->assertSame( 1, substr_count( $this->shortcode( array( 'limit' => '1' ) ), '<article' ) );
	}

	public function test_empty_state(): void {
		$out = $this->shortcode();
		$this->assertStringContainsString( '<p class="rc-empty">Es sind noch keine Bewertungen vorhanden.</p>', $out );
		$this->assertStringNotContainsString( '<article', $out );
		$this->settings( array( 'display' => array( 'empty_text' => 'Bald <b>mehr</b>' ) ) );
		$this->assertStringContainsString( '<p class="rc-empty">Bald &lt;b&gt;mehr&lt;/b&gt;</p>', $this->shortcode() );
	}

	public function test_display_settings_author_and_date(): void {
		$this->review( array( 'author_name' => 'Berta' ) );
		$this->assertStringContainsString( 'Berta', $this->shortcode() );
		$this->assertStringContainsString( '<time class="rc-date" datetime="2026-10-01T10:00:00+00:00">', $this->shortcode() );
		$this->settings( array( 'display' => array( 'show_author' => false, 'show_date' => false ) ) );
		$out = $this->shortcode();
		$this->assertStringNotContainsString( 'Berta', $out );
		$this->assertStringNotContainsString( 'rc-date', $out );
		$this->assertStringContainsString( 'rc-date', $this->shortcode( array( 'show_date' => '1' ) ), 'attribute overrides setting' );
	}

	public function test_css_enqueued_only_when_rendered_and_enabled(): void {
		$this->assertSame( array(), $GLOBALS['rc_test_styles'] );
		$this->shortcode();
		$this->assertContains( FrontendModule::STYLE_HANDLE, $GLOBALS['rc_test_styles'] );

		$GLOBALS['rc_test_styles'] = array();
		$this->settings( array( 'display' => array( 'load_css' => false ) ) );
		$this->shortcode();
		$this->assertSame( array(), $GLOBALS['rc_test_styles'] );
	}

	public function test_rendered_reviews_are_reported_to_collector_but_not_in_preview(): void {
		$r = $this->review();
		$this->shortcode();
		$this->assertTrue( $this->visible()->has( $r ) );

		$this->visible()->reset();
		$this->renderer()->set_preview( true );
		$this->shortcode();
		$this->assertSame( 0, $this->visible()->count() );
	}

	public function test_pagination_uses_rc_page(): void {
		for ( $i = 0; $i < 3; $i++ ) {
			$this->review( array( 'body' => 'Bewertung Nummer ' . $i . ' mit Text' ) );
		}
		$_SERVER['REQUEST_URI'] = '/seite/';
		$_SERVER['HTTP_HOST']   = 'example.test';
		$first                  = $this->shortcode( array( 'limit' => '2', 'paginate' => '1' ) );
		$this->assertSame( 2, substr_count( $first, '<article' ) );
		$this->assertStringContainsString( 'rc_page=2', $first );

		$_GET['rc_page'] = '2';
		$second          = $this->shortcode( array( 'limit' => '2', 'paginate' => '1' ) );
		$this->assertSame( 1, substr_count( $second, '<article' ) );
		$this->assertStringContainsString( 'rc-pagination__prev', $second );
		unset( $_SERVER['REQUEST_URI'], $_SERVER['HTTP_HOST'] );
	}

	public function test_cta_links_neutrally_to_profile_review_url(): void {
		$now = gmdate( 'Y-m-d H:i:s' );
		$this->db->insert(
			Schema::table( 'profiles' ),
			array(
				'platform'    => 'google',
				'label'       => 'Filiale',
				'profile_url' => 'https://g.page/x',
				'review_url'  => 'https://g.page/r/abc/review',
				'status'      => 'manual',
				'created_at'  => $now,
				'updated_at'  => $now,
			)
		);
		$id  = (int) $this->db->insert_id;
		$out = $this->module->shortcode_cta( array( 'v' => '1', 'profile' => (string) $id ) );
		$this->assertStringContainsString( 'href="https://g.page/r/abc/review"', $out );
		$this->assertStringContainsString( 'Bewerte uns auf Google', $out );
		$this->assertStringContainsString( 'noopener noreferrer', $out );
		$this->assertStringNotContainsString( 'target=', $out );
		$this->assertStringNotContainsString( 'star', $out );
		$this->assertStringNotContainsString( '<input', $out );

		$tab = $this->module->shortcode_cta( array( 'profile' => (string) $id, 'new_tab' => '1' ) );
		$this->assertStringContainsString( 'target="_blank"', $tab );
		$this->assertStringContainsString( 'öffnet in neuem Tab', $tab );

		$this->assertStringStartsWith( '<!--', $this->module->shortcode_cta( array( 'profile' => '999' ) ) );
	}

	public function test_cta_rejects_non_https_profile(): void {
		$now = gmdate( 'Y-m-d H:i:s' );
		$this->db->insert(
			Schema::table( 'profiles' ),
			array(
				'platform'    => 'trustpilot',
				'review_url'  => 'javascript:alert(1)',
				'profile_url' => 'http://insecure.example',
				'status'      => 'manual',
				'created_at'  => $now,
				'updated_at'  => $now,
			)
		);
		$out = $this->module->shortcode_cta( array( 'profile' => (string) $this->db->insert_id ) );
		$this->assertStringNotContainsString( 'href', $out );
	}
}
