<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Frontend;

use ReviewCaptain\Domain\Kind;
use ReviewCaptain\Domain\Origin;
use ReviewCaptain\Domain\Platform;
use ReviewCaptain\Domain\Review;
use ReviewCaptain\Domain\ReviewStatus;
use ReviewCaptain\Domain\Verification;
use ReviewCaptain\Frontend\FrontendModule;
use ReviewCaptain\Frontend\Renderer;
use ReviewCaptain\Plugin;
use ReviewCaptain\Seo\VisibleReviews;
use ReviewCaptain\Tests\TestCase;

/**
 * Shared helpers for frontend/SEO tests.
 */
abstract class FrontendTestCase extends TestCase {

	protected FrontendModule $module;

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['rc_test_styles']  = array();
		$GLOBALS['rc_test_scripts'] = array();
		$GLOBALS['rc_test_blocks']  = array();
		$this->module               = new FrontendModule();
		$this->module->register( Plugin::instance() );
		$this->renderer()->set_preview( false );
	}

	protected function renderer(): Renderer {
		return $this->module->renderer();
	}

	protected function visible(): VisibleReviews {
		return VisibleReviews::from_plugin( Plugin::instance() );
	}

	/**
	 * @param array<string, mixed> $settings Settings by tab.
	 */
	protected function settings( array $settings ): void {
		update_option( 'review_captain_settings', $settings );
		Plugin::instance()->settings()->flush();
	}

	/**
	 * Insert a review.
	 *
	 * @param array<string, mixed> $o Overrides.
	 */
	protected function review( array $o = array() ): Review {
		$r               = new Review();
		$r->origin       = Origin::LOCAL_SUBMISSION;
		$r->platform     = Platform::LOCAL;
		$r->kind         = Kind::REVIEW;
		$r->status       = ReviewStatus::APPROVED;
		$r->body         = 'Sehr guter Service und freundliche Beratung.';
		$r->rating_value = 5.0;
		$r->author_name  = 'Anna';
		$r->published_at = '2026-10-01 10:00:00';
		foreach ( $o as $k => $v ) {
			$r->$k = $v;
		}
		if ( $r->platform->is_external() ) {
			$r->origin       = Origin::PROVIDER_SYNC;
			$r->verification = $o['verification'] ?? Verification::EXTERNAL_PLATFORM;
			$r->source_url   = $o['source_url'] ?? 'https://www.google.com/maps/review/abc';
			$r->external_id  = $o['external_id'] ?? 'ext-' . wp_generate_password( 8, false );
		}
		return Plugin::instance()->reviews()->insert( $r );
	}

	protected function shortcode( array $atts = array() ): string {
		return $this->module->shortcode_reviews( $atts );
	}
}
