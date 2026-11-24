<?php
/**
 * WP-CLI registration.
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Cli;

use ReviewCaptain\Module;
use ReviewCaptain\Plugin;

/**
 * Registers `wp review-captain …` only when running under WP-CLI.
 */
final class CliModule implements Module {

	/**
	 * Register commands.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function register( Plugin $plugin ): void {
		if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) || ! class_exists( '\WP_CLI' ) ) {
			return;
		}
		// Register after WordPress is fully loaded: the definitions are translated and translations
		// must not be loaded before `init` (QA F-01, _load_textdomain_just_in_time notice).
		\WP_CLI::add_hook(
			'after_wp_load',
			static function () use ( $plugin ): void {
				self::add_commands( $plugin );
			}
		);
	}

	/**
	 * Add all commands.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	private static function add_commands( Plugin $plugin ): void {
		$commands = new Commands( $plugin, new WpCliOutput() );
		foreach ( self::definitions() as $name => $def ) {
			\WP_CLI::add_command(
				'review-captain ' . $name,
				array( $commands, $def['method'] ),
				array(
					'shortdesc' => $def['shortdesc'],
					'synopsis'  => $def['synopsis'],
				)
			);
		}
	}

	/**
	 * Command definitions.
	 *
	 * @return array<string, array{method: string, shortdesc: string, synopsis: list<array<string, mixed>>}>
	 */
	public static function definitions(): array {
		$format = array(
			'type'     => 'assoc',
			'name'     => 'format',
			'optional' => true,
			'default'  => 'table',
			'options'  => array( 'table', 'json', 'csv', 'yaml' ),
		);
		return array(
			'jobs run'           => array(
				'method'    => 'jobs_run',
				'shortdesc' => __( 'Fällige Hintergrundjobs abarbeiten (für System-Cron).', 'review-captain' ),
				'synopsis'  => array(
					array(
						'type'        => 'assoc',
						'name'        => 'budget',
						'optional'    => true,
						'description' => __( 'Zeitbudget in Sekunden (Standard: Einstellung).', 'review-captain' ),
					),
				),
			),
			'jobs list'          => array(
				'method'    => 'jobs_list',
				'shortdesc' => __( 'Jobs auflisten.', 'review-captain' ),
				'synopsis'  => array(
					array(
						'type'     => 'assoc',
						'name'     => 'status',
						'optional' => true,
					),
					array(
						'type'     => 'assoc',
						'name'     => 'limit',
						'optional' => true,
						'default'  => '50',
					),
					$format,
				),
			),
			'jobs retry'         => array(
				'method'    => 'jobs_retry',
				'shortdesc' => __( 'Job erneut einreihen.', 'review-captain' ),
				'synopsis'  => array(
					array(
						'type' => 'positional',
						'name' => 'id',
					),
				),
			),
			'jobs stats'         => array(
				'method'    => 'jobs_stats',
				'shortdesc' => __( 'Job-Zähler anzeigen.', 'review-captain' ),
				'synopsis'  => array( $format ),
			),
			'maintenance run'    => array(
				'method'    => 'maintenance_run',
				'shortdesc' => __( 'Aufbewahrung und Bereinigung ausführen.', 'review-captain' ),
				'synopsis'  => array(
					array(
						'type'     => 'assoc',
						'name'     => 'budget',
						'optional' => true,
					),
				),
			),
			'reconcile'          => array(
				'method'    => 'reconcile',
				'shortdesc' => __( 'Konsistenzprüfung nach Upgrade/Restore.', 'review-captain' ),
				'synopsis'  => array(
					array(
						'type'     => 'flag',
						'name'     => 'fix',
						'optional' => true,
					),
					array(
						'type'     => 'assoc',
						'name'     => 'mode',
						'optional' => true,
						'default'  => 'manual',
						'options'  => array( 'upgrade', 'reactivate', 'restore', 'clone', 'manual' ),
					),
					$format,
				),
			),
			'doctor'             => array(
				'method'    => 'doctor',
				'shortdesc' => __( 'Schema, Cron und Schlüssel prüfen.', 'review-captain' ),
				'synopsis'  => array( $format ),
			),
			'dev seed'           => array(
				'method'    => 'dev_seed',
				'shortdesc' => __( 'Testdaten erzeugen (nur Entwicklung).', 'review-captain' ),
				'synopsis'  => array(
					array(
						'type'     => 'assoc',
						'name'     => 'reviews',
						'optional' => true,
						'default'  => '0',
					),
					array(
						'type'     => 'assoc',
						'name'     => 'jobs',
						'optional' => true,
						'default'  => '0',
					),
					array(
						'type'     => 'flag',
						'name'     => 'yes',
						'optional' => true,
					),
				),
			),
			'schema upgrade'     => array(
				'method'    => 'schema_upgrade',
				'shortdesc' => __( 'Datenbankschema installieren/aktualisieren.', 'review-captain' ),
				'synopsis'  => array(),
			),
			// SET-RC-18: settings with the same checks and rights as the settings page.
			'settings list'      => array(
				'method'    => 'settings_list',
				'shortdesc' => __( 'Einstellungen mit aktuellem Wert und Standard auflisten.', 'review-captain' ),
				'synopsis'  => array(
					array(
						'type'        => 'assoc',
						'name'        => 'tab',
						'optional'    => true,
						'description' => __( 'Nur diesen Bereich zeigen, z. B. privacy.', 'review-captain' ),
					),
					$format,
				),
			),
			'settings get'       => array(
				'method'    => 'settings_get',
				'shortdesc' => __( 'Wert einer Einstellung ausgeben (Bereich.Schlüssel).', 'review-captain' ),
				'synopsis'  => array(
					array(
						'type'        => 'positional',
						'name'        => 'key',
						'description' => __( 'Bereich.Schlüssel, z. B. submissions.min_text_length.', 'review-captain' ),
					),
					array(
						'type'     => 'assoc',
						'name'     => 'format',
						'optional' => true,
						'default'  => 'plain',
						'options'  => array( 'plain', 'json' ),
					),
				),
			),
			'settings set'       => array(
				'method'    => 'settings_set',
				'shortdesc' => __( 'Eine Einstellung ändern (wie auf der Einstellungsseite geprüft und protokolliert; braucht --user).', 'review-captain' ),
				'synopsis'  => array(
					array(
						'type' => 'positional',
						'name' => 'key',
					),
					array(
						'type'        => 'positional',
						'name'        => 'value',
						'description' => __( 'Neuer Wert. Ja/Nein: 1/0, true/false, on/off. Listen (Rollen): durch Komma getrennt.', 'review-captain' ),
					),
					array(
						'type'        => 'assoc',
						'name'        => 'confirm',
						'optional'    => true,
						'description' => __( 'Bestätigungswort (LÖSCHEN), nötig beim Einschalten von privacy.delete_data_on_uninstall.', 'review-captain' ),
					),
					array(
						'type'        => 'assoc',
						'name'        => 'confirm-word',
						'optional'    => true,
						'description' => __( 'Alias für --confirm.', 'review-captain' ),
					),
				),
			),
			'settings reset'     => array(
				'method'    => 'settings_reset',
				'shortdesc' => __( 'Bereiche oder alle Einstellungen auf den Standard zurücksetzen (Sicherung wird angelegt; braucht --user).', 'review-captain' ),
				'synopsis'  => array(
					array(
						'type'      => 'positional',
						'name'      => 'tab',
						'optional'  => true,
						'repeating' => true,
					),
					array(
						'type'     => 'flag',
						'name'     => 'all',
						'optional' => true,
					),
					array(
						'type'     => 'flag',
						'name'     => 'yes',
						'optional' => true,
					),
				),
			),
			'settings export'    => array(
				'method'    => 'settings_export',
				'shortdesc' => __( 'Konfiguration als JSON ausgeben oder in eine Datei schreiben (ohne Zugangsdaten; Kontaktangaben nur mit --with-contacts).', 'review-captain' ),
				'synopsis'  => array(
					array(
						'type'     => 'assoc',
						'name'     => 'file',
						'optional' => true,
					),
					array(
						'type'        => 'flag',
						'name'        => 'with-contacts',
						'optional'    => true,
						'description' => __( 'Mit Kontaktangaben exportieren (Empfängeradressen, Markenname, Absenderangaben).', 'review-captain' ),
					),
				),
			),
			'settings import'    => array(
				'method'    => 'settings_import',
				'shortdesc' => __( 'Konfiguration aus einer JSON-Datei importieren; --dry-run zeigt nur die Vorschau (braucht --user).', 'review-captain' ),
				'synopsis'  => array(
					array(
						'type' => 'positional',
						'name' => 'file',
					),
					array(
						'type'     => 'flag',
						'name'     => 'dry-run',
						'optional' => true,
					),
					array(
						'type'     => 'flag',
						'name'     => 'yes',
						'optional' => true,
					),
					array(
						'type'     => 'assoc',
						'name'     => 'format',
						'optional' => true,
						'default'  => 'table',
						'options'  => array( 'table', 'json', 'csv', 'yaml' ),
					),
				),
			),
		);
	}
}
