<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\M7;

use ReviewCaptain\Widgets\WidgetDefinition;

/**
 * QA F-06 (focus on the error box after a failed submission) and F-07 (configurable card
 * heading level, default h3).
 */
final class A11yTemplateTest extends M7TestCase {

	public function test_heading_level_is_configurable_and_validated(): void {
		$this->review( array( 'title' => 'Super' ) );
		$this->assertStringContainsString( '<h3 class="rc-card__title">Super</h3>', $this->shortcode( array( 'v' => '1' ) ) );
		$this->assertStringContainsString( '<h2 class="rc-card__title">Super</h2>', $this->shortcode( array( 'v' => '1', 'heading_level' => '2' ) ) );
		$d = WidgetDefinition::from_atts( array( 'heading_level' => '9' ) );
		$this->assertSame( 3, $d->heading_level );
		$this->assertContains( 'heading_level', $d->notices );
		$this->assertSame( 4, WidgetDefinition::from_block_attributes( array( 'headingLevel' => 4 ) )->heading_level );
	}

	public function test_failed_submission_redirects_to_and_focuses_the_error_box(): void {
		$res = $this->form()->process( $this->form_post( array( 'rc_rating' => '' ) ) );
		$this->assertStringEndsWith( '#rc-form-1-errors', $res['redirect'] );
		$_GET = array(
			'rc_form'  => '1',
			'rc_error' => 'rating',
		);
		$html = $this->module->shortcode_form( array( 'v' => '1' ) );
		$this->assertMatchesRegularExpression( '/<div class="rc-form__error rc-form__errors" id="rc-form-1-errors" role="alert" tabindex="-1" autofocus>/', $html );
	}
}
