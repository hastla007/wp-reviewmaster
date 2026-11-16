<?php
/**
 * TEC-005: race tests with real parallel PHP processes against MySQL/MariaDB (InnoDB).
 *
 * Driven by tests/e2e/race-mariadb.sh, which starts several worker processes at the same moment.
 * Every mode uses the real Core code (JobQueue, ReviewRepository); nothing is mocked.
 *
 *   php race-mariadb.php <wp-load.php> setup
 *   php race-mariadb.php <wp-load.php> claim   <worker-id> <start-at>
 *   php race-mariadb.php <wp-load.php> enqueue <worker-id> <start-at>
 *   php race-mariadb.php <wp-load.php> dedupe  <worker-id> <start-at>
 *   php race-mariadb.php <wp-load.php> cas     <worker-id> <start-at>
 *   php race-mariadb.php <wp-load.php> verify
 *
 * Only for a disposable test site: setup deletes all jobs and the race test reviews.
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

if ( PHP_SAPI !== 'cli' || $argc < 3 ) {
	fwrite( STDERR, "usage: php race-mariadb.php <wp-load.php> <mode> [worker] [start-at]\n" );
	exit( 2 );
}
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
// No WP-Cron spawns from the worker processes: a real Runner would pause the test job types (no handler).
define( 'DISABLE_WP_CRON', true );
require $argv[1];

use ReviewCaptain\Domain\Origin;
use ReviewCaptain\Domain\Platform;
use ReviewCaptain\Domain\Review;
use ReviewCaptain\Domain\ReviewStatus;
use ReviewCaptain\Install\Schema;
use ReviewCaptain\Jobs\JobQueue;
use ReviewCaptain\Plugin;

const RC_RACE_JOBS     = 2000;
const RC_RACE_KEYS     = 200;
const RC_RACE_EXTERNAL = 'race-ext-';
const RC_RACE_OPTION   = 'review_captain_race_cas_review';

$mode   = $argv[2];
$worker = $argv[3] ?? 'w0';
$start  = (float) ( $argv[4] ?? 0 );

/** Wait for the common start moment so all processes hit the DB together. */
function rc_race_wait( float $start ): void {
	while ( microtime( true ) < $start ) {
		usleep( 200 );
	}
}

/** Append one line to the worker's result file. */
function rc_race_log( string $worker, string $line ): void {
	$dir = getenv( 'RC_RACE_DIR' ) ? getenv( 'RC_RACE_DIR' ) : sys_get_temp_dir();
	file_put_contents( $dir . '/race-' . $worker . '.log', $line . "\n", FILE_APPEND );
}

global $wpdb;
$jobs    = Schema::table( 'jobs' );
$reviews = Schema::table( 'reviews' );
$queue   = new JobQueue();

switch ( $mode ) {
	case 'setup':
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "DELETE FROM {$jobs}" );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$reviews} WHERE external_id LIKE %s", RC_RACE_EXTERNAL . '%' ) );
		// phpcs:enable
		for ( $i = 0; $i < RC_RACE_JOBS; $i++ ) {
			$queue->enqueue( 'race.noop', array( 'n' => $i ), 'race-claim-' . $i );
		}
		$r                  = new Review();
		$r->rating_value    = 4.0;
		$r->body            = 'CAS race target';
		$r->author_name     = 'Race';
		$r->status          = ReviewStatus::PENDING;
		$r->consent_publish = true;
		$r                  = Plugin::instance()->reviews()->insert( $r );
		update_option( RC_RACE_OPTION, $r->id, false );
		echo 'setup: ', RC_RACE_JOBS, " jobs, CAS review #{$r->id}\n";
		break;

	case 'claim':
		// Claim in small batches until the queue is empty; log every claimed job ID.
		rc_race_wait( $start );
		$idle = 0;
		while ( $idle < 3 ) {
			$claimed = $queue->claim( 'race-' . $worker, 300, 5 );
			if ( ! $claimed ) {
				++$idle;
				usleep( 20000 );
				continue;
			}
			$idle = 0;
			foreach ( $claimed as $job ) {
				rc_race_log( $worker, 'claim ' . (int) $job['id'] );
				$queue->complete( (int) $job['id'], 'race-' . $worker );
			}
		}
		break;

	case 'enqueue':
		// All workers enqueue the same RC_RACE_KEYS idempotency keys at once.
		rc_race_wait( $start );
		for ( $i = 0; $i < RC_RACE_KEYS; $i++ ) {
			$id = $queue->enqueue( 'race.enqueue', array( 'w' => $worker ), 'race-enqueue-' . $i );
			rc_race_log( $worker, 'enqueue ' . $i . ' ' . (int) $id );
		}
		break;

	case 'dedupe':
		// All workers upsert the same RC_RACE_KEYS external reviews at once (provider sync / import).
		rc_race_wait( $start );
		for ( $i = 0; $i < RC_RACE_KEYS; $i++ ) {
			$r               = new Review();
			$r->platform     = Platform::GOOGLE;
			$r->origin       = Origin::PROVIDER_SYNC;
			$r->external_id  = RC_RACE_EXTERNAL . $i;
			$r->rating_value = 5.0;
			$r->body         = 'Race ' . $i . ' from ' . $worker;
			$r->source_url   = 'https://example.org/review/' . $i;
			try {
				$res = Plugin::instance()->reviews()->upsert_external( $r );
				rc_race_log( $worker, 'dedupe ' . $i . ' ' . ( $res['created'] ? 'created' : 'updated' ) . ' ' . (int) $res['review']->id );
			} catch ( \Throwable $e ) {
				rc_race_log( $worker, 'dedupe ' . $i . ' error ' . get_class( $e ) . ' ' . substr( $e->getMessage(), 0, 80 ) );
			}
		}
		break;

	case 'cas':
		// All workers load the same revision and try one CAS update each; exactly one may win.
		$id     = (int) get_option( RC_RACE_OPTION );
		$review = Plugin::instance()->reviews()->find( $id );
		rc_race_wait( $start );
		$review->body = 'Edited by ' . $worker;
		$ok           = Plugin::instance()->reviews()->update( $review, false, true );
		rc_race_log( $worker, 'cas ' . ( $ok ? 'won' : 'lost' ) );
		break;

	case 'verify':
		$dir   = getenv( 'RC_RACE_DIR' ) ? getenv( 'RC_RACE_DIR' ) : sys_get_temp_dir();
		$lines = array();
		foreach ( glob( $dir . '/race-*.log' ) as $file ) {
			$lines = array_merge( $lines, file( $file, FILE_IGNORE_NEW_LINES ) );
		}
		$fail = 0;
		$out  = static function ( bool $ok, string $label ) use ( &$fail ): void {
			echo ( $ok ? 'PASS ' : 'FAIL ' ), $label, "\n";
			$fail += $ok ? 0 : 1;
		};

		// Claim: every job claimed exactly once, all succeeded, one attempt each.
		$claims = array_count_values( array_map( static fn( $l ) => (int) substr( $l, 6 ), preg_grep( '/^claim /', $lines ) ) );
		$out( RC_RACE_JOBS === count( $claims ), 'claim: ' . count( $claims ) . ' distinct jobs claimed (expected ' . RC_RACE_JOBS . ')' );
		$out( 1 === max( $claims ? $claims : array( 0 ) ), 'claim: no job claimed twice (max ' . max( $claims ? $claims : array( 0 ) ) . ')' );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$bad = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$jobs} WHERE type = 'race.noop' AND ( status <> 'succeeded' OR attempts <> 1 )" );
		$out( 0 === $bad, "claim: all jobs succeeded with exactly one attempt ({$bad} deviations)" );

		// Enqueue: one row per key, and every worker got the same ID for a key.
		$rows = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$jobs} WHERE type = 'race.enqueue'" );
		$out( RC_RACE_KEYS === $rows, "enqueue: {$rows} rows for " . RC_RACE_KEYS . ' keys' );
		$ids = array();
		foreach ( preg_grep( '/^enqueue /', $lines ) as $l ) {
			[ , $k, $id ]       = explode( ' ', $l );
			$ids[ $k ][ $id ] = true;
		}
		$split = count( array_filter( $ids, static fn( $v ) => count( $v ) !== 1 || isset( $v[0] ) ) );
		$out( 0 === $split, "enqueue: every worker received the winner's ID ({$split} keys with differing IDs or false)" );

		// Dedupe: one review per external ID; no worker saw an exception.
		$rows = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$reviews} WHERE external_id LIKE %s", RC_RACE_EXTERNAL . '%' ) );
		$out( RC_RACE_KEYS === $rows, "dedupe: {$rows} reviews for " . RC_RACE_KEYS . ' external IDs' );
		$errors = count( preg_grep( '/^dedupe \d+ error/', $lines ) );
		$out( 0 === $errors, "dedupe: {$errors} exceptions in workers" );
		$created = count( preg_grep( '/^dedupe \d+ created/', $lines ) );
		$out( RC_RACE_KEYS === $created, "dedupe: {$created} 'created' results (expected exactly one per key)" );
		// phpcs:enable

		// CAS: exactly one winner.
		$won = count( preg_grep( '/^cas won/', $lines ) );
		$out( 1 === $won, "cas: {$won} winner(s) of " . count( preg_grep( '/^cas /', $lines ) ) . ' concurrent updates' );

		echo 0 === $fail ? "ALL PASS\n" : "{$fail} FAILED\n";
		exit( 0 === $fail ? 0 : 1 );

	default:
		fwrite( STDERR, "unknown mode {$mode}\n" );
		exit( 2 );
}
