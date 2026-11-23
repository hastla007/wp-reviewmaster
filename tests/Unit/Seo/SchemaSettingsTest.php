<?php
/**
 * SET-RC-23: structured data per content type, minimum number, diagnostics.
 */

declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Seo;

use ReviewCaptain\Domain\Kind;
use ReviewCaptain\Plugin;
use ReviewCaptain\Seo\SchemaEligibility;
use ReviewCaptain\Seo\SeoEnvironment;
use ReviewCaptain\Seo\SeoModule;
use ReviewCaptain\Seo\SeoStatus;
use ReviewCaptain\Tests\Unit\Frontend\FrontendTestCase;

final class SchemaSettingsTest extends FrontendTestCase {

	private SeoModule $seo;

	protected function setUp(): void {
		parent::setUp();
		\RC_Test_State::$queried_id = 10;
		$this->seo = new SeoModule();
		$this->seo->register( Plugin::instance() );
		$this->seo->set_environment( SeoEnvironment::from_state( array() ) );
	}

	public function test_context_can_be_switched_off(): void {
		$r   = $this->review( array( 'context_type' => 'product', 'context_id' => '10' ) );
		$ctx = array(
			'rendered'    => true,
			'environment' => SeoEnvironment::from_state( array() ),
		);
		$this->assertTrue( SchemaEligibility::is_eligible( $r, $ctx + array( 'contexts' => array( 'product', 'post' ) ) ) );
		$this->assertSame( 'context_disabled', SchemaEligibility::evaluate( $r, $ctx + array( 'contexts' => array( 'post' ) ) )['reason'] );
		$this->assertNotSame( '', SchemaEligibility::reason_label( 'context_disabled' ) );
		$this->assertSame( array( 'post' ), SchemaEligibility::enabled_contexts( array( 'post', 'organization', 'evil' ) ), 'can only narrow' );

		$this->shortcode( array( 'limit' => '10' ) );
		$this->assertNotSame( '', $this->seo->fallback_schema() );
		$this->settings( array( 'display' => array( 'schema_contexts' => array( 'post', 'event' ) ) ) );
		$this->assertSame( '', $this->seo->fallback_schema() );
	}

	public function test_minimum_number_of_reviews(): void {
		$this->review( array( 'context_type' => 'product', 'context_id' => '10' ) );
		$this->review( array( 'context_type' => 'product', 'context_id' => '10' ) );
		$this->shortcode( array( 'limit' => '10' ) );
		$this->settings( array( 'display' => array( 'schema_min_reviews' => 3 ) ) );
		$this->assertSame( array(), $this->seo->eligible_reviews() );
		$this->assertSame( 'below_minimum', $this->seo->last_reason() );
		$this->assertNotSame( '', SchemaEligibility::reason_label( 'below_minimum' ) );

		$this->settings( array( 'display' => array( 'schema_min_reviews' => 2 ) ) );
		$this->assertCount( 2, $this->seo->eligible_reviews() );
		$this->assertSame( 'eligible', $this->seo->last_reason() );

		add_filter( 'review_captain_schema_min_reviews', static fn() => 5 );
		$this->assertSame( array(), $this->seo->eligible_reviews(), 'filter wins' );
	}

	public function test_testimonials_never_get_markup(): void {
		$r = $this->review( array( 'context_type' => 'product', 'context_id' => '10', 'kind' => Kind::TESTIMONIAL ) );
		$this->assertSame( 'not_review', SchemaEligibility::evaluate( $r, array( 'rendered' => true ) )['reason'] );
	}

	public function test_diagnostics_explain_narrowed_output(): void {
		$this->settings( array( 'display' => array( 'schema_contexts' => array( 'product' ), 'schema_min_reviews' => 3 ) ) );
		$messages = implode( ' | ', array_column( SeoStatus::report( SeoEnvironment::from_state( array() ) ), 'message' ) );
		$this->assertStringContainsString( 'Markup nur für: Produkte', $messages );
		$this->assertStringContainsString( 'ab 3 geeigneten Bewertungen', $messages );
		$this->assertStringContainsString( 'Erfahrungsberichte der Website', $messages );

		$this->settings( array( 'display' => array( 'schema_contexts' => array() ) ) );
		$items = SeoStatus::report( SeoEnvironment::from_state( array() ) );
		$warn  = array_filter( $items, static fn( array $i ): bool => 'warn' === $i['status'] && str_contains( $i['message'], 'keinen Inhaltstyp' ) );
		$this->assertCount( 1, $warn );
	}
}
