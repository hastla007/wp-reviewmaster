<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Seo;

use ReviewCaptain\Domain\Kind;
use ReviewCaptain\Domain\Platform;
use ReviewCaptain\Domain\Review;
use ReviewCaptain\Domain\ReviewStatus;
use ReviewCaptain\Seo\SchemaEligibility;
use ReviewCaptain\Seo\SeoEnvironment;
use ReviewCaptain\Seo\VisibleReviews;
use ReviewCaptain\Tests\TestCase;

final class SchemaEligibilityTest extends TestCase {

	private function eligible_review(): Review {
		$r               = new Review();
		$r->uuid         = 'u-' . wp_generate_password( 8, false );
		$r->platform     = Platform::LOCAL;
		$r->kind         = Kind::REVIEW;
		$r->status       = ReviewStatus::APPROVED;
		$r->context_type = 'product';
		$r->context_id   = '10';
		$r->rating_value = 5.0;
		$r->body         = 'Text';
		return $r;
	}

	/**
	 * @param array<string, mixed> $env Env state.
	 * @return array<string, mixed>
	 */
	private function ctx( array $env = array(), bool $rendered = true ): array {
		return array(
			'rendered'    => $rendered,
			'environment' => SeoEnvironment::from_state( $env ),
		);
	}

	private function reason( Review $r, array $ctx ): string {
		return SchemaEligibility::evaluate( $r, $ctx )['reason'];
	}

	public function test_all_conditions_met_is_eligible(): void {
		$this->assertSame( array( 'eligible' => true, 'reason' => 'eligible' ), SchemaEligibility::evaluate( $this->eligible_review(), $this->ctx() ) );
		foreach ( array( 'post', 'event' ) as $type ) {
			$r               = $this->eligible_review();
			$r->context_type = $type;
			$this->assertTrue( SchemaEligibility::is_eligible( $r, $this->ctx() ), $type );
		}
		// A6: no rating is still eligible.
		$r               = $this->eligible_review();
		$r->rating_value = null;
		$this->assertTrue( SchemaEligibility::is_eligible( $r, $this->ctx() ) );
		// Compatible Yoast.
		$this->assertTrue( SchemaEligibility::is_eligible( $this->eligible_review(), $this->ctx( array( 'yoast_version' => '26.1' ) ) ) );
	}

	public function test_e1_external_platforms(): void {
		foreach ( array( Platform::GOOGLE, Platform::TRUSTPILOT, Platform::TRUSTED_SHOPS, Platform::APP_STORE, Platform::GOOGLE_PLAY, Platform::OTHER ) as $p ) {
			$r           = $this->eligible_review();
			$r->platform = $p;
			$this->assertSame( 'external_platform', $this->reason( $r, $this->ctx() ), $p->value );
		}
	}

	public function test_e2_testimonial(): void {
		$r       = $this->eligible_review();
		$r->kind = Kind::TESTIMONIAL;
		$this->assertSame( 'not_review', $this->reason( $r, $this->ctx() ) );
	}

	public function test_e3_status(): void {
		foreach ( array( ReviewStatus::PENDING, ReviewStatus::FLAGGED, ReviewStatus::REMOVED, ReviewStatus::REJECTED, ReviewStatus::ARCHIVED ) as $s ) {
			$r         = $this->eligible_review();
			$r->status = $s;
			$this->assertSame( 'not_approved', $this->reason( $r, $this->ctx() ), $s->value );
		}
	}

	public function test_e4_context(): void {
		$r               = $this->eligible_review();
		$r->context_type = null;
		$this->assertSame( 'self_serving', $this->reason( $r, $this->ctx() ) );
		$r->context_type = 'organization';
		$this->assertSame( 'self_serving', $this->reason( $r, $this->ctx() ) );
		foreach ( array( 'voucher', 'app', 'form' ) as $type ) {
			$r->context_type = $type;
			$this->assertSame( 'context_not_supported', $this->reason( $r, $this->ctx() ), $type );
		}
	}

	public function test_e5_visibility(): void {
		$r = $this->eligible_review();
		$this->assertSame( 'not_rendered', $this->reason( $r, $this->ctx( array(), false ) ) );
		$this->assertSame( 'not_rendered', $this->reason( $r, array( 'environment' => SeoEnvironment::from_state( array() ) ) ) );
		$this->assertSame( 'not_rendered', $this->reason( $r, array( 'rendered' => true, 'preview' => true, 'environment' => SeoEnvironment::from_state( array() ) ) ) );

		$visible = new VisibleReviews();
		$ctx     = array(
			'visible'     => $visible,
			'environment' => SeoEnvironment::from_state( array() ),
		);
		$this->assertSame( 'not_rendered', $this->reason( $r, $ctx ) );
		$visible->add( $r );
		$this->assertSame( 'eligible', $this->reason( $r, $ctx ) );
	}

	public function test_e6_woocommerce_product_schema(): void {
		$r   = $this->eligible_review();
		$env = array(
			'woocommerce'     => true,
			'woo_product_ids' => array( '10' ),
		);
		$this->assertSame( 'woo_product_schema', $this->reason( $r, $this->ctx( $env ) ) );
		// Yoast WooCommerce SEO as owner as well.
		$this->assertSame( 'woo_product_schema', $this->reason( $r, $this->ctx( $env + array( 'yoast_version' => '26.0', 'yoast_woo' => true ) ) ) );
		// Non-Woo product (own product post) remains eligible.
		$r->context_id = '11';
		$this->assertSame( 'eligible', $this->reason( $r, $this->ctx( $env ) ) );
		// Post context on a Woo site is unaffected.
		$r->context_type = 'post';
		$r->context_id   = '10';
		$this->assertSame( 'eligible', $this->reason( $r, $this->ctx( $env ) ) );
	}

	public function test_e7_schema_owner(): void {
		$r = $this->eligible_review();
		foreach ( array( 'Rank Math', 'All in One SEO', 'SEOPress', 'Schema Pro' ) as $owner ) {
			$this->assertSame( 'foreign_schema_owner', $this->reason( $r, $this->ctx( array( 'foreign' => array( $owner ) ) ) ), $owner );
		}
		$this->assertSame( 'foreign_schema_owner', $this->reason( $r, $this->ctx( array( 'yoast_version' => '26.0', 'foreign' => array( 'Schema Pro' ) ) ) ) );
		$this->assertSame( 'yoast_incompatible', $this->reason( $r, $this->ctx( array( 'yoast_version' => '24.9' ) ) ) );
		$this->assertSame( 'yoast_incompatible', $this->reason( $r, $this->ctx( array( 'yoast_version' => 'trunk' ) ) ) );
	}

	public function test_e8_filter_only_tightens(): void {
		$r = $this->eligible_review();
		add_filter( SchemaEligibility::FILTER, static fn() => false );
		$this->assertSame( 'filtered', $this->reason( $r, $this->ctx() ) );
		remove_all_filters( SchemaEligibility::FILTER );

		add_filter( SchemaEligibility::FILTER, static fn() => 1 );
		$this->assertSame( 'filtered', $this->reason( $r, $this->ctx() ), 'non-strict true blocks' );
		remove_all_filters( SchemaEligibility::FILTER );

		// Filter cannot unlock blocked reviews and is not even called for them.
		$called = 0;
		add_filter(
			SchemaEligibility::FILTER,
			static function () use ( &$called ) {
				++$called;
				return true;
			}
		);
		$ext           = $this->eligible_review();
		$ext->platform = Platform::GOOGLE;
		$this->assertFalse( SchemaEligibility::is_eligible( $ext, $this->ctx() ) );
		$this->assertSame( 0, $called );
		$this->assertTrue( SchemaEligibility::is_eligible( $r, $this->ctx() ) );
		$this->assertSame( 1, $called );

		// Admin switch acts like E8.
		$this->assertSame( 'filtered', $this->reason( $r, $this->ctx() + array( 'enabled' => false ) ) );
	}

	public function test_first_failing_rule_wins(): void {
		$r           = $this->eligible_review();
		$r->platform = Platform::GOOGLE;
		$r->kind     = Kind::TESTIMONIAL;
		$r->status   = ReviewStatus::PENDING;
		$this->assertSame( 'external_platform', $this->reason( $r, $this->ctx( array( 'foreign' => array( 'Rank Math' ) ), false ) ) );
		$r->platform = Platform::LOCAL;
		$this->assertSame( 'not_review', $this->reason( $r, $this->ctx() ) );
	}

	public function test_every_reason_has_label(): void {
		foreach ( SchemaEligibility::REASONS as $reason ) {
			$this->assertNotSame( $reason, SchemaEligibility::reason_label( $reason ) );
		}
	}
}
