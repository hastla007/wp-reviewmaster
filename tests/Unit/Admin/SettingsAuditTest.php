<?php
/**
 * SET-RC-17: settings audit with old and new value, own retention and a settings filter in the view.
 */

declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Admin;

use ReviewCaptain\Admin\DiagnosticsPage;
use ReviewCaptain\Admin\SettingsPage;
use ReviewCaptain\Audit\AuditLog;
use ReviewCaptain\Audit\SettingsChanges;
use ReviewCaptain\Install\Schema;
use ReviewCaptain\Jobs\JobsModule;
use ReviewCaptain\Maintenance\Retention;
use ReviewCaptain\Plugin;
use ReviewCaptain\Security\Capabilities;
use ReviewCaptain\Settings\Settings;
use ReviewCaptain\Settings\SettingsSchema;
use ReviewCaptain\Tests\Support\ProRequestsFields;

final class SettingsAuditTest extends AdminTestCase {

	private function page(): SettingsPage {
		return new SettingsPage( $this->plugin() );
	}

	private function changes( string $event = 'settings.changed' ): array {
		$rows = $this->audit_rows( $event );
		$this->assertNotEmpty( $rows );
		$ctx = json_decode( (string) end( $rows )['context'], true );
		return array_column( (array) $ctx['changes'], null, 'field' );
	}

	public function test_value_masking_rules(): void {
		$this->assertSame( 'm***@example.test', SettingsChanges::value( 'sender_email', array( 'type' => 'email' ), 'max.muster@example.test' ) );
		$this->assertSame( SettingsChanges::HIDDEN, SettingsChanges::value( 'api_token', array( 'type' => 'string' ), 'abc123' ) );
		$this->assertSame( SettingsChanges::HIDDEN, SettingsChanges::value( 'password', array( 'type' => 'secret' ), 'x' ) );
		$this->assertSame( SettingsChanges::HIDDEN, SettingsChanges::value( 'owner', array( 'type' => 'string', 'privacy' => 'personal' ), 'Erika' ) );
		$this->assertSame( SettingsChanges::HIDDEN, SettingsChanges::value( 'sender_name', array( 'type' => 'string', 'privacy' => 'config' ), 'Erika Muster' ) );
		$this->assertSame( '', SettingsChanges::value( 'sender_name', array( 'type' => 'string', 'privacy' => 'config' ), '' ) );
		$this->assertSame( 'kurz', SettingsChanges::value( 'brand_name', array( 'type' => 'string' ), 'kurz' ) );
		$long = SettingsChanges::value( 'privacy_notice', array( 'type' => 'textarea' ), str_repeat( 'Datenschutz ', 20 ) );
		$this->assertSame( SettingsChanges::TEXT_MAX, mb_strlen( (string) $long ) );
		$this->assertStringEndsWith( '…', (string) $long );
		$this->assertSame( 'a***@b.test', SettingsChanges::value( 'empty_text', array( 'type' => 'string' ), 'anna@b.test' ), 'addresses in texts are masked too' );
		$this->assertSame( 'administrator, editor', SettingsChanges::value( 'moderate_roles', array( 'type' => 'array' ), array( 'administrator', 'editor' ) ) );
		$this->assertTrue( SettingsChanges::value( 'enabled', array( 'type' => 'bool' ), true ) );
		$this->assertSame( 30, SettingsChanges::value( 'retention_logs_days', array( 'type' => 'int' ), 30 ) );
	}

	public function test_recipient_lists_and_addresses_in_texts_are_masked(): void {
		// P4-2: submissions.notify_recipients (email_list) was logged in plain text.
		$this->login_with( Capabilities::MANAGE_SETTINGS );
		$this->page()->save_tab(
			'submissions',
			array(
				'submissions' => array(
					'notify_recipients' => "erika.muster@example.test\nshop@example.test",
					'notify_body'       => 'Fragen an max.mustermann@example.test',
				),
			)
		);
		$context = $this->audit_rows( 'settings.changed' )[0]['context'];
		$this->assertStringNotContainsString( 'erika.muster', $context );
		$this->assertStringNotContainsString( 'max.mustermann', $context );
		$changes = $this->changes();
		$this->assertSame( 'e***@example.test, s***@example.test', $changes['submissions.notify_recipients']['to'] );
		$this->assertSame( 'Fragen an m***@example.test', $changes['submissions.notify_body']['to'] );
	}

	public function test_save_logs_old_and_new_values_masked(): void {
		ProRequestsFields::register( true );
		add_filter(
			'review_captain_settings_schema',
			static function ( array $schema ): array {
				$schema['requests']['api_token'] = array( 'type' => 'string', 'default' => '', 'owner' => 'pro', 'privacy' => 'config', 'advanced' => false, 'scope' => 'site' );
				return $schema;
			}
		);
		update_option( Settings::OPTION, array( 'requests' => array( 'sender_email' => 'alt@example.test' ) ) );
		$this->login_with( Capabilities::MANAGE_SETTINGS );
		$this->page()->save_tab(
			'requests',
			array(
				'requests' => array(
					'sender_email' => 'neu.absender@example.test',
					'daily_limit'  => '150',
					'api_token'    => 'sk_live_supersecret',
				),
			)
		);
		$rows = $this->audit_rows( 'settings.changed' );
		$this->assertStringNotContainsString( 'supersecret', $rows[0]['context'] );
		$this->assertStringNotContainsString( 'neu.absender', $rows[0]['context'] );
		$changes = $this->changes();
		$this->assertSame( array( 'field' => 'requests.sender_email', 'from' => 'a***@example.test', 'to' => 'n***@example.test' ), $changes['requests.sender_email'] );
		$this->assertSame( 200, $changes['requests.daily_limit']['from'] );
		$this->assertSame( 150, $changes['requests.daily_limit']['to'] );
		$this->assertSame( SettingsChanges::HIDDEN, $changes['requests.api_token']['to'] );
	}

	public function test_long_text_is_shortened_in_the_log(): void {
		$this->login_with( Capabilities::MANAGE_SETTINGS, Capabilities::MANAGE_PRIVACY );
		$text = 'Wir speichern deine Bewertung, deinen Namen und deine E-Mail-Adresse, bis du sie löschen lässt.';
		$this->page()->save_tab( 'privacy', array( 'privacy' => array( 'privacy_notice' => $text ) ) );
		$changes = $this->changes();
		$this->assertSame( '', $changes['privacy.privacy_notice']['from'] );
		$this->assertSame( SettingsChanges::shorten( $text, SettingsChanges::TEXT_MAX ), $changes['privacy.privacy_notice']['to'] );
		$this->assertStringNotContainsString( 'bis du sie löschen', $this->audit_rows( 'settings.changed' )[0]['context'] );
	}

	public function test_address_lists_are_masked_in_the_log(): void {
		// SET-RC-P1: submissions.notify_recipients (email_list) was logged in plain text.
		$this->assertSame( 'a***@example.test, b***@example.test', SettingsChanges::value( 'notify_recipients', array( 'type' => 'email_list', 'privacy' => 'config' ), array( 'anna.mueller@example.test', 'bob@example.test' ) ) );
		$this->assertSame( SettingsChanges::HIDDEN, SettingsChanges::value( 'owners', array( 'type' => 'array', 'privacy' => 'personal' ), array( 'Erika' ) ) );
		$this->assertSame( '', SettingsChanges::value( 'owners', array( 'type' => 'array', 'privacy' => 'personal' ), array() ) );

		$this->login_with( Capabilities::MANAGE_SETTINGS );
		$this->page()->save_tab( 'submissions', array( 'submissions' => array( 'notify_recipients' => 'anna.mueller@example.test, bob@example.test' ) ) );
		$rows = $this->audit_rows( 'settings.changed' );
		$this->assertStringNotContainsString( 'anna.mueller', (string) end( $rows )['context'] );
		$this->assertSame( 'a***@example.test, b***@example.test', $this->changes()['submissions.notify_recipients']['to'] );
	}

	public function test_reset_and_import_log_changes(): void {
		update_option( Settings::OPTION, array( 'display' => array( 'show_date' => false ) ) );
		$this->login_with( Capabilities::MANAGE_SETTINGS );
		$this->page()->reset_tabs( array( 'display' ) );
		$changes = $this->changes( 'settings.reset' );
		$this->assertFalse( $changes['display.show_date']['from'] );
		$this->assertTrue( $changes['display.show_date']['to'] );

		$values                            = $this->plugin()->settings()->all();
		$values['general']['date_format'] = 'd.m.Y';
		$this->page()->apply_import( $values, array( 'general' ) );
		$changes = $this->changes( 'settings.imported' );
		$this->assertSame( 'd.m.Y', $changes['general.date_format']['to'] );
	}

	public function test_audit_context_drops_forbidden_nested_keys(): void {
		$ctx = AuditLog::sanitize_context(
			array(
				'changes' => array(
					array( 'field' => 'a.b', 'from' => 1, 'to' => 2, 'email' => 'x@y.test', 'nested' => array( 1 ) ),
					'plain',
				),
			)
		);
		$this->assertSame( array( array( 'field' => 'a.b', 'from' => 1, 'to' => 2 ), 'plain' ), $ctx['changes'] );
	}

	public function test_settings_entries_have_their_own_retention(): void {
		( new JobsModule() )->register( Plugin::instance() );
		$this->assertSame( 365, SettingsSchema::defaults()['privacy']['retention_audit_days'] );
		$audit = Schema::table( 'audit_log' );
		$old   = static fn( int $days ): string => gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$this->db->insert( $audit, array( 'event' => 'review.x', 'object_type' => 'review', 'context' => '{}', 'created_at' => $old( 100 ) ) );
		$this->db->insert( $audit, array( 'event' => 'settings.changed', 'object_type' => 'settings', 'context' => '{}', 'created_at' => $old( 100 ) ) );
		$this->db->insert( $audit, array( 'event' => 'roles.capabilities_changed', 'object_type' => 'capability', 'context' => '{}', 'created_at' => $old( 300 ) ) );
		$this->db->insert( $audit, array( 'event' => 'settings.changed', 'object_type' => 'settings', 'context' => '{}', 'created_at' => $old( 400 ) ) );

		/** @var Retention $retention */
		$retention = new Retention( Plugin::instance(), Plugin::instance()->get( 'jobs' ) );
		$res       = $retention->run();
		$this->assertSame( 2, $res['logs_deleted'] );
		$left = $this->db->get_col( "SELECT created_at FROM {$audit} WHERE object_type IN ('settings','capability') ORDER BY id" );
		$this->assertCount( 2, $left, '100 and 300 days old settings entries are kept (default 365)' );

		// The filter wins over the setting.
		add_filter( 'review_captain_retention_audit_days', static fn(): int => 200 );
		$res = $retention->run();
		$this->assertSame( 1, $res['logs_deleted'] );
	}

	public function test_diagnostics_view_filters_settings_with_old_and_new_values(): void {
		$this->login_with( Capabilities::MANAGE_SETTINGS, Capabilities::VIEW_REPORTS );
		$this->page()->save_tab( 'general', array( 'general' => array( 'brand_name' => 'Musterladen', 'show_pro_hints' => '1' ) ) );
		$this->plugin()->audit()->record( 'review.status_changed', 'review', 5, array( 'from' => 'pending', 'to' => 'approved' ) );

		$diag = new DiagnosticsPage( $this->plugin() );
		$rows = $diag->settings_audit();
		$this->assertCount( 1, $rows );
		$this->assertSame( array( 'Marken- bzw. Firmenname: – → [verborgen]' ), $rows[0]['changes'] );
		$this->assertCount( 2, $diag->audit( 20 ) );
		$this->assertCount( 1, $diag->audit( 20, true ) );

		$_GET = array(
			'page'  => DiagnosticsPage::SLUG,
			'audit' => 'settings',
		);
		ob_start();
		$diag->render();
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'Änderungen (alt → neu)', $html );
		$this->assertStringContainsString( 'Marken- bzw. Firmenname: – → [verborgen]', $html );
		$this->assertStringNotContainsString( 'review.status_changed', $html );
	}
}
