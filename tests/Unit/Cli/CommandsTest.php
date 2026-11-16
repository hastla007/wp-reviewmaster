<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Cli;

use ReviewCaptain\Cli\CliModule;
use ReviewCaptain\Cli\Commands;
use ReviewCaptain\Cli\Output;
use ReviewCaptain\Install\Schema;
use ReviewCaptain\Jobs\JobQueue;
use ReviewCaptain\Jobs\JobsModule;
use ReviewCaptain\Plugin;
use ReviewCaptain\Support\ErrorCode;
use ReviewCaptain\Tests\TestCase;

final class CommandsTest extends TestCase {

	/** @var object{lines: list<array{0:string,1:string}>, tables: list<array>} */
	private object $out;

	private function commands(): Commands {
		( new JobsModule() )->register( Plugin::instance() );
		update_option( Schema::OPTION_VERSION, Schema::VERSION );
		$this->out = new class() implements Output {
			public array $lines  = array();
			public array $tables = array();
			public function log( string $m ): void { $this->lines[] = array( 'log', $m ); }
			public function success( string $m ): void { $this->lines[] = array( 'success', $m ); }
			public function warning( string $m ): void { $this->lines[] = array( 'warning', $m ); }
			public function error( string $m ): void { throw new \RuntimeException( 'cli-error: ' . $m ); }
			public function table( array $rows, array $fields, string $format = 'table' ): void { $this->tables[] = $rows; }
		};
		return new Commands( Plugin::instance(), $this->out );
	}

	private function queue(): JobQueue {
		/** @var JobQueue */
		return Plugin::instance()->get( 'jobs' );
	}

	public function test_module_does_nothing_without_wp_cli(): void {
		( new CliModule() )->register( Plugin::instance() );
		$this->assertArrayHasKey( 'jobs run', CliModule::definitions() );
		$this->assertArrayHasKey( 'dev seed', CliModule::definitions() );
		foreach ( CliModule::definitions() as $def ) {
			$this->assertTrue( method_exists( Commands::class, $def['method'] ) );
		}
	}

	public function test_jobs_run_list_stats_retry(): void {
		$c  = $this->commands();
		$q  = $this->queue();
		$ok = $q->enqueue( 'dev.noop', array(), 'dev:1' );
		$x  = $q->enqueue( 'unknown.type', array(), 'u:1', 0, 1 );
		$res = $c->jobs_run( array(), array( 'budget' => '5' ) );
		$this->assertSame( 2, $res['processed'] );
		$this->assertSame( 'succeeded', $q->find( $ok )['status'] );

		$c->jobs_list( array(), array( 'status' => 'paused' ) );
		$this->assertSame( $x, $this->out->tables[0][0]['id'] );
		$this->assertSame( ErrorCode::CONTRACT_INCOMPATIBLE, $this->out->tables[0][0]['error'] );

		$c->jobs_stats( array(), array() );
		$this->assertContains( array( 'key' => 'succeeded', 'value' => '1' ), $this->out->tables[1] );

		$c->jobs_retry( array( (string) $x ), array() );
		$this->assertSame( 'queued', $q->find( $x )['status'] );
		$this->expectExceptionMessage( 'cli-error' );
		$c->jobs_retry( array( '99999' ), array() );
	}

	public function test_jobs_list_rejects_unknown_status(): void {
		$c = $this->commands();
		$this->expectExceptionMessage( 'cli-error' );
		$c->jobs_list( array(), array( 'status' => 'bogus' ) );
	}

	public function test_maintenance_reconcile_and_doctor(): void {
		$c = $this->commands();
		$r = $c->maintenance_run( array(), array() );
		$this->assertArrayHasKey( 'retention', $r );
		$this->assertTrue( $r['reconcile']['schema_ok'] );

		$report = $c->reconcile( array(), array( 'fix' => true ) );
		$this->assertTrue( $report['fix'] );

		$checks = $c->doctor( array(), array() );
		$byname = array_column( $checks, null, 'check' );
		$this->assertSame( 'ok', $byname['schema']['status'] );
		$this->assertSame( 'warning', $byname['cron']['status'] );
		$this->assertSame( 'warning', $byname['crypto']['status'] ); // Fallback key in tests.
	}

	public function test_reconcile_errors_on_outdated_schema(): void {
		$c = $this->commands();
		update_option( Schema::OPTION_VERSION, 0 );
		$this->expectExceptionMessage( 'cli-error' );
		$c->reconcile( array(), array() );
	}

	public function test_dev_seed_requires_debug_or_yes_and_inserts_batches(): void {
		$c = $this->commands();
		if ( ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
			try {
				$c->dev_seed( array(), array( 'reviews' => '5' ) );
				$this->fail( 'Expected refusal' );
			} catch ( \RuntimeException $e ) {
				$this->assertStringContainsString( 'cli-error', $e->getMessage() );
			}
		}
		$res = $c->dev_seed( array(), array( 'reviews' => '1203', 'jobs' => '700', 'yes' => true ) );
		$this->assertSame( array( 'reviews' => 1203, 'jobs' => 700 ), $res );
		$this->assertSame( 1203, $this->rows( 'reviews' ) );
		$this->assertSame( 700, $this->rows( 'jobs' ) );
		$reviews = Schema::table( 'reviews' );
		$this->assertGreaterThan( 0, (int) $this->db->get_var( "SELECT COUNT(*) FROM {$reviews} WHERE platform = 'local' AND dedupe_key IS NULL" ) );
		// Seeding again does not collide on unique keys.
		$c->dev_seed( array(), array( 'reviews' => '10', 'jobs' => '10', 'yes' => true ) );
		$this->assertSame( 1213, $this->rows( 'reviews' ) );
		$this->assertSame( 710, $this->rows( 'jobs' ) );
	}
}
