<?php
/**
 * Settings migrations (E5 contact retention default, P2-13 notification migration).
 */

declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Settings;

use ReviewCaptain\Admin\ConfigTransfer;
use ReviewCaptain\Install\Schema;
use ReviewCaptain\Settings\Settings;
use ReviewCaptain\Settings\SettingsMigrator;
use ReviewCaptain\Settings\SettingsSchema;
use ReviewCaptain\Tests\TestCase;

final class SettingsMigratorTest extends TestCase {

	private function audit_count( string $event ): int {
		return (int) $this->db->get_var( $this->db->prepare( 'SELECT COUNT(*) FROM ' . Schema::table( 'audit_log' ) . ' WHERE event = %s', $event ) );
	}

	public function test_fresh_installation_gets_the_new_defaults(): void {
		$s = new Settings();
		$this->assertSame( 365, $s->get( 'privacy', 'retention_contact_days' ), 'E5: 12 months for new installations' );
		$this->assertSame( 'recipients', $s->get( 'submissions', 'report_notify' ) );
		$this->assertTrue( $s->get( 'submissions', 'notify_questions' ) );
		$this->assertSame( SettingsSchema::VERSION, get_option( Settings::OPTION_VERSION ) );
		$this->assertFalse( get_option( Settings::OPTION ), 'nothing stored: the schema defaults apply' );
		$this->assertSame( 0, $this->audit_count( 'settings.migrated' ) );
	}

	public function test_existing_installation_without_saved_settings_keeps_unlimited(): void {
		update_option( 'review_captain_db_version', 5 );
		$s = new Settings();
		$this->assertSame( 0, $s->get( 'privacy', 'retention_contact_days' ) );
		$this->assertSame( 0, get_option( Settings::OPTION )['privacy']['retention_contact_days'], 'stored explicitly' );
		$this->assertSame( SettingsSchema::VERSION, get_option( Settings::OPTION_VERSION ) );
		$this->assertSame( 1, $this->audit_count( 'settings.migrated' ) );

		// Runs once: a later reset to the defaults is the operator's choice and gives 365.
		$s->reset( array( 'privacy' ) );
		$fresh = new Settings();
		$this->assertSame( 365, $fresh->get( 'privacy', 'retention_contact_days' ) );
		$this->assertSame( 1, $this->audit_count( 'settings.migrated' ) );
	}

	public function test_existing_installation_keeps_a_stored_period(): void {
		update_option( Settings::OPTION_VERSION, 1 );
		update_option( Settings::OPTION, array( 'privacy' => array( 'retention_contact_days' => 30 ) ) );
		$this->assertSame( 30, ( new Settings() )->get( 'privacy', 'retention_contact_days' ) );
	}

	public function test_notifications_off_stay_off_after_the_update(): void {
		// P2-13: review mails were off → report and question mails off as well.
		update_option( Settings::OPTION_VERSION, 1 );
		update_option( Settings::OPTION, array( 'submissions' => array( 'notify_admin' => false, 'reports_enabled' => true, 'qa_enabled' => true ) ) );
		$s = new Settings();
		$this->assertSame( 'none', $s->get( 'submissions', 'report_notify' ) );
		$this->assertFalse( $s->get( 'submissions', 'notify_questions' ) );
		$this->assertFalse( $s->get( 'submissions', 'notify_admin' ) );
		$this->assertTrue( $s->get( 'submissions', 'reports_enabled' ) );
		$this->assertSame( 0, $s->get( 'privacy', 'retention_contact_days' ) );

		// The operator can switch them on again; the migration does not run a second time.
		$values                                    = $s->all();
		$values['submissions']['notify_questions'] = true;
		$s->save( $values );
		$this->assertTrue( ( new Settings() )->get( 'submissions', 'notify_questions' ) );
	}

	public function test_notifications_on_keep_the_new_defaults(): void {
		update_option( Settings::OPTION_VERSION, 1 );
		update_option( Settings::OPTION, array( 'submissions' => array( 'notify_admin' => true ) ) );
		$s = new Settings();
		$this->assertSame( 'recipients', $s->get( 'submissions', 'report_notify' ) );
		$this->assertTrue( $s->get( 'submissions', 'notify_questions' ) );
	}

	public function test_current_version_is_not_migrated_again(): void {
		update_option( Settings::OPTION_VERSION, SettingsSchema::VERSION );
		update_option( Settings::OPTION, array( 'submissions' => array( 'notify_admin' => false ) ) );
		$s = new Settings();
		$this->assertSame( 'recipients', $s->get( 'submissions', 'report_notify' ) );
		$this->assertSame( 365, $s->get( 'privacy', 'retention_contact_days' ) );
		$this->assertNull( SettingsMigrator::maybe_run() );
	}

	public function test_migrate_is_pure_and_versioned(): void {
		$v1 = array( 'submissions' => array( 'notify_admin' => false ) );
		$this->assertSame(
			array(
				'submissions' => array( 'notify_admin' => false, 'report_notify' => 'none', 'notify_questions' => false ),
				'privacy'     => array( 'retention_contact_days' => 0 ),
			),
			SettingsMigrator::migrate( $v1, 1 )
		);
		$this->assertSame( $v1, SettingsMigrator::migrate( $v1, 2 ) );
	}

	public function test_rollback_of_an_old_backup_keeps_the_old_behaviour(): void {
		update_option( Settings::OPTION_VERSION, SettingsSchema::VERSION );
		update_option(
			ConfigTransfer::BACKUP_OPTION,
			array(
				'created_at' => '2026-11-01 10:00:00',
				'version'    => 1,
				'settings'   => array( 'display' => array( 'show_avatar' => true ) ),
			)
		);
		$s = new Settings();
		( new ConfigTransfer( $s ) )->rollback();
		$this->assertSame( 0, ( new Settings() )->get( 'privacy', 'retention_contact_days' ) );
		$this->assertTrue( ( new Settings() )->get( 'display', 'show_avatar' ) );
	}
}
