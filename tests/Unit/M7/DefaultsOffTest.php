<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\M7;

use ReviewCaptain\Feedback\CampaignStore;
use ReviewCaptain\Moderation\ReportHandler;
use ReviewCaptain\Plugin;
use ReviewCaptain\Questions\QuestionHandler;
use ReviewCaptain\Settings\SettingsSchema;

/**
 * M7 features are off by default and do not change the 1.0 output.
 */
final class DefaultsOffTest extends M7TestCase {

	public function test_all_m7_switches_default_to_off_and_are_core_owned(): void {
		$fields = SettingsSchema::fields();
		foreach ( array( 'tags_enabled', 'reports_enabled', 'photos_enabled', 'multi_product_enabled', 'qa_enabled', 'feedback_campaigns_enabled' ) as $key ) {
			$this->assertFalse( $fields['submissions'][ $key ]['default'], $key );
			$this->assertSame( 'core', $fields['submissions'][ $key ]['owner'], $key );
		}
		$this->assertFalse( $fields['display']['show_photos']['default'] );
	}

	public function test_default_output_has_no_m7_elements(): void {
		$this->review();
		$html = $this->shortcode( array( 'v' => '1' ) );
		$this->assertStringNotContainsString( 'rc-report', $html );
		$this->assertStringNotContainsString( 'rc-card__photo', $html );
		$form = $this->module->shortcode_form( array( 'v' => '1', 'context_type' => 'product', 'context_id' => '5', 'context_ids' => '5,6' ) );
		$this->assertStringNotContainsString( 'type="file"', $form );
		$this->assertStringNotContainsString( 'rc_items', $form );
		$this->assertStringNotContainsString( 'multipart', $form );
		$this->assertSame( '', $this->module->shortcode_qa( array( 'v' => '1', 'context_type' => 'product', 'context_id' => '5' ) ) );
	}

	public function test_endpoints_refuse_while_disabled(): void {
		$this->assertSame( 'disabled', ( new ReportHandler( Plugin::instance() ) )->process( array() )['error'] );
		$this->assertSame( array( 'disabled' ), ( new QuestionHandler( Plugin::instance() ) )->process( array() )['errors'] );
		$res = $this->form()->process( $this->form_post( array( 'rc_context_ids' => '5,6', 'rc_context_ids_sig' => 'x' ), 'product', '' ) );
		$this->assertSame( array( 'context' ), $res['errors'] );
		$this->assertSame( array(), ( new CampaignStore() )->all() );
	}
}
