<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Settings;

use ReviewCaptain\Settings\Settings;
use ReviewCaptain\Settings\SettingsSchema;
use ReviewCaptain\Tests\Support\ProRequestsFields;
use ReviewCaptain\Tests\TestCase;

final class SettingsTest extends TestCase {

	public function test_safe_defaults(): void {
		$s = new Settings();
		$this->assertSame( 'pending', $s->get( 'general', 'new_review_status' ) );
		$this->assertNull( $s->get( 'requests', 'reminders_enabled' ), 'the core has no default for add-on fields' );
		$this->assertTrue( $s->get( 'general', 'show_pro_hints' ) );
		$this->assertTrue( $s->get( 'submissions', 'require_consent' ) );
		$this->assertFalse( $s->get( 'privacy', 'delete_data_on_uninstall' ) );

		ProRequestsFields::register( false );
		$s = new Settings();
		$this->assertFalse( $s->get( 'requests', 'reminders_enabled' ), 'default of the add-on field' );
		$this->assertSame( 200, $s->get( 'requests', 'daily_limit' ) );
	}

	public function test_multisite_switch_reads_the_settings_of_the_current_site(): void {
		// SET-RC-P1: the cached values belong to one site; after switch_to_blog() the other site's apply.
		update_option( Settings::OPTION, array( 'general' => array( 'brand_name' => 'Site 1' ) ) );
		$s = new Settings();
		$this->assertSame( 'Site 1', $s->get( 'general', 'brand_name' ) );
		switch_to_blog( 2 );
		$this->assertSame( '', $s->get( 'general', 'brand_name' ), 'site 2 has its own (default) value' );
		update_option( Settings::OPTION, array( 'general' => array( 'brand_name' => 'Site 2' ) ) );
		$s->flush();
		$this->assertSame( 'Site 2', $s->get( 'general', 'brand_name' ) );
		restore_current_blog();
		$this->assertSame( 'Site 1', $s->get( 'general', 'brand_name' ) );
		$s->save( array( 'general' => array( 'brand_name' => 'Neu 1' ) ) );
		switch_to_blog( 2 );
		$this->assertSame( 'Site 2', $s->get( 'general', 'brand_name' ), 'saving site 1 never touches site 2' );
		restore_current_blog();
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

		// Registered by the add-on: still untouched unless the caller allows add-on fields.
		ProRequestsFields::register( true );
		$out = $s->sanitize( array( 'requests' => array( 'daily_limit' => 999 ) ) );
		$this->assertSame( 50, $out['requests']['daily_limit'] );
		$out = $s->sanitize( array( 'requests' => array( 'daily_limit' => 999 ) ), null, true );
		$this->assertSame( 999, $out['requests']['daily_limit'] );
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
