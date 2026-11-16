<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\M7;

use ReviewCaptain\Install\Migrator;
use ReviewCaptain\Install\Schema;
use ReviewCaptain\Tests\TestCase;

/**
 * Schema v2: new columns/tables and an idempotent migration step.
 */
final class SchemaV2Test extends TestCase {

	public function test_version_tables_and_columns(): void {
		$this->assertGreaterThanOrEqual( 2, Schema::VERSION );
		foreach ( array( 'review_tags', 'review_reports', 'review_media', 'questions' ) as $key ) {
			$this->assertContains( $key, Schema::TABLES );
			$this->assertSame( 0, $this->rows( $key ) );
		}
		$def = Schema::definitions()['reviews'];
		$this->assertStringContainsString( 'revision int(10) unsigned NOT NULL DEFAULT 0', $def );
		$this->assertStringContainsString( 'UNIQUE KEY submission_key (submission_key)', $def );
		$this->assertStringContainsString( 'UNIQUE KEY review_tag (review_id,tag)', Schema::definitions()['review_tags'] );
	}

	public function test_migration_step_2_is_registered_and_rerunnable(): void {
		$this->assertArrayHasKey( 2, Migrator::steps() );
		$this->db->insert( Schema::table( 'reviews' ), array( 'uuid' => wp_generate_uuid4(), 'body' => 'x', 'created_at' => gmdate( 'Y-m-d H:i:s' ), 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ) );
		Migrator::run( 1, 2 );
		Migrator::run( 1, 2 );
		$this->assertSame( '0', (string) $this->db->get_var( 'SELECT revision FROM ' . Schema::table( 'reviews' ) ) );
	}
}
