<?php
/**
 * Scale measurement (TEC-014). Run with: vendor/bin/phpunit --testsuite perf
 *
 * @package ReviewCaptain
 */

use ReviewCaptain\Database\Schema;
use ReviewCaptain\ImportExport\Csv;
use ReviewCaptain\Jobs\Queue;
use ReviewCaptain\Reviews\ReviewRepository;

class ScaleTest extends WP_UnitTestCase {

	private static array $results = array();

	public static function wpTearDownAfterClass(): void {
		global $wpdb;
		foreach ( array( 'rc_reviews', 'rc_jobs', 'rc_review_log' ) as $t ) {
			$wpdb->query( 'TRUNCATE ' . Schema::table( $t ) ); // phpcs:ignore
		}
	}

	public static function wpSetUpBeforeClass(): void {
		global $wpdb;
		self::wpTearDownAfterClass();
		$table     = Schema::table( 'rc_reviews' );
		$providers = array( 'site', 'google', 'trustpilot', 'apple_app_store', 'google_play' );
		$now       = gmdate( 'Y-m-d H:i:s' );
		$t         = microtime( true );
		for ( $i = 0; $i < 100000; $i += 1000 ) {
			$values = array();
			for ( $j = $i; $j < $i + 1000; $j++ ) {
				$p        = $providers[ $j % 5 ];
				$values[] = $wpdb->prepare( '(%s,%s,%s,%s,%s,%d,%s,%s,%s,%s,%s,%s,%s)', wp_generate_uuid4(), 'site' === $p ? 'local' : 'manual', $p, 'general', 'Seed ' . $j, ( $j % 5 ) + 1, 'Seed text number ' . $j . ' lorem ipsum dolor', 'User ' . ( $j % 977 ), 0 === $j % 7 ? 'pending' : 'approved', 'unverified', gmdate( 'Y-m-d H:i:s', time() - ( $j * 600 ) ), $now, $now );
			}
			$wpdb->query( "INSERT INTO {$table} (uuid, origin, provider, type, title, rating_value, content, author_name, status, verification, published_at, created_at, updated_at) VALUES " . implode( ',', $values ) ); // phpcs:ignore
		}
		self::$results['seed_100k_reviews_s'] = round( microtime( true ) - $t, 2 );
		$t = microtime( true );
		for ( $k = 0; $k < 10000; $k++ ) {
			Queue::enqueue( 'perf.noop', array( 'n' => $k ) );
		}
		self::$results['seed_10k_jobs_s'] = round( microtime( true ) - $t, 2 );
	}

	public static function tear_down_after_class(): void {
		fwrite( STDERR, "\nTEC-014 results: " . wp_json_encode( self::$results, JSON_PRETTY_PRINT ) . "\n" ); // phpcs:ignore
		parent::tear_down_after_class();
	}

	private function time( string $label, callable $cb ) {
		$t                         = microtime( true );
		$r                         = $cb();
		self::$results[ $label ] = round( ( microtime( true ) - $t ) * 1000, 1 ) . ' ms';
		return $r;
	}

	public function test_admin_list_queries(): void {
		$this->assertSame( 100000, ReviewRepository::count() );
		$this->time( 'list_page_1_ms', fn() => ReviewRepository::query( array( 'status' => 'approved' ), 20, 0 ) );
		$this->time( 'list_page_500_ms', fn() => ReviewRepository::query( array( 'status' => 'approved' ), 20, 9980 ) );
		$this->time( 'filter_provider_rating_ms', fn() => ReviewRepository::query( array( 'provider' => 'google', 'rating_min' => 4 ), 20, 0 ) );
		$this->time( 'count_filtered_ms', fn() => ReviewRepository::count( array( 'status' => 'approved', 'provider' => 'google' ) ) );
		$this->time( 'search_ms', fn() => ReviewRepository::query( array( 'search' => 'number 4711' ), 20, 0 ) );
		$this->time( 'summary_by_provider_ms', fn() => ReviewRepository::summary_by_provider() );
		$this->time( 'public_widget_query_ms', fn() => ReviewRepository::query( array( 'public' => true, 'provider' => array( 'site', 'google' ) ), 6, 0 ) );
		$this->assertTrue( true );
	}

	public function test_csv_export_memory(): void {
		$before = memory_get_peak_usage( true );
		$out    = fopen( 'php://temp', 'w+' ); // phpcs:ignore
		$n      = $this->time( 'csv_export_100k_ms', fn() => Csv::export( $out, array() ) );
		self::$results['csv_export_peak_mem_mb'] = round( ( memory_get_peak_usage( true ) - $before ) / 1048576, 1 );
		$this->assertSame( 100000, $n );
	}

	public function test_job_claim_with_10k_jobs(): void {
		$jobs = $this->time( 'claim_10_of_10k_ms', fn() => Queue::claim( str_repeat( 'p', 32 ), 10 ) );
		$this->assertCount( 10, $jobs );
		$this->time( 'queue_stats_ms', fn() => Queue::stats() );
	}
}
