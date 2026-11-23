<?php
/**
 * WP-CLI commands `wp review-captain …` (RC-036, TEC-014). Thin layer over services.
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Cli;

use ReviewCaptain\Admin\ConfigTransfer;
use ReviewCaptain\Admin\SettingsPage;
use ReviewCaptain\Audit\SettingsChanges;
use ReviewCaptain\Install\Installer;
use ReviewCaptain\Install\Schema;
use ReviewCaptain\Jobs\Diagnostics;
use ReviewCaptain\Jobs\JobQueue;
use ReviewCaptain\Jobs\JobsModule;
use ReviewCaptain\Jobs\Runner;
use ReviewCaptain\Maintenance\DevSeeder;
use ReviewCaptain\Maintenance\Maintenance;
use ReviewCaptain\Maintenance\Reconciler;
use ReviewCaptain\Plugin;
use ReviewCaptain\Security\Capabilities;
use ReviewCaptain\Security\Crypto;
use ReviewCaptain\Settings\SettingsSchema;
use ReviewCaptain\Support\Requirements;

/**
 * Command implementations. Each method takes WP-CLI style ($args, $assoc_args).
 */
class Commands {

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 * @param Output $out    Output.
	 */
	public function __construct( private Plugin $plugin, private Output $out ) {}

	/**
	 * Queue service.
	 */
	private function queue(): JobQueue {
		/** @var JobQueue */
		return $this->plugin->get( 'jobs' );
	}

	/**
	 * `jobs run [--budget=<s>]`.
	 *
	 * @param list<string>          $args  Args.
	 * @param array<string, string> $assoc Assoc args.
	 * @return array{processed:int, succeeded:int, failed:int}
	 */
	public function jobs_run( array $args, array $assoc ): array {
		$budget = isset( $assoc['budget'] ) ? (float) $assoc['budget'] : 0.0;
		Runner::heartbeat( 'cli' );
		/** @var Runner $runner */
		$runner = $this->plugin->get( 'jobs.runner' );
		$result = $runner->run( $budget );
		$this->out->success(
			sprintf(
				/* translators: 1: processed, 2: succeeded, 3: failed */
				__( '%1$d jobs processed, %2$d succeeded, %3$d failed.', 'review-captain' ),
				$result['processed'],
				$result['succeeded'],
				$result['failed']
			)
		);
		return $result;
	}

	/**
	 * `jobs list [--status=<s>] [--limit=<n>] [--format=<f>]`.
	 *
	 * @param list<string>          $args  Args.
	 * @param array<string, string> $assoc Assoc args.
	 */
	public function jobs_list( array $args, array $assoc ): void {
		$status = isset( $assoc['status'] ) ? (string) $assoc['status'] : null;
		if ( null !== $status && ! in_array( $status, JobQueue::STATUSES, true ) ) {
			$this->out->error( __( 'Unknown status.', 'review-captain' ) );
			return;
		}
		$rows = array_map(
			static fn( array $j ): array => array(
				'id'           => $j['id'],
				'type'         => $j['type'],
				'status'       => $j['status'],
				'attempts'     => $j['attempts'] . '/' . $j['max_attempts'],
				'available_at' => $j['available_at'],
				'error'        => (string) $j['last_error_code'],
				'message'      => (string) $j['last_error_message'],
			),
			$this->queue()->recent( (int) ( $assoc['limit'] ?? 50 ), $status )
		);
		$this->out->table( $rows, array( 'id', 'type', 'status', 'attempts', 'available_at', 'error', 'message' ), (string) ( $assoc['format'] ?? 'table' ) );
	}

	/**
	 * `jobs retry <id>`.
	 *
	 * @param list<string>          $args  Args.
	 * @param array<string, string> $assoc Assoc args.
	 */
	public function jobs_retry( array $args, array $assoc ): void {
		$id = (int) ( $args[0] ?? 0 );
		if ( $id <= 0 || ! $this->queue()->retry( $id ) ) {
			$this->out->error( __( 'Job not found or not retryable.', 'review-captain' ) );
			return;
		}
		$this->out->success( __( 'Job requeued.', 'review-captain' ) );
	}

	/**
	 * `jobs stats [--format=<f>]`.
	 *
	 * @param list<string>          $args  Args.
	 * @param array<string, string> $assoc Assoc args.
	 */
	public function jobs_stats( array $args, array $assoc ): void {
		$stats = $this->queue()->stats();
		$rows  = array();
		foreach ( $stats as $key => $value ) {
			$rows[] = array(
				'key'   => $key,
				'value' => null === $value ? '' : (string) $value,
			);
		}
		$this->out->table( $rows, array( 'key', 'value' ), (string) ( $assoc['format'] ?? 'table' ) );
	}

	/**
	 * `maintenance run`.
	 *
	 * @param list<string>          $args  Args.
	 * @param array<string, string> $assoc Assoc args.
	 * @return array<string, mixed>
	 */
	public function maintenance_run( array $args, array $assoc ): array {
		/** @var Maintenance $maintenance */
		$maintenance = $this->plugin->get( 'maintenance' );
		$result      = $maintenance->run( isset( $assoc['budget'] ) ? (float) $assoc['budget'] : 60.0 );
		$r           = $result['retention'];
		$this->out->success(
			sprintf(
				/* translators: 1: rejected, 2: external, 3: logs, 4: jobs */
				__( 'Retention: deleted %1$d rejected reviews, %2$d external snapshots, %3$d log entries, %4$d jobs.', 'review-captain' ),
				$r['rejected_deleted'],
				$r['external_deleted'],
				$r['logs_deleted'],
				$r['jobs_deleted']
			)
		);
		if ( empty( $r['complete'] ) ) {
			$this->out->warning( __( 'Not all due data was cleaned up in this run; the next run will continue.', 'review-captain' ) );
		}
		return $result;
	}

	/**
	 * `reconcile [--fix] [--mode=<m>] [--format=<f>]`.
	 *
	 * @param list<string>          $args  Args.
	 * @param array<string, string> $assoc Assoc args.
	 * @return array<string, mixed>
	 */
	public function reconcile( array $args, array $assoc ): array {
		/** @var Reconciler $reconciler */
		$reconciler = $this->plugin->get( 'reconciler' );
		$report     = $reconciler->run( ! empty( $assoc['fix'] ), (string) ( $assoc['mode'] ?? 'manual' ) );
		$rows       = array();
		foreach ( $report as $key => $value ) {
			$rows[] = array(
				'check' => $key,
				'value' => in_array( (string) ( $assoc['format'] ?? 'table' ), array( 'json', 'yaml' ), true ) ? $value : ( is_scalar( $value ) ? var_export( $value, true ) : (string) wp_json_encode( $value ) ), // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
			);
		}
		$this->out->table( $rows, array( 'check', 'value' ), (string) ( $assoc['format'] ?? 'table' ) );
		if ( empty( $report['schema_ok'] ) ) {
			$this->out->error( __( 'Schema outdated or incomplete: run “wp review-captain schema upgrade” first.', 'review-captain' ) );
		}
		return $report;
	}

	/**
	 * `doctor`: schema version, cron status, crypto key.
	 *
	 * @param list<string>          $args  Args.
	 * @param array<string, string> $assoc Assoc args.
	 * @return list<array{check: string, status: string, message: string}>
	 */
	public function doctor( array $args, array $assoc ): array {
		$checks    = array();
		$installed = (int) get_option( Schema::OPTION_VERSION, 0 );
		$checks[]  = array(
			'check'   => 'schema',
			'status'  => Schema::VERSION === $installed ? 'ok' : 'error',
			'message' => sprintf(
				/* translators: 1: installed, 2: expected */
				__( 'Schema version %1$d (expected %2$d).', 'review-captain' ),
				$installed,
				Schema::VERSION
			),
		);

		$scheduled = false !== wp_next_scheduled( JobsModule::HOOK_RUN )
			|| ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( JobsModule::HOOK_RUN, array(), JobsModule::AS_GROUP ) );
		$checks[]  = array(
			'check'   => 'cron',
			'status'  => $scheduled ? 'ok' : 'warning',
			'message' => $scheduled ? __( 'Job tick is scheduled.', 'review-captain' ) : __( 'Job tick is not scheduled (visit the admin area once or reactivate the plugin).', 'review-captain' ),
		);
		$health   = Diagnostics::runner_health();
		$checks[] = array(
			'check'   => 'runner',
			'status'  => 'ok' === $health['status'] ? 'ok' : 'warning',
			'message' => Diagnostics::wp_cron_disabled()
				? __( 'DISABLE_WP_CRON is set – system cron must run “wp review-captain jobs run”.', 'review-captain' )
				: __( 'WP-Cron active: runs only on page views, timing is imprecise. System cron recommended.', 'review-captain' ),
		);

		$crypto   = new Crypto();
		$status   = $crypto->status();
		$checks[] = array(
			'check'   => 'crypto',
			'status'  => Crypto::STATUS_EXPLICIT === $status ? 'ok' : ( Crypto::STATUS_FALLBACK === $status ? 'warning' : 'error' ),
			'message' => match ( $status ) {
				Crypto::STATUS_EXPLICIT => __( 'Custom key REVIEW_CAPTAIN_ENCRYPTION_KEY is set.', 'review-captain' ),
				Crypto::STATUS_FALLBACK => __( 'Credentials are encrypted with a key derived from the WordPress salts. Recommendation: set REVIEW_CAPTAIN_ENCRYPTION_KEY.', 'review-captain' ),
				Crypto::STATUS_INVALID  => __( 'REVIEW_CAPTAIN_ENCRYPTION_KEY is invalid (expected Base64 of exactly 32 bytes).', 'review-captain' ),
				default                 => __( 'libsodium is not available.', 'review-captain' ),
			},
		);

		// MINVER-1: an older database server is a warning, never a block.
		$db       = Requirements::current_db();
		$checks[] = array(
			'check'   => 'database',
			'status'  => true === $db['ok'] ? 'ok' : 'warning',
			'message' => null === $db['ok']
				? __( 'Unable to determine database version.', 'review-captain' )
				: sprintf(
					/* translators: 1: vendor, 2: installed version, 3: minimum version */
					__( 'Database %1$s %2$s (recommended minimum %3$s).', 'review-captain' ),
					'mariadb' === $db['vendor'] ? 'MariaDB' : 'MySQL',
					$db['version'],
					$db['minimum']
				),
		);

		$this->out->table( $checks, array( 'check', 'status', 'message' ), (string) ( $assoc['format'] ?? 'table' ) );
		return $checks;
	}

	/**
	 * `dev seed --reviews=<n> --jobs=<n> [--yes]` (only with WP_DEBUG or --yes).
	 *
	 * @param list<string>          $args  Args.
	 * @param array<string, string> $assoc Assoc args.
	 * @return array{reviews:int, jobs:int}
	 */
	public function dev_seed( array $args, array $assoc ): array {
		$debug = defined( 'WP_DEBUG' ) && WP_DEBUG;
		if ( ! $debug && empty( $assoc['yes'] ) ) {
			$this->out->error( __( 'Generate test data only with WP_DEBUG or --yes.', 'review-captain' ) );
			return array(
				'reviews' => 0,
				'jobs'    => 0,
			);
		}
		$reviews = max( 0, min( 1000000, (int) ( $assoc['reviews'] ?? 0 ) ) );
		$jobs    = max( 0, min( 1000000, (int) ( $assoc['jobs'] ?? 0 ) ) );
		$seeder  = new DevSeeder();
		$out     = array(
			'reviews' => $reviews > 0 ? $seeder->seed_reviews( $reviews ) : 0,
			'jobs'    => $jobs > 0 ? $seeder->seed_jobs( $jobs ) : 0,
		);
		$this->out->success(
			sprintf(
				/* translators: 1: reviews, 2: jobs */
				__( 'Created %1$d test reviews and %2$d test jobs.', 'review-captain' ),
				$out['reviews'],
				$out['jobs']
			)
		);
		return $out;
	}

	/**
	 * `schema upgrade`.
	 *
	 * @param list<string>          $args  Args.
	 * @param array<string, string> $assoc Assoc args.
	 */
	public function schema_upgrade( array $args, array $assoc ): void {
		Installer::install_site();
		$this->out->success(
			sprintf(
				/* translators: %d: schema version */
				__( 'Schema is at version %d.', 'review-captain' ),
				(int) get_option( Schema::OPTION_VERSION, 0 )
			)
		);
	}

	// ---------------------------------------------------------------------------------------------
	// Settings (SET-RC-18). Same schema, checks, rights and audit as the settings page.

	/**
	 * Max size of an import file in bytes (same limit as the upload on the settings page).
	 */
	private const IMPORT_MAX_BYTES = 200000;

	/**
	 * Settings page logic (no output).
	 */
	private function settings_page(): SettingsPage {
		return new SettingsPage( $this->plugin );
	}

	/**
	 * Resolve "tab.key" to its field definition or stop with an error.
	 *
	 * @param string $name Tab.key.
	 * @return array{0: string, 1: string, 2: array<string, mixed>}|null
	 */
	private function settings_field( string $name ): ?array {
		[ $tab, $key ] = array_pad( explode( '.', $name, 2 ), 2, '' );
		$fields        = SettingsSchema::fields();
		if ( ! isset( $fields[ $tab ][ $key ] ) ) {
			/* translators: %s: setting key */
			$this->out->error( sprintf( __( 'Unknown setting “%s”. Format: section.key, see “wp review-captain settings list”.', 'review-captain' ), $name ) );
			return null;
		}
		return array( $tab, $key, $fields[ $tab ][ $key ] );
	}

	/**
	 * Writes need a user with the same rights as on the settings page (`--user=<id|login>`).
	 *
	 * @param list<string> $tabs Tabs to write.
	 */
	private function settings_require_writer( array $tabs ): bool {
		if ( get_current_user_id() <= 0 || ! current_user_can( Capabilities::MANAGE_SETTINGS ) ) {
			$this->out->error( __( 'Please name a user with --user=<ID|login> who may change the settings (capability review_captain_manage_settings).', 'review-captain' ) );
			return false;
		}
		foreach ( $tabs as $tab ) {
			if ( ! SettingsPage::can_write_tab( $tab ) ) {
				/* translators: %s: tab */
				$this->out->error( sprintf( __( 'The user lacks the permission for section “%s” (privacy: review_captain_manage_privacy, access: promote_users).', 'review-captain' ), $tab ) );
				return false;
			}
		}
		return true;
	}

	/**
	 * Value for output; secrets are never printed.
	 *
	 * @param string               $key   Key.
	 * @param array<string, mixed> $field Field.
	 * @param mixed                $value Value.
	 */
	private static function settings_output_value( string $key, array $field, mixed $value ): mixed {
		if ( 'secret' === ( $field['type'] ?? '' ) || preg_match( ConfigTransfer::SECRET_PATTERN, $key ) ) {
			return SettingsChanges::HIDDEN;
		}
		return $value;
	}

	/**
	 * `settings list [--tab=<tab>] [--format=<f>]`.
	 *
	 * @param list<string>          $args  Args.
	 * @param array<string, string> $assoc Assoc args.
	 * @return list<array<string, mixed>>
	 */
	public function settings_list( array $args, array $assoc ): array {
		$only = isset( $assoc['tab'] ) ? (string) $assoc['tab'] : null;
		if ( null !== $only && ! array_key_exists( $only, SettingsSchema::tabs() ) ) {
			$this->out->error( __( 'Unknown section.', 'review-captain' ) );
			return array();
		}
		$format = (string) ( $assoc['format'] ?? 'table' );
		$raw    = in_array( $format, array( 'json', 'yaml' ), true );
		$values = $this->plugin->settings()->all();
		$rows   = array();
		foreach ( SettingsSchema::fields() as $tab => $fields ) {
			if ( null !== $only && $only !== $tab ) {
				continue;
			}
			foreach ( $fields as $key => $field ) {
				$value  = self::settings_output_value( (string) $key, $field, $values[ $tab ][ $key ] ?? $field['default'] );
				$rows[] = array(
					'key'     => $tab . '.' . $key,
					'value'   => $raw ? $value : ConfigTransfer::display_value( $value ),
					'default' => $raw ? $field['default'] : ConfigTransfer::display_value( $field['default'] ),
					'type'    => (string) $field['type'],
					'owner'   => (string) $field['owner'] . ( ! empty( $field['internal'] ) ? ' (intern)' : '' ),
				);
			}
		}
		$this->out->table( $rows, array( 'key', 'value', 'default', 'type', 'owner' ), $format );
		return $rows;
	}

	/**
	 * `settings get <tab.key> [--format=plain|json]`.
	 *
	 * @param list<string>          $args  Args.
	 * @param array<string, string> $assoc Assoc args.
	 */
	public function settings_get( array $args, array $assoc ): mixed {
		$found = $this->settings_field( (string) ( $args[0] ?? '' ) );
		if ( null === $found ) {
			return null;
		}
		[ $tab, $key, $field ] = $found;
		$value                 = self::settings_output_value( $key, $field, $this->plugin->settings()->get( $tab, $key, $field['default'] ) );
		if ( 'json' === ( $assoc['format'] ?? 'plain' ) ) {
			$this->out->log( (string) wp_json_encode( $value ) );
		} else {
			$this->out->log( is_bool( $value ) ? ( $value ? 'true' : 'false' ) : ( is_array( $value ) ? implode( ',', array_map( 'strval', $value ) ) : (string) $value ) );
		}
		return $value;
	}

	/**
	 * `settings set <tab.key> <value> [--confirm=<word>]` (alias `--confirm-word`) – goes through the settings page's
	 * save logic: validation with error instead of correction, cross-checks, Pro lock, role rules,
	 * confirmation word, audit with old and new value.
	 *
	 * @param list<string>          $args  Args.
	 * @param array<string, string> $assoc Assoc args.
	 * @return array{changed: list<string>, invalid: list<string>, errors: array<string, string>, given: array<string, string>}|null
	 */
	public function settings_set( array $args, array $assoc ): ?array {
		$found = $this->settings_field( (string) ( $args[0] ?? '' ) );
		if ( null === $found ) {
			return null;
		}
		[ $tab, $key, $field ] = $found;
		if ( ! empty( $field['internal'] ) ) {
			$this->out->error( __( 'This value is set by the plugin and cannot be changed directly.', 'review-captain' ) );
			return null;
		}
		if ( 'pro' === $field['owner'] && ! SettingsPage::pro_writable() ) {
			$this->out->error( __( 'This setting belongs to an extension and cannot be changed right now.', 'review-captain' ) );
			return null;
		}
		if ( ! $this->settings_require_writer( array( $tab ) ) ) {
			return null;
		}
		$given = (string) ( $args[1] ?? '' );
		if ( 'bool' === $field['type'] ) {
			$bool = self::parse_bool( $given );
			if ( null === $bool ) {
				$this->out->error( __( 'Please give 1 or 0 (true/false, on/off, yes/no).', 'review-captain' ) );
				return null;
			}
			$value = $bool;
		} elseif ( 'array' === $field['type'] ) {
			$value = array_values( array_filter( array_map( 'trim', explode( ',', $given ) ), 'strlen' ) );
		} else {
			$value = $given;
		}

		// Booleans of the tab are sent with their current value (a missing checkbox means "off").
		$input  = array();
		$fields = SettingsSchema::fields()[ $tab ];
		$all    = $this->plugin->settings()->all();
		foreach ( $fields as $k => $f ) {
			if ( 'bool' === $f['type'] ) {
				$input[ $k ] = (bool) ( $all[ $tab ][ $k ] ?? $f['default'] );
			}
		}
		$input[ $key ] = $value;
		// S10 suite standard: --confirm=LÖSCHEN; --confirm-word stays as an alias.
		$word = $assoc['confirm'] ?? $assoc['confirm-word'] ?? null;
		if ( null !== $word ) {
			$input['uninstall_confirm_word'] = (string) $word;
		}
		$result = $this->settings_page()->save_tab( $tab, array( $tab => $input ) );
		$this->plugin->settings()->flush();

		$own = $result['errors'][ $tab . '-' . $key ] ?? null;
		foreach ( $result['errors'] as $id => $message ) {
			if ( $id !== $tab . '-' . $key ) {
				/* translators: 1: field, 2: message */
				$this->out->warning( sprintf( __( '%1$s: %2$s', 'review-captain' ), str_replace( '-', '.', $id ), $message ) );
			}
		}
		if ( null !== $own ) {
			/* translators: 1: setting key, 2: message */
			$this->out->error( sprintf( __( '%1$s was not saved: %2$s', 'review-captain' ), $tab . '.' . $key, $own ) );
			return $result;
		}
		if ( ! in_array( $tab . '.' . $key, $result['changed'], true ) ) {
			/* translators: %s: setting key */
			$this->out->success( sprintf( __( '%s is unchanged.', 'review-captain' ), $tab . '.' . $key ) );
			return $result;
		}
		/* translators: 1: setting key, 2: value */
		$this->out->success( sprintf( __( '%1$s saved: %2$s', 'review-captain' ), $tab . '.' . $key, ConfigTransfer::display_value( self::settings_output_value( $key, $field, $this->plugin->settings()->get( $tab, $key ) ) ) ) );
		return $result;
	}

	/**
	 * Strict yes/no parser for CLI values.
	 *
	 * @param string $value Value.
	 */
	private static function parse_bool( string $value ): ?bool {
		$value = strtolower( trim( $value ) );
		if ( in_array( $value, array( '1', 'true', 'on', 'yes', 'ja' ), true ) ) {
			return true;
		}
		if ( in_array( $value, array( '0', 'false', 'off', 'no', 'nein' ), true ) ) {
			return false;
		}
		return null;
	}

	/**
	 * `settings reset [<tab>...] [--all] --yes`.
	 *
	 * @param list<string>          $args  Args.
	 * @param array<string, string> $assoc Assoc args.
	 * @return list<string> Reset tabs.
	 */
	public function settings_reset( array $args, array $assoc ): array {
		$known = array_keys( SettingsSchema::tabs() );
		if ( ! empty( $assoc['all'] ) ) {
			$tabs = $known;
		} else {
			$tabs = array_values( array_unique( array_map( 'strval', $args ) ) );
			if ( ! $tabs ) {
				$this->out->error( __( 'Give one or more sections or --all.', 'review-captain' ) );
				return array();
			}
			$unknown = array_diff( $tabs, $known );
			if ( $unknown ) {
				/* translators: %s: tabs */
				$this->out->error( sprintf( __( 'Unknown section: %s', 'review-captain' ), implode( ', ', $unknown ) ) );
				return array();
			}
		}
		if ( empty( $assoc['yes'] ) ) {
			$this->out->error( __( 'Reset only with --yes. Reviews, profiles and history stay unchanged; the previous state is backed up.', 'review-captain' ) );
			return array();
		}
		if ( ! $this->settings_require_writer( empty( $assoc['all'] ) ? $tabs : array() ) ) {
			return array();
		}
		$done = $this->settings_page()->reset_tabs( $tabs );
		$this->plugin->settings()->flush();
		$own = array_diff( $tabs, SettingsPage::resettable_tabs() );
		if ( $own && empty( $assoc['all'] ) ) {
			/* translators: %s: tabs */
			$this->out->warning( sprintf( __( 'Add-on settings only, which are kept on reset: %s', 'review-captain' ), implode( ', ', $own ) ) );
		}
		$skipped = array_diff( $tabs, $done, $own );
		if ( $skipped ) {
			/* translators: %s: tabs */
			$this->out->warning( sprintf( __( 'Skipped without permission: %s', 'review-captain' ), implode( ', ', $skipped ) ) );
		}
		if ( ! $done ) {
			$this->out->error( __( 'Nothing was reset.', 'review-captain' ) );
			return array();
		}
		/* translators: %s: tabs */
		$this->out->success( sprintf( __( 'Reset: %s. The previous state was backed up.', 'review-captain' ), implode( ', ', $done ) ) );
		return $done;
	}

	/**
	 * `settings export [--file=<path>]`.
	 *
	 * @param list<string>          $args  Args.
	 * @param array<string, string> $assoc Assoc args.
	 * @return array<string, mixed>
	 */
	public function settings_export( array $args, array $assoc ): array {
		$doc  = ( new ConfigTransfer( $this->plugin->settings() ) )->export();
		$json = (string) wp_json_encode( $doc, JSON_PRETTY_PRINT );
		$this->plugin->audit()->record( 'settings.exported', 'settings', 'cli', array( 'version' => SettingsSchema::VERSION ) );
		if ( empty( $assoc['file'] ) ) {
			$this->out->log( $json );
			return $doc;
		}
		$file = (string) $assoc['file'];
		if ( false === file_put_contents( $file, $json . "\n" ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			/* translators: %s: file */
			$this->out->error( sprintf( __( 'File %s could not be written.', 'review-captain' ), $file ) );
			return $doc;
		}
		/* translators: %s: file */
		$this->out->success( sprintf( __( 'Configuration exported to %s (without credentials).', 'review-captain' ), $file ) );
		return $doc;
	}

	/**
	 * `settings import <file> [--dry-run] [--yes] [--format=<f>]` – preview first (same as the
	 * settings page), apply only with --yes; a backup is created before applying.
	 *
	 * @param list<string>          $args  Args.
	 * @param array<string, string> $assoc Assoc args.
	 * @return array{diff: list<array<string, mixed>>, changed: list<string>}
	 */
	public function settings_import( array $args, array $assoc ): array {
		$none = array(
			'diff'    => array(),
			'changed' => array(),
		);
		$file = (string) ( $args[0] ?? '' );
		if ( '' === $file || ! is_readable( $file ) || ! is_file( $file ) ) {
			$this->out->error( __( 'Import file not found or not readable.', 'review-captain' ) );
			return $none;
		}
		$size = (int) filesize( $file );
		$json = $size > 0 && $size <= self::IMPORT_MAX_BYTES ? file_get_contents( $file ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$dry  = ! empty( $assoc['dry-run'] );
		if ( ! is_string( $json ) ) {
			$this->out->error( __( 'The file is empty or larger than 200 KB.', 'review-captain' ) );
			return $none;
		}
		if ( ! $dry && empty( $assoc['yes'] ) ) {
			$this->out->error( __( 'Apply the import only with --yes, or show a preview with --dry-run.', 'review-captain' ) );
			return $none;
		}
		if ( ! $this->settings_require_writer( array() ) ) {
			return $none;
		}
		$tabs     = SettingsPage::writable_tabs();
		$transfer = new ConfigTransfer( $this->plugin->settings() );
		$preview  = $transfer->preview( $json, $tabs, SettingsPage::pro_writable() );
		if ( null === $preview ) {
			$this->out->error( __( 'Not a valid Review Captain configuration export, or unsupported version.', 'review-captain' ) );
			return $none;
		}
		if ( SettingsPage::migrate_access( $this->plugin->settings() ) ) {
			$preview = (array) $transfer->preview( $json, $tabs, SettingsPage::pro_writable() );
		}
		foreach ( (array) ( $preview['kept'] ?? array() ) as $kept ) {
			$names = array_map( static fn( $key ): string => $kept['tab'] . '.' . $key, (array) $kept['fields'] );
			/* translators: 1: settings keys, 2: reason */
			$this->out->warning( sprintf( __( 'Not applied, previous values are kept: %1$s. %2$s', 'review-captain' ), implode( ', ', $names ), (string) $kept['message'] ) );
		}
		$rows = array();
		foreach ( $preview['diff'] as $row ) {
			$field  = SettingsSchema::fields()[ $row['tab'] ][ $row['key'] ] ?? array();
			$rows[] = array(
				'key' => $row['tab'] . '.' . $row['key'],
				'old' => ConfigTransfer::display_value( self::settings_output_value( (string) $row['key'], $field, $row['old'] ) ),
				'new' => ConfigTransfer::display_value( self::settings_output_value( (string) $row['key'], $field, $row['new'] ) ),
			);
		}
		if ( ! $rows ) {
			$this->out->success( __( 'The import contains no changes compared to the current settings.', 'review-captain' ) );
			return $none;
		}
		$this->out->table( $rows, array( 'key', 'old', 'new' ), (string) ( $assoc['format'] ?? 'table' ) );
		$skipped = array_diff( array_keys( SettingsSchema::tabs() ), $tabs );
		if ( $skipped ) {
			/* translators: %s: tabs */
			$this->out->warning( sprintf( __( 'Ignored without permission: %s', 'review-captain' ), implode( ', ', $skipped ) ) );
		}
		if ( $dry ) {
			/* translators: %d: number of changes */
			$this->out->success( sprintf( __( 'Dry run: %d changes, nothing saved.', 'review-captain' ), count( $rows ) ) );
			return array(
				'diff'    => $rows,
				'changed' => array(),
			);
		}
		$changed = $this->settings_page()->apply_import( $preview['values'], $preview['tabs'] );
		$this->plugin->settings()->flush();
		/* translators: %d: number of changes */
		$this->out->success( sprintf( __( '%d settings imported. The previous state was backed up.', 'review-captain' ), count( $changed ) ) );
		return array(
			'diff'    => $rows,
			'changed' => $changed,
		);
	}
}
