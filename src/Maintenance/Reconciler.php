<?php
/**
 * Upgrade/restore reconciliation (TEC-015, docs/architecture/reconciliation.md).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Maintenance;

use ReviewCaptain\Install\Schema;
use ReviewCaptain\Jobs\JobQueue;
use ReviewCaptain\Jobs\Runner;
use ReviewCaptain\Plugin;
use ReviewCaptain\Repository\ReviewRepository;

/**
 * Core part of the reconciliation: schema, leases, overdue side-effect jobs, integrity counters,
 * instance fingerprint (clone/restore detection) and the side-effect lock. Credential, cursor and
 * privacy steps belong to Pro/Privacy and hook in via `review_captain_reconcile_report`.
 */
class Reconciler {

	public const MODES = array( 'upgrade', 'reactivate', 'restore', 'clone', 'manual' );

	/**
	 * Constructor.
	 *
	 * @param Plugin   $plugin Plugin.
	 * @param JobQueue $queue  Queue.
	 */
	public function __construct( private Plugin $plugin, private JobQueue $queue ) {}

	/**
	 * Run the checks.
	 *
	 * @param bool   $fix  Apply fixes (false = dry run, no writes).
	 * @param string $mode upgrade|reactivate|restore|clone|manual.
	 * @return array<string, mixed> Report.
	 */
	public function run( bool $fix, string $mode = 'manual' ): array {
		global $wpdb;
		$mode   = in_array( $mode, self::MODES, true ) ? $mode : 'manual';
		$jobs   = $this->queue->table();
		$now    = gmdate( 'Y-m-d H:i:s', $this->queue->time() );
		$report = array(
			'mode'                      => $mode,
			'fix'                       => $fix,
			'schema_installed'          => (int) get_option( Schema::OPTION_VERSION, 0 ),
			'schema_expected'           => Schema::VERSION,
			'schema_ok'                 => false,
			'missing_tables'            => array(),
			'expired_leases'            => 0,
			'running_without_lease'     => 0,
			'side_effect_jobs_overdue'  => 0,
			'refresh_overdue'           => 0,
			'missing_dedupe_keys'       => 0,
			'stale_imports'             => 0,
			'orphan_rows'               => 0,
			'clone_suspected'           => false,
			'restore_suspected'         => false,
			'lock'                      => (string) get_option( Runner::OPTION_LOCK, '' ),
			'fixed'                     => array(),
		);

		// 1. Schema.
		foreach ( Schema::TABLES as $key ) {
			if ( ! $this->table_exists( Schema::table( $key ) ) ) {
				$report['missing_tables'][] = $key;
			}
		}
		$report['schema_ok'] = Schema::VERSION === $report['schema_installed'] && array() === $report['missing_tables'];
		if ( ! $report['schema_ok'] ) {
			// Migration required first (wp review-captain schema upgrade); nothing else is touched.
			return $report;
		}

		// Instance fingerprint (clone / restore detection).
		$instance  = get_option( Runner::OPTION_INSTANCE, array() );
		$instance  = is_array( $instance ) ? $instance : array();
		$home_hash = hash( 'sha256', (string) home_url() );
		if ( isset( $instance['home_url_hash'] ) && ! hash_equals( (string) $instance['home_url_hash'], $home_hash ) ) {
			$report['clone_suspected'] = true;
		}
		if ( isset( $instance['last_seen_at'] ) && (int) $instance['last_seen_at'] < time() - DAY_IN_SECONDS ) {
			$report['restore_suspected'] = true;
		}
		if ( 'manual' === $mode && $report['clone_suspected'] ) {
			$mode           = 'clone';
			$report['mode'] = $mode;
		} elseif ( 'manual' === $mode && $report['restore_suspected'] ) {
			$mode           = 'restore';
			$report['mode'] = $mode;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		// 2. Leases.
		$report['expired_leases']        = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$jobs} WHERE status = 'running' AND lease_expires_at IS NOT NULL AND lease_owner IS NOT NULL AND lease_expires_at < %s", $now ) );
		$report['running_without_lease'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$jobs} WHERE status = 'running' AND (lease_expires_at IS NULL OR lease_owner IS NULL)" );
		if ( $fix ) {
			$where = in_array( $mode, array( 'restore', 'clone' ), true )
				? "status = 'running' AND type <> 'reconcile'" // All leases predate the restore – except this very reconciliation.
				: $wpdb->prepare( "status = 'running' AND (lease_expires_at IS NULL OR lease_owner IS NULL OR lease_expires_at < %s)", $now );
			$reset = (int) $wpdb->query( $wpdb->prepare( "UPDATE {$jobs} SET status = 'queued', lease_owner = NULL, lease_expires_at = NULL, available_at = %s, updated_at = %s WHERE {$where}", $now, $now ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$report['fixed']['leases_reset'] = max( 0, $reset );
		}

		// 3. Overdue queued jobs with external side effects (> 24 h).
		$overdue_ids = array();
		// A job deferred by the reconcile lock keeps its original due time in the payload.
		$cutoff = gmdate( 'Y-m-d H:i:s', $this->queue->time() - DAY_IN_SECONDS );
		$rows   = $wpdb->get_results( "SELECT id, type, available_at, payload FROM {$jobs} WHERE status = 'queued' ORDER BY id ASC LIMIT 1000", ARRAY_A );
		foreach ( (array) $rows as $row ) {
			if ( Runner::is_side_effect_free( (string) $row['type'] ) ) {
				continue;
			}
			$payload = json_decode( (string) $row['payload'], true );
			$due     = is_array( $payload ) && is_string( $payload[ Runner::LOCK_DUE_KEY ] ?? null ) ? $payload[ Runner::LOCK_DUE_KEY ] : (string) $row['available_at'];
			if ( $due < $cutoff ) {
				$overdue_ids[] = (int) $row['id'];
			}
		}
		$report['side_effect_jobs_overdue'] = count( $overdue_ids );
		if ( $fix && array() !== $overdue_ids && in_array( $mode, array( 'restore', 'clone' ), true ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $overdue_ids ), '%d' ) );
			$params       = array_merge( array( __( 'Nach Wiederherstellung erneut prüfen.', 'review-captain' ), $now ), $overdue_ids );
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$paused                                    = (int) $wpdb->query( $wpdb->prepare( "UPDATE {$jobs} SET status = 'paused', last_error_code = 'rc.source.unavailable', last_error_message = %s, updated_at = %s WHERE status = 'queued' AND id IN ({$placeholders})", $params ) );
			$report['fixed']['side_effect_jobs_paused'] = max( 0, $paused );
		}

		// 6. Snapshots due for refresh (report only; hiding is done by retention/refresh gate).
		$reviews                   = Schema::table( 'reviews' );
		$report['refresh_overdue'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$reviews} WHERE refresh_due_at IS NOT NULL AND refresh_due_at < %s AND status <> 'removed'", $now ) );

		// 8. Integrity.
		$report['missing_dedupe_keys'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$reviews} WHERE external_id IS NOT NULL AND external_id <> '' AND dedupe_key IS NULL" );
		// Rows of deleted reviews (a moderation write racing a delete, TEC-005): history, tags,
		// reports and photos without their review.
		$orphans = array();
		foreach ( array( 'review_events', 'review_tags', 'review_reports', 'review_media' ) as $key ) {
			$child = Schema::table( $key );
			foreach ( (array) $wpdb->get_col( "SELECT DISTINCT c.review_id FROM {$child} c LEFT JOIN {$reviews} r ON r.id = c.review_id WHERE r.id IS NULL LIMIT 500" ) as $orphan_id ) {
				$orphans[ (int) $orphan_id ] = true;
			}
		}
		$report['orphan_rows'] = count( $orphans );
		if ( $fix && $orphans ) {
			foreach ( array_keys( $orphans ) as $orphan_id ) {
				ReviewRepository::purge_related( $orphan_id );
			}
			$report['fixed']['orphans_removed'] = count( $orphans );
		}
		$imports                       = Schema::table( 'import_batches' );
		$report['stale_imports']       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$imports} WHERE status = 'importing'" );
		if ( $fix && $report['stale_imports'] > 0 ) {
			$active = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$jobs} WHERE type LIKE 'import.%' AND status IN ('queued','running')" );
			if ( 0 === $active ) {
				$report['fixed']['imports_failed'] = max( 0, (int) $wpdb->query( "UPDATE {$imports} SET status = 'failed' WHERE status = 'importing'" ) );
			}
		}
		// phpcs:enable

		/**
		 * Extend the reconciliation (credentials, cursors, privacy re-application …).
		 *
		 * @param array  $report Report.
		 * @param bool   $fix    Whether fixes may be applied.
		 * @param string $mode   Mode.
		 */
		$report = (array) apply_filters( 'review_captain_reconcile_report', $report, $fix, $mode );

		// 9. Lock and completion.
		if ( $fix ) {
			if ( 'clone' === $mode ) {
				update_option( Runner::OPTION_LOCK, 'clone', false ); // Needs explicit confirmation.
			} else {
				delete_option( Runner::OPTION_LOCK );
			}
			$instance['last_seen_at'] = time();
			if ( ! isset( $instance['home_url_hash'] ) ) {
				$instance['home_url_hash'] = $home_hash;
			}
			update_option( Runner::OPTION_INSTANCE, $instance, false );
			$report['lock'] = (string) get_option( Runner::OPTION_LOCK, '' );
			$this->plugin->audit()->record(
				'reconcile.completed',
				'reconcile',
				'',
				array(
					'mode'  => $mode,
					'count' => array_sum( array_map( 'intval', $report['fixed'] ) ),
				)
			);
		}
		return $report;
	}

	/**
	 * Operator confirmation after a clone/move (nonce/capability checked by the caller).
	 *
	 * @param bool $is_copy True = this is a copy: queued/paused jobs with side effects are cancelled.
	 * @return int Jobs taken out of the queue.
	 */
	public function confirm_instance( bool $is_copy ): int {
		global $wpdb;
		$affected = 0;
		if ( $is_copy ) {
			$jobs = $this->queue->table();
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
			$rows = $wpdb->get_results( "SELECT id, type FROM {$jobs} WHERE status IN ('queued','paused','running')", ARRAY_A );
			foreach ( (array) $rows as $row ) {
				if ( ! Runner::is_side_effect_free( (string) $row['type'] ) && $this->queue->cancel( (int) $row['id'] ) ) {
					++$affected;
				}
			}
		}
		$instance                  = get_option( Runner::OPTION_INSTANCE, array() );
		$instance                  = is_array( $instance ) ? $instance : array();
		$instance['home_url_hash'] = hash( 'sha256', (string) home_url() );
		$instance['last_seen_at']  = time();
		update_option( Runner::OPTION_INSTANCE, $instance, false );
		delete_option( Runner::OPTION_LOCK );
		/**
		 * Fires after the operator confirmed the instance identity (Pro pauses API profiles on copies).
		 *
		 * @param bool $is_copy Copy.
		 */
		do_action( 'review_captain_instance_confirmed', $is_copy );
		return $affected;
	}

	/**
	 * Table existence check that works on MySQL and in tests.
	 *
	 * @param string $table Table.
	 */
	private function table_exists( string $table ): bool {
		global $wpdb;
		$suppress = method_exists( $wpdb, 'suppress_errors' ) ? $wpdb->suppress_errors( true ) : null;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE 1 = 0" );
		$ok     = null !== $result;
		if ( null !== $suppress ) {
			$wpdb->suppress_errors( $suppress );
		}
		return $ok;
	}
}
