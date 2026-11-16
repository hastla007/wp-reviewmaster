<?php
/**
 * Versioned database schema (RC-035, docs/m1/datenmodell.md).
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Database;

defined( 'ABSPATH' ) || exit;

final class Schema {

	public const VERSION_OPTION = 'review_captain_db_version';

	/** Core-owned tables without prefix. */
	public const TABLES = array( 'rc_reviews', 'rc_review_log', 'rc_profiles', 'rc_widgets', 'rc_testimonials', 'rc_jobs', 'rc_import_batches', 'rc_audit_log' );

	public static function table( string $name ): string {
		global $wpdb;
		return $wpdb->prefix . $name;
	}

	public static function installed_version(): int {
		return (int) get_option( self::VERSION_OPTION, 0 );
	}

	public static function needs_upgrade(): bool {
		return self::installed_version() < REVIEW_CAPTAIN_DB_VERSION;
	}

	/**
	 * Create or update all tables. Idempotent (dbDelta).
	 */
	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		foreach ( self::definitions() as $table => $columns ) {
			$name = self::table( $table );
			dbDelta( "CREATE TABLE {$name} (\n{$columns}\n) {$charset};" );
		}

		update_option( self::VERSION_OPTION, REVIEW_CAPTAIN_DB_VERSION, false );
	}

	public static function drop_all(): void {
		global $wpdb;
		foreach ( self::TABLES as $table ) {
			$wpdb->query( 'DROP TABLE IF EXISTS ' . self::table( $table ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange
		}
		delete_option( self::VERSION_OPTION );
	}

	/**
	 * Table definitions in dbDelta format (two spaces before PRIMARY KEY, one index per line).
	 *
	 * @return array<string,string>
	 */
	public static function definitions(): array {
		return array(
			'rc_reviews'        => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  uuid char(36) NOT NULL,
  origin varchar(20) NOT NULL DEFAULT 'local',
  provider varchar(32) NOT NULL DEFAULT 'site',
  profile_id bigint(20) unsigned NULL,
  external_id varchar(191) NULL,
  external_key char(64) NULL,
  source_entry_key char(64) NULL,
  type varchar(20) NOT NULL DEFAULT 'general',
  rating_value decimal(5,2) NULL,
  rating_min tinyint(3) unsigned NOT NULL DEFAULT 1,
  rating_max tinyint(3) unsigned NOT NULL DEFAULT 5,
  title varchar(255) NULL,
  content longtext NULL,
  language varchar(20) NULL,
  author_name varchar(191) NULL,
  author_avatar_url varchar(2048) NULL,
  author_user_id bigint(20) unsigned NULL,
  author_email_hash char(64) NULL,
  context_type varchar(32) NULL,
  context_id varchar(64) NULL,
  source_url varchar(2048) NULL,
  attribution varchar(255) NULL,
  reply_text longtext NULL,
  reply_at datetime NULL,
  status varchar(20) NOT NULL DEFAULT 'pending',
  verification varchar(20) NOT NULL DEFAULT 'unverified',
  verification_ref varchar(191) NULL,
  reported_count int(10) unsigned NOT NULL DEFAULT 0,
  published_at datetime NULL,
  source_updated_at datetime NULL,
  fetched_at datetime NULL,
  tombstoned_at datetime NULL,
  import_batch_id bigint(20) unsigned NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  meta longtext NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uuid (uuid),
  UNIQUE KEY external_key (external_key),
  UNIQUE KEY source_entry_key (source_entry_key),
  KEY status_published (status,published_at),
  KEY provider_profile (provider,profile_id),
  KEY rating (rating_value),
  KEY context (context_type,context_id),
  KEY batch (import_batch_id),
  KEY email_hash (author_email_hash),
  KEY tombstone (tombstoned_at)",

			'rc_review_log'     => 'id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  review_id bigint(20) unsigned NOT NULL,
  action varchar(20) NOT NULL,
  reason varchar(255) NULL,
  user_id bigint(20) unsigned NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY review_time (review_id,created_at)',

			'rc_profiles'       => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  provider varchar(32) NOT NULL,
  label varchar(191) NOT NULL,
  public_url varchar(2048) NULL,
  external_ref varchar(191) NOT NULL,
  locale varchar(20) NULL,
  connection_ref varchar(64) NULL,
  status varchar(20) NOT NULL DEFAULT 'active',
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY provider_ref (provider,external_ref)",

			'rc_widgets'        => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  uuid char(36) NOT NULL,
  title varchar(191) NOT NULL,
  layout varchar(32) NOT NULL DEFAULT 'grid',
  config longtext NULL,
  status varchar(20) NOT NULL DEFAULT 'active',
  schema_version smallint(5) unsigned NOT NULL DEFAULT 1,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uuid (uuid)",

			'rc_testimonials'   => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  uuid char(36) NOT NULL,
  kind varchar(10) NOT NULL DEFAULT 'text',
  quote longtext NULL,
  display_name varchar(191) NULL,
  display_name_mode varchar(20) NOT NULL DEFAULT 'full',
  role_label varchar(191) NULL,
  attachment_id bigint(20) unsigned NULL,
  poster_attachment_id bigint(20) unsigned NULL,
  external_embed_url varchar(2048) NULL,
  transcript longtext NULL,
  captions_attachment_id bigint(20) unsigned NULL,
  rating_value tinyint(3) unsigned NULL,
  context_type varchar(32) NULL,
  context_id varchar(64) NULL,
  language varchar(20) NULL,
  consent longtext NULL,
  valid_until datetime NULL,
  revoked_at datetime NULL,
  status varchar(20) NOT NULL DEFAULT 'draft',
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uuid (uuid),
  KEY status (status)",

			'rc_jobs'           => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  type varchar(64) NOT NULL,
  unique_key char(64) NULL,
  payload longtext NULL,
  status varchar(20) NOT NULL DEFAULT 'queued',
  priority smallint(6) NOT NULL DEFAULT 10,
  attempts smallint(5) unsigned NOT NULL DEFAULT 0,
  max_attempts smallint(5) unsigned NOT NULL DEFAULT 6,
  available_at datetime NOT NULL,
  claimed_by char(32) NULL,
  lease_until datetime NULL,
  last_error_code varchar(64) NULL,
  last_error_message varchar(255) NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY unique_key (unique_key),
  KEY due (status,available_at,priority),
  KEY lease (lease_until),
  KEY claimed (claimed_by)",

			'rc_import_batches' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  uuid char(36) NOT NULL,
  filename varchar(191) NULL,
  rows_total int(10) unsigned NOT NULL DEFAULT 0,
  rows_imported int(10) unsigned NOT NULL DEFAULT 0,
  rows_skipped int(10) unsigned NOT NULL DEFAULT 0,
  status varchar(20) NOT NULL DEFAULT 'imported',
  user_id bigint(20) unsigned NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uuid (uuid)",

			'rc_audit_log'      => 'id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  event varchar(64) NOT NULL,
  object_type varchar(32) NULL,
  object_id varchar(64) NULL,
  user_id bigint(20) unsigned NULL,
  context longtext NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY created (created_at),
  KEY object (object_type,object_id)',
		);
	}
}
