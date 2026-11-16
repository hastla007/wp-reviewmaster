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
		);
	}
}
