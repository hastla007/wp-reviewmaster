<?php
/**
 * WP-CLI commands `wp review-captain …` (RC-036, TEC-014). Thin layer over services.
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Cli;

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
use ReviewCaptain\Security\Crypto;
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
				__( '%1$d Jobs verarbeitet, %2$d erfolgreich, %3$d fehlgeschlagen.', 'review-captain' ),
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
			$this->out->error( __( 'Unbekannter Status.', 'review-captain' ) );
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
			$this->out->error( __( 'Job nicht gefunden oder nicht wiederholbar.', 'review-captain' ) );
			return;
		}
		$this->out->success( __( 'Job erneut eingereiht.', 'review-captain' ) );
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
				__( 'Aufbewahrung: %1$d abgelehnte Reviews, %2$d externe Snapshots, %3$d Logeinträge, %4$d Jobs gelöscht.', 'review-captain' ),
				$r['rejected_deleted'],
				$r['external_deleted'],
				$r['logs_deleted'],
				$r['jobs_deleted']
			)
		);
		if ( empty( $r['complete'] ) ) {
			$this->out->warning( __( 'Nicht alle fälligen Daten wurden in diesem Lauf bereinigt; der nächste Lauf setzt fort.', 'review-captain' ) );
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
			$this->out->error( __( 'Schema veraltet oder unvollständig: zuerst „wp review-captain schema upgrade“ ausführen.', 'review-captain' ) );
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
				__( 'Schema-Version %1$d (erwartet %2$d).', 'review-captain' ),
				$installed,
				Schema::VERSION
			),
		);

		$scheduled = false !== wp_next_scheduled( JobsModule::HOOK_RUN )
			|| ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( JobsModule::HOOK_RUN, array(), JobsModule::AS_GROUP ) );
		$checks[]  = array(
			'check'   => 'cron',
			'status'  => $scheduled ? 'ok' : 'warning',
			'message' => $scheduled ? __( 'Job-Takt ist eingeplant.', 'review-captain' ) : __( 'Job-Takt ist nicht eingeplant (Adminbereich einmal aufrufen oder Plugin reaktivieren).', 'review-captain' ),
		);
		$health   = Diagnostics::runner_health();
		$checks[] = array(
			'check'   => 'runner',
			'status'  => 'ok' === $health['status'] ? 'ok' : 'warning',
			'message' => Diagnostics::wp_cron_disabled()
				? __( 'DISABLE_WP_CRON gesetzt – System-Cron muss „wp review-captain jobs run“ ausführen.', 'review-captain' )
				: __( 'WP-Cron aktiv: Ausführung nur bei Seitenaufrufen, Zeiten ungenau. System-Cron empfohlen.', 'review-captain' ),
		);

		$crypto   = new Crypto();
		$status   = $crypto->status();
		$checks[] = array(
			'check'   => 'crypto',
			'status'  => Crypto::STATUS_EXPLICIT === $status ? 'ok' : ( Crypto::STATUS_FALLBACK === $status ? 'warning' : 'error' ),
			'message' => match ( $status ) {
				Crypto::STATUS_EXPLICIT => __( 'Eigener Schlüssel REVIEW_CAPTAIN_ENCRYPTION_KEY ist gesetzt.', 'review-captain' ),
				Crypto::STATUS_FALLBACK => __( 'Zugangsdaten werden mit einem aus den WordPress-Salts abgeleiteten Schlüssel verschlüsselt. Empfehlung: REVIEW_CAPTAIN_ENCRYPTION_KEY setzen.', 'review-captain' ),
				Crypto::STATUS_INVALID  => __( 'REVIEW_CAPTAIN_ENCRYPTION_KEY ist ungültig (Base64 von genau 32 Byte erwartet).', 'review-captain' ),
				default                 => __( 'libsodium ist nicht verfügbar.', 'review-captain' ),
			},
		);

		// MINVER-1: an older database server is a warning, never a block.
		$db       = Requirements::current_db();
		$checks[] = array(
			'check'   => 'database',
			'status'  => true === $db['ok'] ? 'ok' : 'warning',
			'message' => null === $db['ok']
				? __( 'Datenbankversion nicht ermittelbar.', 'review-captain' )
				: sprintf(
					/* translators: 1: vendor, 2: installed version, 3: minimum version */
					__( 'Datenbank %1$s %2$s (empfohlen mindestens %3$s).', 'review-captain' ),
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
			$this->out->error( __( 'Testdaten nur mit WP_DEBUG oder --yes erzeugen.', 'review-captain' ) );
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
				__( '%1$d Test-Reviews und %2$d Test-Jobs angelegt.', 'review-captain' ),
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
				__( 'Schema ist auf Version %d.', 'review-captain' ),
				(int) get_option( Schema::OPTION_VERSION, 0 )
			)
		);
	}
}
