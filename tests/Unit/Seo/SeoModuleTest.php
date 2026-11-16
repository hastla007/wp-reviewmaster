<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Seo;

use ReviewCaptain\Domain\Kind;
use ReviewCaptain\Domain\Platform;
use ReviewCaptain\Domain\ReviewStatus;
use ReviewCaptain\Plugin;
use ReviewCaptain\Seo\Robots;
use ReviewCaptain\Seo\SeoEnvironment;
use ReviewCaptain\Seo\SeoModule;
use ReviewCaptain\Seo\SeoStatus;
use ReviewCaptain\Tests\Unit\Frontend\FrontendTestCase;

final class SeoModuleTest extends FrontendTestCase {

	private SeoModule $seo;

	protected function setUp(): void {
		parent::setUp();
		\RC_Test_State::$queried_id = 10; // Single page of product 10 (QA F-09: main entity).
		$this->seo = new SeoModule();
		$this->seo->register( Plugin::instance() );
		$this->seo->set_environment( SeoEnvironment::from_state( array() ) );
	}

	/**
	 * Decode the fallback JSON-LD graph.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function graph(): array {
		$html = $this->seo->fallback_schema();
		if ( '' === $html ) {
			return array();
		}
		$this->assertMatchesRegularExpression( '#^<script type="application/ld\+json" data-rc-schema>.*</script>\s*$#s', $html );
		$json = preg_replace( '#^<script[^>]*>|</script>\s*$#', '', $html );
		$data = json_decode( (string) $json, true );
		$this->assertSame( 'https://schema.org', $data['@context'] );
		return $data['@graph'];
	}

	private function types( array $graph ): array {
		return array_map( static fn( array $n ): string => (string) $n['@type'], $graph );
	}

	public function test_json_ld_only_for_eligible_rendered_reviews(): void {
		$ok = $this->review( array( 'context_type' => 'product', 'context_id' => '10', 'title' => 'Super', 'author_name' => 'Dora', 'author_email' => 'dora@example.test' ) );
		$this->review( array( 'context_type' => 'product', 'context_id' => '10', 'platform' => Platform::GOOGLE ) );
		$this->review( array( 'context_type' => null ) );
		$this->review( array( 'context_type' => 'product', 'context_id' => '10', 'kind' => Kind::TESTIMONIAL ) );

		$this->assertSame( array(), $this->graph(), 'nothing rendered yet → nothing marked up' );

		$this->shortcode( array( 'limit' => '10' ) );
		$graph = $this->graph();
		$this->assertSame( array( 'Product', 'Review' ), $this->types( $graph ) );
		$review = $graph[1];
		$this->assertStringEndsWith( '#/schema/review-captain/review/' . $ok->uuid, $review['@id'] );
		$this->assertSame( 5, $review['reviewRating']['ratingValue'] );
		$this->assertSame( 5, $review['reviewRating']['bestRating'] );
		$this->assertSame( 1, $review['reviewRating']['worstRating'] );
		$this->assertSame( array( '@type' => 'Person', 'name' => 'Dora' ), $review['author'] );
		$this->assertSame( 'Super', $review['name'] );
		$this->assertSame( $graph[0]['@id'], $review['itemReviewed']['@id'] );

		$raw = $this->seo->fallback_schema();
		$this->assertStringNotContainsString( 'dora@', $raw );
		$this->assertStringNotContainsString( 'AggregateRating', $raw );
		$this->assertStringNotContainsString( 'Organization', $raw );
		$this->assertStringNotContainsString( 'LocalBusiness', $raw );
		$this->assertStringNotContainsString( 'WebPage', $raw );
	}

	public function test_only_reviews_of_current_page_are_marked_up(): void {
		\RC_Test_State::$queried_id = 3;
		$a = $this->review( array( 'context_type' => 'post', 'context_id' => '3', 'published_at' => '2026-10-03 10:00:00' ) );
		$b = $this->review( array( 'context_type' => 'post', 'context_id' => '3', 'published_at' => '2026-10-01 10:00:00' ) );
		$this->shortcode( array( 'limit' => '1' ) );
		$raw = $this->seo->fallback_schema();
		$this->assertStringContainsString( $a->uuid, $raw );
		$this->assertStringNotContainsString( $b->uuid, $raw );
	}

	public function test_no_json_ld_for_woo_product_or_foreign_owner_or_yoast(): void {
		$this->review( array( 'context_type' => 'product', 'context_id' => '10' ) );
		$this->shortcode();

		$this->seo->set_environment( SeoEnvironment::from_state( array( 'woocommerce' => true, 'woo_product_ids' => array( '10' ) ) ) );
		$this->assertSame( '', $this->seo->fallback_schema() );

		$this->seo->set_environment( SeoEnvironment::from_state( array( 'foreign' => array( 'Rank Math' ) ) ) );
		$this->assertSame( '', $this->seo->fallback_schema() );

		$this->seo->set_environment( SeoEnvironment::from_state( array( 'yoast_version' => '26.0' ) ) );
		$this->assertSame( '', $this->seo->fallback_schema(), 'with Yoast no own JSON-LD' );

		$this->seo->set_environment( SeoEnvironment::from_state( array( 'yoast_version' => '12.0' ) ) );
		$this->assertSame( '', $this->seo->fallback_schema() );
		$this->assertSame( array(), $this->seo->filter_yoast_graph( array() ) );
	}

	public function test_no_markup_in_preview(): void {
		$this->review( array( 'context_type' => 'product', 'context_id' => '10' ) );
		$this->renderer()->set_preview( true );
		$this->shortcode();
		$this->assertSame( '', $this->seo->fallback_schema() );
	}

	public function test_admin_switch_disables_markup(): void {
		$this->review( array( 'context_type' => 'product', 'context_id' => '10' ) );
		$this->shortcode();
		$this->settings( array( 'display' => array( 'schema_enabled' => false ) ) );
		$this->assertSame( '', $this->seo->fallback_schema() );
	}

	public function test_json_ld_is_script_safe(): void {
		\RC_Test_State::$queried_id = 5;
		$this->review( array( 'context_type' => 'event', 'context_id' => '5', 'body' => 'Böse </script><script>alert(1)</script> & mehr' ) );
		$this->shortcode();
		$raw = $this->seo->fallback_schema();
		$this->assertSame( 1, substr_count( $raw, '</script>' ) );
		$this->assertStringContainsString( '</script>', $raw );
		$this->assertSame( array( 'Event', 'Review' ), $this->types( $this->graph() ) );
	}

	public function test_dedupe_same_review_in_two_widgets(): void {
		$this->review( array( 'context_type' => 'product', 'context_id' => '10' ) );
		$this->shortcode();
		$this->shortcode( array( 'layout' => 'list' ) );
		$this->assertSame( array( 'Product', 'Review' ), $this->types( $this->graph() ) );
	}

	public function test_yoast_graph_array_integration(): void {
		$this->seo->set_environment( SeoEnvironment::from_state( array( 'yoast_version' => '26.0' ) ) );
		$r = $this->review( array( 'context_type' => 'product', 'context_id' => '10' ) );
		$this->review( array( 'platform' => Platform::GOOGLE, 'context_type' => 'product', 'context_id' => '10' ) );
		$this->seo->prime_from_main_content( "<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->\n<!-- wp:review-captain/reviews {\"limit\":5} /-->" );

		$context            = new \stdClass();
		$context->canonical = 'https://example.test/produkt/';
		$graph              = array(
			array( '@type' => 'WebPage', '@id' => 'https://example.test/produkt/#webpage' ),
			array( '@type' => 'Organization', '@id' => 'https://example.test/#organization' ),
		);
		$out = $this->seo->filter_yoast_graph( $graph, $context );
		$this->assertCount( 4, $out );
		$this->assertSame( 'Organization', $out[1]['@type'], 'Yoast nodes untouched' );
		$this->assertSame( 'Product', $out[2]['@type'] );
		$this->assertSame( array( '@id' => 'https://example.test/produkt/#webpage' ), $out[2]['mainEntityOfPage'] );
		$this->assertSame( 'https://example.test/produkt/#/schema/review-captain/review/' . $r->uuid, $out[3]['@id'] );

		// The piece API is unavailable in tests, so the pieces filter leaves the list untouched.
		$this->assertSame( array(), $this->seo->add_yoast_piece( array() ) );
	}

	public function test_prime_from_shortcode_content(): void {
		\RC_Test_State::$queried_id = 3;
		$r = $this->review( array( 'context_type' => 'post', 'context_id' => '3' ) );
		$this->seo->prime_from_main_content( 'Text [review_captain v="1" platform="local" limit="3"] mehr [review_captain_form]' );
		$this->assertTrue( $this->visible()->has( $r ) );
		$this->assertCount( 2, $this->graph() );
	}

	public function test_registers_no_head_meta_hooks(): void {
		foreach ( array( 'wp_head', 'pre_get_document_title', 'document_title_parts', 'wpseo_title', 'wpseo_metadesc', 'wpseo_canonical', 'get_canonical_url', 'wpseo_opengraph_title', 'wp_sitemaps_add_provider', 'wpseo_sitemap_index' ) as $hook ) {
			$this->assertFalse( has_filter( $hook ), $hook );
		}
		$this->assertNotFalse( has_filter( 'wp_robots' ) );
		$this->assertNotFalse( has_action( 'wp_footer' ) );
	}

	public function test_wp_robots_only_with_rc_params(): void {
		$base = array( 'index' => true, 'follow' => true, 'max-image-preview' => 'large' );
		$this->assertSame( $base, Robots::filter_wp_robots( $base ) );

		foreach ( array( 'rc_page' => '2', 'rc_submitted' => '1', 'rc_error' => 'x', 'rc_page ' => 'abc' ) as $param => $value ) {
			$_GET = array( trim( $param ) => $value );
			$out  = Robots::filter_wp_robots( $base );
			$this->assertTrue( $out['noindex'], $param );
			$this->assertTrue( $out['follow'] );
			$this->assertArrayNotHasKey( 'index', $out );
			$this->assertSame( 'large', $out['max-image-preview'] );
		}

		// Never removes nofollow, never adds follow next to it.
		$_GET = array( 'rc_page' => '2' );
		$out  = Robots::filter_wp_robots( array( 'noindex' => true, 'nofollow' => true ) );
		$this->assertTrue( $out['nofollow'] );
		$this->assertArrayNotHasKey( 'follow', $out );

		$yoast = Robots::filter_yoast_robots( array( 'index' => 'index', 'follow' => 'nofollow' ) );
		$this->assertSame( array( 'index' => 'noindex', 'follow' => 'nofollow' ), $yoast );

		$_GET = array( 'utm_source' => 'x' );
		$this->assertSame( array( 'index' => 'index', 'follow' => 'follow' ), Robots::filter_yoast_robots( array( 'index' => 'index', 'follow' => 'follow' ) ) );
	}

	public function test_status_report(): void {
		$report = SeoStatus::report( SeoEnvironment::from_state( array() ) );
		$this->assertNotEmpty( $report );
		foreach ( $report as $item ) {
			$this->assertSame( array( 'label', 'status', 'message' ), array_keys( $item ) );
			$this->assertContains( $item['status'], array( 'ok', 'warn', 'info' ) );
		}
		$this->assertStringContainsString( 'Fallback', $report[1]['message'] );

		$yoast = SeoStatus::report( SeoEnvironment::from_state( array( 'yoast_version' => '26.0', 'yoast_premium' => true ) ) );
		$this->assertSame( 'ok', $yoast[0]['status'] );
		$this->assertStringContainsString( '26.0', $yoast[0]['message'] );
		$this->assertSame( 'ok', $yoast[1]['status'] );

		$old = SeoStatus::report( SeoEnvironment::from_state( array( 'yoast_version' => '13.0' ) ) );
		$this->assertSame( 'warn', $old[0]['status'] );
		$this->assertSame( 'warn', $old[1]['status'] );

		$conflict = SeoStatus::report( SeoEnvironment::from_state( array( 'yoast_version' => '26.0', 'foreign' => array( 'Rank Math' ) ) ) );
		$messages = implode( ' ', array_column( $conflict, 'message' ) );
		$this->assertStringContainsString( 'Rank Math', $messages );
		$this->assertStringContainsString( 'Mehrere SEO-Plugins', $messages );
		$this->assertSame( 'warn', $conflict[1]['status'] );
	}

	public function test_pending_review_never_in_schema_even_if_collected(): void {
		$r = $this->review( array( 'context_type' => 'product', 'context_id' => '10', 'status' => ReviewStatus::PENDING ) );
		$this->visible()->add( $r );
		$this->assertSame( '', $this->seo->fallback_schema() );
	}

	public function test_no_markup_on_overview_or_foreign_page(): void {
		$this->review( array( 'context_type' => 'product', 'context_id' => '10' ) );
		\RC_Test_State::$queried_id = 0; // Archive / overview page (QA F-09).
		$this->shortcode();
		$this->assertSame( '', $this->seo->fallback_schema() );
		\RC_Test_State::$queried_id = 99; // Single page of another item.
		$this->assertSame( '', $this->seo->fallback_schema() );
	}
}
