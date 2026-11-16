<?php
/**
 * Schema, settings, capabilities, crypto, audit (RC-035, RC-037, SET-003/004/005, TEC-008).
 *
 * @package ReviewCaptain
 */

use ReviewCaptain\Admin\SettingsPage;
use ReviewCaptain\Database\Schema;
use ReviewCaptain\Install\Activator;
use ReviewCaptain\Security\Capabilities;
use ReviewCaptain\Security\Crypto;
use ReviewCaptain\Security\CryptoException;
use ReviewCaptain\Security\EmbedPolicy;
use ReviewCaptain\Settings\Settings;

class CoreTest extends WP_UnitTestCase {

	public function test_schema_install_is_idempotent_and_versioned(): void {
		global $wpdb;
		Schema::install();
		Schema::install();
		$this->assertSame( REVIEW_CAPTAIN_DB_VERSION, Schema::installed_version() );
		foreach ( Schema::TABLES as $table ) {
			$this->assertSame( Schema::table( $table ), $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', Schema::table( $table ) ) ) );
		}
		$this->assertFalse( Schema::needs_upgrade() );
	}

	public function test_unique_external_key_prevents_duplicates(): void {
		global $wpdb;
		$key = hash( 'sha256', 'google||abc' );
		$a   = \ReviewCaptain\Reviews\ReviewRepository::insert( array( 'provider' => 'google', 'origin' => 'sync', 'external_key' => $key, 'status' => 'approved' ) );
		$b   = \ReviewCaptain\Reviews\ReviewRepository::insert( array( 'provider' => 'google', 'origin' => 'sync', 'external_key' => $key, 'status' => 'approved' ) );
		$this->assertIsInt( $a );
		$this->assertWPError( $b );
		$this->assertSame( 'duplicate', $b->get_error_code() );
	}

	public function test_settings_defaults_and_safe_values(): void {
		Settings::reset_all();
		$this->assertSame( 'pending', Settings::get( 'default_status' ) );
		$this->assertFalse( Settings::get( 'existing_customer_basis' ) );
		$this->assertFalse( Settings::get( 'reminders_default' ) );
		$this->assertFalse( Settings::get( 'delete_data_on_uninstall' ) );
		$this->assertSame( 'click_to_load', Settings::get( 'external_services_consent_mode' ) );
		$this->assertSame( '', Settings::get( 'sender_email' ) );
		$this->assertSame( get_bloginfo( 'name' ), Settings::get( 'brand_name' ) );
	}

	public function test_settings_sanitize_rejects_invalid_values(): void {
		$result = Settings::sanitize_group( 'sync', array( 'sync_interval_hours' => 48, 'sync_lookback_days' => 30 ) );
		$this->assertArrayHasKey( 'sync_interval_hours', $result['errors'] );
		$this->assertSame( 12, $result['values']['sync_interval_hours'] );
		$this->assertSame( 30, $result['values']['sync_lookback_days'] );

		$result = Settings::sanitize_group( 'requests', array( 'sender_email' => 'no-mail', 'send_window_start' => '20:00', 'send_window_end' => '08:00' ) );
		$this->assertArrayHasKey( 'sender_email', $result['errors'] );
		$this->assertArrayHasKey( 'send_window_end', $result['errors'] );
	}

	public function test_locked_attribution_cannot_be_disabled(): void {
		update_option( 'review_captain_display', array( 'source_attribution' => false ) );
		$this->assertTrue( Settings::get( 'source_attribution' ) );
		$result = Settings::sanitize_group( 'display', array( 'source_attribution' => '' ) );
		$this->assertTrue( $result['values']['source_attribution'] );
	}

	public function test_overrides_only_in_allowed_direction(): void {
		Settings::reset_all();
		$this->assertSame( array( 'value' => 6, 'source' => 'profile' ), Settings::effective( 'sync_interval_hours', array( 'profile' => array( 'sync_interval_hours' => 6 ) ) ) );
		$this->assertSame( 'global', Settings::effective( 'sync_interval_hours', array( 'profile' => array( 'sync_interval_hours' => 20 ) ) )['source'] );
		$this->assertSame( 'global', Settings::effective( 'cooldown_days', array( 'campaign' => array( 'cooldown_days' => 5 ) ) )['source'] );
		$this->assertSame( 60, Settings::effective( 'cooldown_days', array( 'campaign' => array( 'cooldown_days' => 60 ) ) )['value'] );
		update_option( 'review_captain_requests', array_merge( Settings::group( 'requests' ), array( 'existing_customer_basis' => true ) ) );
		$this->assertTrue( Settings::effective( 'existing_customer_basis', array( 'campaign' => array( 'existing_customer_basis' => true ) ) )['value'] );
		$this->assertFalse( Settings::effective( 'existing_customer_basis', array( 'campaign' => array( 'existing_customer_basis' => false ) ) )['value'] );
		// Not overridable at widget level.
		$this->assertSame( 'global', Settings::effective( 'sender_email', array( 'widget' => array( 'sender_email' => 'x@example.org' ) ) )['source'] );
	}

	public function test_config_export_excludes_contact_data_and_import_validates(): void {
		update_option( 'review_captain_requests', array_merge( Settings::group( 'requests' ), array( 'sender_email' => 'owner@example.org' ) ) );
		$export = SettingsPage::config_export();
		$this->assertArrayNotHasKey( 'sender_email', $export['settings']['requests'] );
		$this->assertStringNotContainsString( 'owner@example.org', wp_json_encode( $export ) );

		$export['settings']['submissions']['default_status'] = 'approved';
		$export['settings']['sync']['sync_interval_hours']   = 999; // Invalid, must be rejected.
		$result = SettingsPage::config_import( $export );
		$this->assertNotEmpty( $result['errors'] );
		$this->assertSame( 'approved', Settings::get( 'default_status' ) );
		$this->assertSame( 12, Settings::get( 'sync_interval_hours' ) );
		$this->assertSame( 'owner@example.org', Settings::get( 'sender_email' ) ); // Kept.

		$this->assertSame( 0, SettingsPage::config_import( array( 'plugin' => 'other' ) )['applied'] );
	}

	public function test_capabilities_installed_and_map_respects_admin(): void {
		Capabilities::install();
		$this->assertTrue( get_role( 'administrator' )->has_cap( 'rc_manage_settings' ) );
		$this->assertTrue( get_role( 'editor' )->has_cap( 'rc_moderate_reviews' ) );
		$this->assertFalse( get_role( 'editor' )->has_cap( 'rc_manage_privacy' ) );
		$this->assertNotNull( get_role( Capabilities::ROLE ) );
		Capabilities::apply_map( array( 'editor' => array( 'rc_view_reviews' ), 'administrator' => array() ) );
		$this->assertFalse( get_role( 'editor' )->has_cap( 'rc_moderate_reviews' ) );
		$this->assertTrue( get_role( 'administrator' )->has_cap( 'rc_manage_settings' ) );
		Capabilities::install();
	}

	public function test_crypto_roundtrip_and_tamper_detection(): void {
		$secret = '-----BEGIN PRIVATE KEY-----abc';
		$enc    = Crypto::encrypt( $secret );
		$this->assertStringStartsWith( 'rc1:', $enc );
		$this->assertStringNotContainsString( 'PRIVATE', $enc );
		$this->assertNotSame( $enc, Crypto::encrypt( $secret ), 'Nonce must differ per encryption.' );
		$this->assertSame( $secret, Crypto::decrypt( $enc ) );

		$parts    = explode( ':', $enc, 3 );
		$tampered = $parts[0] . ':deadbeef:' . $parts[2];
		try {
			Crypto::decrypt( $tampered );
			$this->fail( 'Expected exception' );
		} catch ( CryptoException $e ) {
			$this->assertSame( 'crypto_key_missing', $e->code_name() );
		}
		$this->assertSame( Crypto::lookup_hash( 'A@Example.org ' ), Crypto::lookup_hash( 'a@example.org' ) );
	}

	public function test_embed_allowlist(): void {
		$this->assertTrue( EmbedPolicy::is_allowed( 'https://www.youtube-nocookie.com/embed/x' ) );
		$this->assertFalse( EmbedPolicy::is_allowed( 'http://www.youtube-nocookie.com/embed/x' ) );
		$this->assertFalse( EmbedPolicy::is_allowed( 'https://evil.example.com/x' ) );
		$this->assertFalse( EmbedPolicy::is_allowed( 'https://user@player.vimeo.com/x' ) );
		$this->assertFalse( EmbedPolicy::is_allowed( 'https://player.vimeo.com:8443/x' ) );
		$html = EmbedPolicy::click_to_load( 'https://player.vimeo.com/video/1', 'Video' );
		$this->assertStringNotContainsString( '<iframe', $html );
		$this->assertStringContainsString( 'data-rc-embed', $html );
	}

	public function test_audit_log_filters_context(): void {
		\ReviewCaptain\Audit\AuditLog::log( 'test_event', 'review', 1, array( 'count' => 2, 'email' => 'secret@example.org', 'token' => 'abc' ) );
		$row = \ReviewCaptain\Audit\AuditLog::latest( 1 )[0];
		$this->assertSame( 'test_event', $row['event'] );
		$this->assertStringNotContainsString( 'secret@example.org', (string) $row['context'] );
		$this->assertStringNotContainsString( 'abc', (string) $row['context'] );
	}

	public function test_uninstall_keeps_data_by_default(): void {
		\ReviewCaptain\Install\Uninstaller::uninstall_site();
		$this->assertSame( REVIEW_CAPTAIN_DB_VERSION, Schema::installed_version() );
		Activator::install_site();
	}
}
