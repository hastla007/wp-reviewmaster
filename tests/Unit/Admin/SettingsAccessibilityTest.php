<?php
/**
 * SET-RC-P5: help text, screen reader names and error focus on the settings page.
 */

declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Admin;

use ReviewCaptain\Admin\SettingsFields;
use ReviewCaptain\Admin\SettingsHints;
use ReviewCaptain\Admin\SettingsPage;
use ReviewCaptain\Security\Capabilities;
use ReviewCaptain\Settings\SettingsSchema;

final class SettingsAccessibilityTest extends AdminTestCase {

	private function render_tab( string $tab, array $extra = array() ): string {
		$_GET = array_merge( array( 'page' => SettingsPage::SLUG, 'tab' => $tab ), $extra );
		ob_start();
		( new SettingsPage( $this->plugin() ) )->render();
		return (string) ob_get_clean();
	}

	public function test_every_core_field_has_a_label_and_a_help_text(): void {
		foreach ( SettingsSchema::fields() as $tab => $fields ) {
			foreach ( $fields as $key => $field ) {
				if ( ! empty( $field['internal'] ) || 'core' !== ( $field['owner'] ?? 'core' ) ) {
					continue;
				}
				$this->assertNotSame( $key, SettingsFields::label( $tab, $key, $field ), "{$tab}.{$key}: label" );
				$this->assertNotSame( '', SettingsFields::description( $tab, $key, $field ), "{$tab}.{$key}: help text" );
			}
		}
	}

	public function test_checkbox_is_named_by_its_row_label(): void {
		$this->login_with( Capabilities::MANAGE_SETTINGS );
		$html = $this->render_tab( 'general' );
		// Row label points at the checkbox, so the accessible name is "Pro-Hinweise zeigen Aktiv", not only "Aktiv".
		$this->assertStringContainsString( '<th scope="row"><label for="rc-general-show_pro_hints">Show Pro hints</label></th>', $html );
	}

	public function test_choice_list_carries_id_help_text_and_error_state(): void {
		$this->login_with( Capabilities::MANAGE_SETTINGS );
		$html = $this->render_tab( 'display' );
		$this->assertMatchesRegularExpression( '/<fieldset id="rc-display-schema_contexts" tabindex="-1" aria-describedby="rc-display-schema_contexts-desc">/', $html );
		$this->assertStringContainsString( 'id="rc-display-schema_contexts-desc"', $html );
		// Choices carry readable names, not the stored keys.
		$this->assertMatchesRegularExpression( '/value="post"[^>]*\/> Posts<\/label>/', $html );
	}

	public function test_error_list_takes_focus(): void {
		$this->login_with( Capabilities::MANAGE_SETTINGS );
		set_transient(
			'review_captain_settings_errors_' . get_current_user_id(),
			array(
				'tab'    => 'submissions',
				'errors' => array( 'submissions-min_text_length' => 'Please enter a whole number.' ),
				'given'  => array( 'min_text_length' => 'x' ),
			),
			60
		);
		$html = $this->render_tab( 'submissions', array( 'rc_notice' => 'saved_invalid' ) );
		$this->assertStringContainsString( 'id="rc-settings-errors" role="alert" tabindex="-1" autofocus', $html );
	}

	public function test_hint_link_names_its_hint(): void {
		$html = SettingsHints::render(
			array(
				array( 'id' => 'a.b', 'tab' => 'general', 'title' => 'Analytics', 'text' => 'Ein Satz.', 'link' => 'https://shop.example.test/' ),
			)
		);
		$this->assertStringContainsString( 'aria-label="Learn more: Analytics">Learn more</a>', $html );
	}
}
