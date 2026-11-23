<?php
/**
 * SET-RC-19 (validation with feedback) and SET-RC-20 (confirmation word for data deletion).
 */

declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Admin;

use ReviewCaptain\Admin\ConfigTransfer;
use ReviewCaptain\Admin\SettingsPage;
use ReviewCaptain\Security\Capabilities;
use ReviewCaptain\Settings\Settings;
use ReviewCaptain\Settings\SettingsSchema;
use ReviewCaptain\Tests\Support\ProRequestsFields;

final class SettingsValidationTest extends AdminTestCase {

	private function page(): SettingsPage {
		return new SettingsPage( $this->plugin() );
	}

	public function test_validate_value_reports_instead_of_correcting(): void {
		$fields = SettingsSchema::fields();
		$this->assertSame( array( 30, null ), SettingsSchema::validate_value( $fields['submissions']['min_text_length'], ' 30 ' ) );
		$this->assertSame( 'Bitte gib eine ganze Zahl ein.', SettingsSchema::validate_value( $fields['submissions']['min_text_length'], '3.5' )[1] );
		$this->assertSame( 'Bitte gib eine ganze Zahl ein.', SettingsSchema::validate_value( $fields['submissions']['min_text_length'], 'abc' )[1] );
		$this->assertSame( 'Erlaubt sind Werte von 0 bis 1.000.', SettingsSchema::validate_value( $fields['submissions']['min_text_length'], '-1' )[1] );
		$this->assertNotNull( SettingsSchema::validate_value( $fields['general']['new_review_status'], 'published' )[1] );
		$this->assertSame( array( 'approved', null ), SettingsSchema::validate_value( $fields['general']['new_review_status'], 'approved' ) );
		$this->assertStringContainsString( 'HH:MM', (string) SettingsSchema::validate_value( $fields['sources']['sync_window_start'], '25:00' )[1] );
		$email = array( 'type' => 'email', 'default' => '' );
		$this->assertNotNull( SettingsSchema::validate_value( $email, 'kein-mail' )[1] );
		$this->assertSame( array( '', null ), SettingsSchema::validate_value( $email, '  ' ) );
		$this->assertSame( array( true, null ), SettingsSchema::validate_value( $fields['display']['show_date'], '1' ) );
		$this->assertNotNull( SettingsSchema::validate_value( $fields['general']['brand_name'], array( 'x' ) )[1] );
		// Import and programmatic paths keep correcting (sanitize_value is unchanged).
		$this->assertSame( 1000, SettingsSchema::sanitize_value( $fields['submissions']['min_text_length'], '99999' ) );
	}

	public function test_too_long_text_is_reported_not_cut(): void {
		// SET-RC-P1: texts over max_length were cut silently (e.g. the consent text mid-sentence).
		$fields = SettingsSchema::fields();
		$this->assertSame( array( str_repeat( 'a', 60 ), null ), SettingsSchema::validate_value( $fields['submissions']['submit_label'], str_repeat( 'a', 60 ) ) );
		$this->assertSame( 'Bitte kürze den Text auf höchstens 60 Zeichen (derzeit 61).', SettingsSchema::validate_value( $fields['submissions']['submit_label'], str_repeat( 'ä', 61 ) )[1] );
		$this->assertNull( SettingsSchema::validate_value( $fields['general']['brand_name'], str_repeat( 'a', 500 ) )[1], 'fields without max_length stay unlimited' );

		$this->login_with( Capabilities::MANAGE_SETTINGS );
		$consent = str_repeat( 'Ich willige ein. ', 120 ); // 2040 characters, limit 2000.
		$result  = $this->page()->save_tab(
			'submissions',
			array(
				'submissions' => array(
					'enabled'      => '1',
					'consent_text' => $consent,
					'submit_label' => 'Senden',
				),
			)
		);
		$this->assertSame( array( 'submissions-consent_text' ), $result['invalid'] );
		$this->assertSame( $consent, $result['given']['consent_text'], 'the whole input is shown again for editing' );
		$s = new Settings();
		$this->assertSame( '', $s->get( 'submissions', 'consent_text' ), 'the stored value stays' );
		$this->assertSame( 'Senden', $s->get( 'submissions', 'submit_label' ) );
		$this->assertSame( 60, mb_strlen( SettingsSchema::sanitize_value( $fields['submissions']['submit_label'], str_repeat( 'a', 70 ) ) ), 'import keeps shortening' );
	}

	public function test_cross_checks(): void {
		$this->assertSame( array( 'min_text_length', 'max_text_length' ), SettingsSchema::cross_check( 'submissions', array( 'min_text_length' => 500, 'max_text_length' => 500 ) )[0]['fields'] );
		$this->assertSame( array(), SettingsSchema::cross_check( 'submissions', array( 'min_text_length' => 20, 'max_text_length' => 500 ) ) );
		$this->assertCount( 1, SettingsSchema::cross_check( 'requests', array( 'send_window_start' => '09:00', 'send_window_end' => '09:00' ) ) );
		$this->assertCount( 1, SettingsSchema::cross_check( 'sources', array( 'sync_window_start' => '02:00', 'sync_window_end' => '02:00' ) ) );
		$this->assertCount( 0, SettingsSchema::cross_check( 'sources', array( 'sync_window_start' => '23:00', 'sync_window_end' => '02:00' ) ), 'over midnight is fine' );
		$this->assertCount( 1, SettingsSchema::cross_check( 'advanced', array( 'lease_seconds' => 60, 'job_time_budget' => 60 ) ) );
		$this->assertCount( 0, SettingsSchema::cross_check( 'advanced', array( 'lease_seconds' => 61, 'job_time_budget' => 60 ) ) );
	}

	public function test_cross_check_keeps_both_stored_values_and_saves_the_rest(): void {
		$this->login_with( Capabilities::MANAGE_SETTINGS );
		$result = $this->page()->save_tab(
			'submissions',
			array(
				'submissions' => array(
					'enabled'         => '1',
					'min_text_length' => '800',
					'max_text_length' => '100',
					'photo_max_kb'    => '4096',
				),
			)
		);
		$this->assertSame( array( 'submissions-min_text_length', 'submissions-max_text_length' ), $result['invalid'] );
		$this->assertSame( 'Die Mindestlänge muss kleiner als die Höchstlänge sein.', $result['errors']['submissions-min_text_length'] );
		$s = new Settings();
		$this->assertSame( 20, $s->get( 'submissions', 'min_text_length' ) );
		$this->assertSame( 5000, $s->get( 'submissions', 'max_text_length' ) );
		$this->assertSame( 4096, $s->get( 'submissions', 'photo_max_kb' ), 'valid fields are saved' );
		$this->assertContains( 'submissions.photo_max_kb', $result['changed'] );
		$this->assertNotContains( 'submissions.min_text_length', $result['changed'] );
		$this->assertNotContains( 'submissions.max_text_length', $result['changed'] );
	}

	public function test_lease_must_exceed_budget_and_pro_window_is_checked_only_when_writable(): void {
		$this->login_with( Capabilities::MANAGE_SETTINGS );
		$result = $this->page()->save_tab( 'advanced', array( 'advanced' => array( 'job_time_budget' => '90', 'lease_seconds' => '80', 'use_action_scheduler' => '1' ) ) );
		$this->assertArrayHasKey( 'advanced-lease_seconds', $result['errors'] );
		$this->assertSame( 300, ( new Settings() )->get( 'advanced', 'lease_seconds' ) );
		$this->assertSame( 20, ( new Settings() )->get( 'advanced', 'job_time_budget' ) );

		// Without the add-on the requests tab has no fields: nothing is checked or reported.
		update_option( Settings::OPTION, array( 'requests' => array( 'send_window_start' => '10:00', 'send_window_end' => '10:00' ) ) );
		$result = $this->page()->save_tab( 'requests', array( 'requests' => array( 'send_window_start' => '10:00' ) ) );
		$this->assertSame( array(), $result['errors'] );

		// Fields registered by the add-on but not writable: no error on fields the user cannot change.
		ProRequestsFields::register( false );
		$result = $this->page()->save_tab( 'requests', array( 'requests' => array( 'send_window_start' => '10:00' ) ) );
		$this->assertSame( array(), $result['errors'] );

		// The core cross-check of the send window works for the add-on's fields.
		add_filter( 'review_captain_pro_settings_writable', '__return_true' );
		$result = $this->page()->save_tab( 'requests', array( 'requests' => array( 'send_window_start' => '12:00', 'send_window_end' => '12:00' ) ) );
		$this->assertArrayHasKey( 'requests-send_window_end', $result['errors'] );
		$result = $this->page()->save_tab( 'requests', array( 'requests' => array( 'send_window_start' => '08:00', 'send_window_end' => '17:00' ) ) );
		$this->assertSame( array(), $result['errors'] );
		$this->assertSame( '17:00', ( new Settings() )->get( 'requests', 'send_window_end' ) );
	}

	public function test_error_list_with_jump_links_and_aria_invalid(): void {
		$this->login_with( Capabilities::MANAGE_SETTINGS );
		$this->post(
			'save_settings',
			array(
				'tab'               => 'submissions',
				SettingsPage::INPUT => array(
					'submissions' => array(
						'enabled'             => '1',
						'rate_limit_per_hour' => '99999',
						'min_text_length'     => 'zwanzig',
					),
				),
			)
		);
		$msg = $this->run_handler( array( $this->page(), 'handle_save' ) );
		$this->assertStringContainsString( 'rc_notice=saved_invalid', $msg );

		$_GET = array(
			'page'      => SettingsPage::SLUG,
			'tab'       => 'submissions',
			'rc_notice' => 'saved_invalid',
			'rc_fields' => 'submissions-rate_limit_per_hour,submissions-min_text_length',
		);
		ob_start();
		$this->page()->render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'id="rc-settings-errors" role="alert"', $html );
		$this->assertStringContainsString( '<a href="#rc-submissions-min_text_length">Mindestlänge des Textes</a>: Bitte gib eine ganze Zahl ein.', $html );
		$this->assertStringContainsString( '<a href="#rc-submissions-rate_limit_per_hour">', $html );
		$this->assertMatchesRegularExpression( '/id="rc-submissions-min_text_length"[^>]*value="zwanzig"[^>]*aria-describedby="[^"]*rc-submissions-min_text_length-error" aria-invalid="true"/', $html );
		$this->assertStringContainsString( 'id="rc-submissions-min_text_length-error"', $html );
		// The advanced section opens when one of its fields has an error.
		$this->assertStringContainsString( '<details class="rc-admin-advanced" open>', $html );

		// Shown once.
		ob_start();
		$this->page()->render();
		$this->assertStringNotContainsString( 'aria-invalid="true"', (string) ob_get_clean() );
	}

	public function test_confirmation_word_is_required_to_enable_data_deletion(): void {
		$this->login_with( Capabilities::MANAGE_SETTINGS, Capabilities::MANAGE_PRIVACY );

		$result = $this->page()->save_tab( 'privacy', array( 'privacy' => array( 'delete_data_on_uninstall' => '1', 'uninstall_confirm_word' => 'ja' ) ) );
		$this->assertArrayHasKey( 'privacy-delete_data_on_uninstall', $result['errors'] );
		$this->assertFalse( ( new Settings() )->get( 'privacy', 'delete_data_on_uninstall' ) );
		$this->assertSame( '', ( new Settings() )->get( 'privacy', 'delete_data_confirmed_at' ) );

		$result = $this->page()->save_tab( 'privacy', array( 'privacy' => array( 'delete_data_on_uninstall' => '1', 'uninstall_confirm_word' => ' löschen ' ) ) );
		$this->assertSame( array(), $result['errors'] );
		$s = new Settings();
		$this->assertTrue( $s->get( 'privacy', 'delete_data_on_uninstall' ) );
		$at = $s->get( 'privacy', 'delete_data_confirmed_at' );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', $at );
		$this->assertTrue( \ReviewCaptain\Privacy\Uninstaller::should_delete() );

		// Later saves keep the confirmation without a new word; the input cannot forge the time.
		$this->page()->save_tab( 'privacy', array( 'privacy' => array( 'delete_data_on_uninstall' => '1', 'delete_data_confirmed_at' => '1999-01-01 00:00:00', 'retention_logs_days' => '30' ) ) );
		$this->assertSame( $at, ( new Settings() )->get( 'privacy', 'delete_data_confirmed_at' ) );

		// Turning it off clears the confirmation.
		$this->page()->save_tab( 'privacy', array( 'privacy' => array( 'retention_logs_days' => '30' ) ) );
		$this->assertFalse( ( new Settings() )->get( 'privacy', 'delete_data_on_uninstall' ) );
		$this->assertSame( '', ( new Settings() )->get( 'privacy', 'delete_data_confirmed_at' ) );
		$this->assertFalse( \ReviewCaptain\Privacy\Uninstaller::should_delete() );
	}

	public function test_import_cannot_enable_data_deletion_and_field_is_not_exported(): void {
		$this->login_with( Capabilities::MANAGE_SETTINGS, Capabilities::MANAGE_PRIVACY );
		$transfer = new ConfigTransfer( $this->plugin()->settings() );
		$doc      = $transfer->export();
		$this->assertArrayNotHasKey( 'delete_data_confirmed_at', $doc['settings']['privacy'] );
		$doc['settings']['privacy']['delete_data_on_uninstall'] = true;
		$doc['settings']['privacy']['delete_data_confirmed_at'] = '2026-01-01 00:00:00';
		$preview = $transfer->preview( (string) wp_json_encode( $doc ) );
		$this->assertSame( array(), $preview['diff'] );
		$this->assertFalse( $preview['values']['privacy']['delete_data_on_uninstall'] );
	}

	public function test_privacy_tab_renders_confirmation_field_and_confirmed_time(): void {
		$this->login_with( Capabilities::MANAGE_SETTINGS, Capabilities::MANAGE_PRIVACY );
		$_GET = array(
			'page' => SettingsPage::SLUG,
			'tab'  => 'privacy',
		);
		ob_start();
		$this->page()->render();
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'name="review_captain_settings[privacy][uninstall_confirm_word]"', $html );
		$this->assertStringNotContainsString( 'name="review_captain_settings[privacy][delete_data_confirmed_at]"', $html, 'internal field is not rendered' );
		$this->assertStringContainsString( 'name="review_captain_settings[privacy][retention_audit_days]"', $html );

		update_option( Settings::OPTION, array( 'privacy' => array( 'delete_data_on_uninstall' => true, 'delete_data_confirmed_at' => '2026-11-23 09:15:00' ) ) );
		$this->plugin()->settings()->flush();
		ob_start();
		$this->page()->render();
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'Bestätigt am', $html );
		$this->assertStringNotContainsString( 'uninstall_confirm_word', $html );

		// Legacy: on but never confirmed.
		update_option( Settings::OPTION, array( 'privacy' => array( 'delete_data_on_uninstall' => true ) ) );
		$this->plugin()->settings()->flush();
		ob_start();
		$this->page()->render();
		$this->assertStringContainsString( 'noch nicht bestätigt', (string) ob_get_clean() );
	}
}
