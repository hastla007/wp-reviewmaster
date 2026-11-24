<?php
/**
 * Decision of 24.11.2026: write permission per add-on field (`review_captain_pro_field_writable`).
 */

declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Admin;

use ReviewCaptain\Admin\ConfigTransfer;
use ReviewCaptain\Admin\SettingsPage;
use ReviewCaptain\Security\Capabilities;
use ReviewCaptain\Settings\Settings;
use ReviewCaptain\Tests\Support\ProRequestsFields;

final class SettingsProFieldWritableTest extends AdminTestCase {

	private function page(): SettingsPage {
		return new SettingsPage( $this->plugin() );
	}

	/**
	 * Only the "limits" feature answers: daily_limit and cooldown_days writable, the rest not.
	 */
	private function allow_limits_only(): void {
		add_filter(
			'review_captain_pro_field_writable',
			static fn( bool $writable, string $tab, string $key ): bool => 'requests' === $tab && in_array( $key, array( 'daily_limit', 'cooldown_days' ), true ),
			10,
			3
		);
	}

	public function test_default_comes_from_the_shared_filter(): void {
		$this->assertFalse( SettingsPage::pro_field_writable( 'requests', 'daily_limit' ) );
		add_filter( 'review_captain_pro_settings_writable', '__return_true' );
		$this->assertTrue( SettingsPage::pro_field_writable( 'requests', 'daily_limit' ), 'an older add-on answering only the shared filter keeps working' );

		$seen = array();
		add_filter(
			'review_captain_pro_field_writable',
			static function ( bool $writable, string $tab, string $key ) use ( &$seen ): bool {
				$seen[] = array( $writable, $tab, $key );
				return 'sender_email' !== $key && $writable;
			},
			10,
			3
		);
		$this->assertFalse( SettingsPage::pro_field_writable( 'requests', 'sender_email' ) );
		$gate = SettingsPage::pro_field_gate();
		$this->assertTrue( $gate( 'requests', 'daily_limit' ) );
		$this->assertSame( array( true, 'requests', 'daily_limit' ), end( $seen ) );
		$this->assertFalse( SettingsPage::pro_field_writable( 'requests', 'x', false ), 'the given shared answer is the default' );
	}

	public function test_save_and_render_ask_per_field(): void {
		$this->login_with( Capabilities::MANAGE_SETTINGS );
		ProRequestsFields::register( false );
		$this->allow_limits_only();

		$this->page()->save_tab(
			'requests',
			array(
				'requests' => array(
					'daily_limit'       => '33',
					'sender_email'      => 'x@example.test',
					'reminders_enabled' => '1',
				),
			)
		);
		$settings = new Settings();
		$this->assertSame( 33, $settings->get( 'requests', 'daily_limit' ) );
		$this->assertSame( '', $settings->get( 'requests', 'sender_email' ), 'field of another feature stays untouched' );
		$this->assertFalse( $settings->get( 'requests', 'reminders_enabled' ) );

		$_GET = array(
			'page' => SettingsPage::SLUG,
			'tab'  => 'requests',
		);
		ob_start();
		$this->page()->render();
		$html = (string) ob_get_clean();
		$this->assertDoesNotMatchRegularExpression( '/name="review_captain_settings\[requests\]\[daily_limit\]"[^>]*disabled/', $html );
		$this->assertMatchesRegularExpression( '/name="review_captain_settings\[requests\]\[sender_email\]"[^>]*disabled/', $html );
		$this->assertStringContainsString( 'button-primary', $html, 'a tab with one writable add-on field keeps its save button' );
	}

	public function test_manage_settings_is_still_required(): void {
		$this->login_with();
		ProRequestsFields::register();
		$this->assertFalse( SettingsPage::can_write_tab( 'requests' ) );
		$this->assertNotContains( 'requests', SettingsPage::writable_tabs() );
	}

	public function test_import_preview_and_apply_ask_per_field(): void {
		$this->login_with( Capabilities::MANAGE_SETTINGS );
		ProRequestsFields::register( false );
		$transfer = new ConfigTransfer( new Settings() );
		$doc      = $transfer->export();
		$doc['settings']['requests']['daily_limit']  = 12;
		$doc['settings']['requests']['sender_email'] = 'x@example.test';

		$this->allow_limits_only();
		$preview = $transfer->preview( (string) wp_json_encode( $doc ), SettingsPage::writable_tabs(), SettingsPage::pro_field_gate() );
		$keys    = array_map( static fn( array $row ): string => $row['tab'] . '.' . $row['key'], $preview['diff'] );
		$this->assertContains( 'requests.daily_limit', $keys );
		$this->assertNotContains( 'requests.sender_email', $keys );

		// A newer add-on overrides the shared answer: sender_email stays locked although the shared filter says yes.
		add_filter( 'review_captain_pro_settings_writable', '__return_true' );
		$preview = $transfer->preview( (string) wp_json_encode( $doc ), SettingsPage::writable_tabs(), SettingsPage::pro_field_gate() );
		$this->assertSame( '', $preview['values']['requests']['sender_email'] );

		// Apply re-checks per field: a value of a locked field in the stored preview is not written.
		$values                             = $preview['values'];
		$values['requests']['sender_email'] = 'forged@example.test';
		$changed                            = $this->page()->apply_import( $values, $preview['tabs'] );
		$this->assertContains( 'requests.daily_limit', $changed );
		$settings = new Settings();
		$this->assertSame( 12, $settings->get( 'requests', 'daily_limit' ) );
		$this->assertSame( '', $settings->get( 'requests', 'sender_email' ) );
		$this->assertNotEmpty( $this->audit_rows( 'settings.imported' ) );
	}
}
