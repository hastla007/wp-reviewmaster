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
				'shortdesc' => __( 'Process due background jobs (for system cron).', 'review-captain' ),
				'synopsis'  => array(
					array(
						'type'        => 'assoc',
						'name'        => 'budget',
						'optional'    => true,
						'description' => __( 'Time budget in seconds (default: setting).', 'review-captain' ),
					),
				),
			),
			'jobs list'          => array(
				'method'    => 'jobs_list',
				'shortdesc' => __( 'List jobs.', 'review-captain' ),
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
				'shortdesc' => __( 'Requeue job.', 'review-captain' ),
				'synopsis'  => array(
					array(
						'type' => 'positional',
						'name' => 'id',
					),
				),
			),
			'jobs stats'         => array(
				'method'    => 'jobs_stats',
				'shortdesc' => __( 'Show job counts.', 'review-captain' ),
				'synopsis'  => array( $format ),
			),
			'maintenance run'    => array(
				'method'    => 'maintenance_run',
				'shortdesc' => __( 'Run retention and cleanup.', 'review-captain' ),
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
				'shortdesc' => __( 'Consistency check after upgrade/restore.', 'review-captain' ),
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
				'shortdesc' => __( 'Check schema, cron, and keys.', 'review-captain' ),
				'synopsis'  => array( $format ),
			),
			'dev seed'           => array(
				'method'    => 'dev_seed',
				'shortdesc' => __( 'Generate test data (development only).', 'review-captain' ),
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
				'shortdesc' => __( 'Install/update database schema.', 'review-captain' ),
				'synopsis'  => array(),
			),
			// SET-RC-18: settings with the same checks and rights as the settings page.
			'settings list'      => array(
				'method'    => 'settings_list',
				'shortdesc' => __( 'List settings with current value and default.', 'review-captain' ),
				'synopsis'  => array(
					array(
						'type'        => 'assoc',
						'name'        => 'tab',
						'optional'    => true,
						'description' => __( 'Only show this section, e.g. privacy.', 'review-captain' ),
					),
					$format,
				),
			),
			'settings get'       => array(
				'method'    => 'settings_get',
				'shortdesc' => __( 'Print the value of a setting (section.key).', 'review-captain' ),
				'synopsis'  => array(
					array(
						'type'        => 'positional',
						'name'        => 'key',
						'description' => __( 'section.key, e.g. submissions.min_text_length.', 'review-captain' ),
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
				'shortdesc' => __( 'Change a setting (validated and logged as on the settings page; needs --user).', 'review-captain' ),
				'synopsis'  => array(
					array(
						'type' => 'positional',
						'name' => 'key',
					),
					array(
						'type'        => 'positional',
						'name'        => 'value',
						'description' => __( 'New value. Yes/no: 1/0, true/false, on/off. Lists (roles): separated by commas.', 'review-captain' ),
					),
					array(
						'type'        => 'assoc',
						'name'        => 'confirm',
						'optional'    => true,
						'description' => __( 'Confirmation word (DELETE), needed when switching on privacy.delete_data_on_uninstall.', 'review-captain' ),
					),
					array(
						'type'        => 'assoc',
						'name'        => 'confirm-word',
						'optional'    => true,
						'description' => __( 'Alias for --confirm.', 'review-captain' ),
					),
				),
			),
			'settings reset'     => array(
				'method'    => 'settings_reset',
				'shortdesc' => __( 'Reset sections or all settings to the defaults (a backup is created; needs --user).', 'review-captain' ),
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
				'shortdesc' => __( 'Print the configuration as JSON or write it to a file (without credentials).', 'review-captain' ),
				'synopsis'  => array(
					array(
						'type'     => 'assoc',
						'name'     => 'file',
						'optional' => true,
					),
				),
			),
			'settings import'    => array(
				'method'    => 'settings_import',
				'shortdesc' => __( 'Import the configuration from a JSON file; --dry-run only shows the preview (needs --user).', 'review-captain' ),
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
