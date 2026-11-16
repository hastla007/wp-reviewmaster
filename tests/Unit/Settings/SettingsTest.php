<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Settings;

use ReviewCaptain\Settings\Settings;
use ReviewCaptain\Settings\SettingsSchema;
use ReviewCaptain\Tests\TestCase;

final class SettingsTest extends TestCase {

	public function test_safe_defaults(): void {
		$s = new Settings();
		$this->assertSame( 'pending', $s->get( 'general', 'new_review_status' ) );
		$this->assertFalse( $s->get( 'requests', 'reminders_enabled' ) );
		$this->assertTrue( $s->get( 'submissions', 'require_consent' ) );
		$this->assertFalse( $s->get( 'privacy', 'delete_data_on_uninstall' ) );
	}

	public function test_sanitize_clamps_and_rejects_invalid(): void {
		$s   = new Settings();
		$out = $s->sanitize(
			array(
				'submissions' => array( 'rate_limit_per_hour' => '99999', 'min_text_length' => 'abc', 'enabled' => '1' ),
				'general'     => array( 'new_review_status' => 'evil' ),
				'sources'     => array( 'sync_window_start' => '25:00' ),
			)
		);
		$this->assertSame( 1000, $out['submissions']['rate_limit_per_hour'] );
		$this->assertSame( 20, $out['submissions']['min_text_length'] );
		$this->assertTrue( $out['submissions']['enabled'] );
		$this->assertFalse( $out['submissions']['honeypot'], 'unchecked checkbox in submitted tab = false' );
		$this->assertSame( 'pending', $out['general']['new_review_status'] );
		$this->assertSame( '02:00', $out['sources']['sync_window_start'] );
	}

	public function test_pro_fields_untouched_without_pro(): void {
		update_option( Settings::OPTION, array( 'requests' => array( 'daily_limit' => 50 ) ) );
		$s   = new Settings();
		$out = $s->sanitize( array( 'requests' => array( 'daily_limit' => 999 ) ) );
		$this->assertSame( 50, $out['requests']['daily_limit'] );
	}

	public function test_reset_keeps_pro_fields(): void {
		update_option( Settings::OPTION, array( 'requests' => array( 'daily_limit' => 50 ), 'display' => array( 'show_date' => false ) ) );
		$s = new Settings();
		$s->reset();
		$s->flush();
		$this->assertTrue( $s->get( 'display', 'show_date' ) );
		$this->assertSame( 50, $s->get( 'requests', 'daily_limit' ) );
	}

	public function test_effective_override_and_fallback(): void {
		$s = new Settings();
		$this->assertSame( array( 'value' => false, 'source' => 'override' ), $s->effective( 'display', 'show_date', array( 'show_date' => false ) ) );
		$this->assertSame( 'global', $s->effective( 'display', 'date_style', array( 'date_style' => 'nonsense' ) )['source'] );
	}

	public function test_filter_cannot_remove_core_fields(): void {
		add_filter( 'review_captain_settings_schema', fn( $s ) => array( 'pro_tab' => array( 'x' => array( 'type' => 'bool', 'default' => true, 'owner' => 'pro' ) ) ) );
		$fields = SettingsSchema::fields();
		$this->assertArrayHasKey( 'general', $fields );
		$this->assertArrayHasKey( 'pro_tab', $fields );
	}
}
