<?php
/**
 * Stand-in for Review Captain Pro registering its request fields (SET-RC-06/07).
 *
 * @package ReviewCaptain\Tests
 */

declare(strict_types=1);

namespace ReviewCaptain\Tests\Support;

use ReviewCaptain\Admin\SettingsHints;

/**
 * Adds the seven fields of the tab "requests" through `review_captain_settings_schema` the way the
 * add-on does (owner pro, own label/description/section, replaces_hint), optionally writable.
 */
final class ProRequestsFields {

	/**
	 * Register the fields (and the writable answer).
	 *
	 * @param bool $writable Answer of `review_captain_pro_settings_writable`.
	 */
	public static function register( bool $writable = true ): void {
		add_filter(
			'review_captain_settings_schema',
			static function ( array $schema ): array {
				$schema['requests'] = array_merge( (array) ( $schema['requests'] ?? array() ), self::fields() );
				return $schema;
			}
		);
		if ( $writable ) {
			add_filter( 'review_captain_pro_settings_writable', '__return_true' );
		}
	}

	/**
	 * Field definitions as in the add-on.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function fields(): array {
		return array(
			'sender_name'       => array( 'type' => 'string', 'default' => '', 'owner' => 'pro', 'privacy' => 'config', 'label' => 'Absendername', 'description' => 'Name im Absender von Einladungen. Leer = Markenname.', 'section' => 'Absender', 'replaces_hint' => array( SettingsHints::REQUESTS, SettingsHints::SENDER ) ),
			'sender_email'      => array( 'type' => 'email', 'default' => '', 'owner' => 'pro', 'privacy' => 'config', 'label' => 'Absenderadresse', 'section' => 'Absender', 'replaces_hint' => SettingsHints::SENDER ),
			'send_window_start' => array( 'type' => 'time', 'default' => '09:00', 'owner' => 'pro', 'label' => 'Versand ab', 'section' => 'Versandzeiten', 'replaces_hint' => SettingsHints::SEND_WINDOW ),
			'send_window_end'   => array( 'type' => 'time', 'default' => '18:00', 'owner' => 'pro', 'label' => 'Versand bis', 'section' => 'Versandzeiten', 'replaces_hint' => SettingsHints::SEND_WINDOW ),
			'daily_limit'       => array( 'type' => 'int', 'default' => 200, 'min' => 1, 'max' => 100000, 'owner' => 'pro', 'label' => 'Tageslimit', 'section' => 'Grenzen', 'replaces_hint' => SettingsHints::LIMITS ),
			'cooldown_days'     => array( 'type' => 'int', 'default' => 90, 'min' => 0, 'max' => 3650, 'owner' => 'pro', 'label' => 'Mindestabstand je Empfänger (Tage)', 'section' => 'Grenzen', 'replaces_hint' => SettingsHints::LIMITS ),
			'reminders_enabled' => array( 'type' => 'bool', 'default' => false, 'owner' => 'pro', 'label' => 'Erinnerungen erlauben', 'section' => 'Erinnerungen', 'replaces_hint' => SettingsHints::REMINDERS ),
		);
	}
}
