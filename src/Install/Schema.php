<?php
/**
 * Versioned database schema (RC-035, TEC-004).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Install;

/**
 * Table names and dbDelta definitions.
 */
final class Schema {

	/**
	 * Bump on every schema change and add a migration step in Migrator.
	 */
	public const VERSION = 2;

	public const OPTION_VERSION = 'review_captain_db_version';

	/**
	 * Logical table keys.
	 */
	public const TABLES = array( 'reviews', 'review_events', 'profiles', 'jobs', 'import_batches', 'audit_log', 'review_tags', 'review_reports', 'review_media', 'questions' );

	/**
	 * Fully qualified table name for the current site.
	 *
	 * @param string $key Logical key.
	 */
	public static function table( string $key ): string {
		global $wpdb;
		return $wpdb->prefix . 'review_captain_' . $key;
	}

	/**
	 * CREATE TABLE statements in dbDelta format.
	 *
	 * @return array<string, string>
	 */
	public static function definitions(): array {
		global $wpdb;
		$c = $wpdb->get_charset_collate();

		$reviews        = self::table( 'reviews' );
		$review_events  = self::table( 'review_events' );
		$profiles       = self::table( 'profiles' );
		$jobs           = self::table( 'jobs' );
		$import_batches = self::table( 'import_batches' );
		$audit_log      = self::table( 'audit_log' );
		$review_tags    = self::table( 'review_tags' );
		$review_reports = self::table( 'review_reports' );
		$review_media   = self::table( 'review_media' );
		$questions      = self::table( 'questions' );

		return array(
			'reviews'        => "CREATE TABLE {$reviews} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  uuid char(36) NOT NULL,
  kind varchar(20) NOT NULL DEFAULT 'review',
  origin varchar(30) NOT NULL DEFAULT 'manual',
  platform varchar(30) NOT NULL DEFAULT 'local',
  profile_id bigint(20) unsigned NULL,
  external_id varchar(191) NULL,
  dedupe_key char(40) NULL,
  rating_value decimal(6,2) NULL,
  rating_scale_min decimal(6,2) NOT NULL DEFAULT '1.00',
  rating_scale_max decimal(6,2) NOT NULL DEFAULT '5.00',
  title varchar(255) NOT NULL DEFAULT '',
  body longtext NOT NULL,
  language varchar(20) NOT NULL DEFAULT '',
  author_name varchar(191) NOT NULL DEFAULT '',
  author_email varchar(191) NULL,
  author_user_id bigint(20) unsigned NULL,
  verification varchar(30) NOT NULL DEFAULT 'unverified',
  status varchar(20) NOT NULL DEFAULT 'pending',
  context_type varchar(40) NULL,
  context_id varchar(64) NULL,
  source_url text NULL,
  published_at datetime NULL,
  fetched_at datetime NULL,
  refresh_due_at datetime NULL,
  consent_publish tinyint(1) NOT NULL DEFAULT 0,
  consent_at datetime NULL,
  import_batch_id bigint(20) unsigned NULL,
  experience_id varchar(191) NULL,
  submission_key char(40) NULL,
  revision int(10) unsigned NOT NULL DEFAULT 0,
  meta longtext NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  removed_at datetime NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uuid (uuid),
  UNIQUE KEY dedupe_key (dedupe_key),
  UNIQUE KEY submission_key (submission_key),
  KEY status_kind_platform (status,kind,platform),
  KEY rating_value (rating_value),
  KEY created_at (created_at),
  KEY context (context_type,context_id),
  KEY author_email (author_email),
  KEY import_batch_id (import_batch_id),
  KEY profile_id (profile_id),
  KEY refresh_due_at (refresh_due_at)
) {$c};",
			'review_events'  => "CREATE TABLE {$review_events} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  review_id bigint(20) unsigned NOT NULL,
  action varchar(40) NOT NULL,
  from_status varchar(20) NULL,
  to_status varchar(20) NULL,
  reason varchar(500) NOT NULL DEFAULT '',
  user_id bigint(20) unsigned NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY review_id (review_id),
  KEY created_at (created_at)
) {$c};",
			'profiles'       => "CREATE TABLE {$profiles} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  platform varchar(30) NOT NULL,
  label varchar(191) NOT NULL DEFAULT '',
  profile_url text NULL,
  review_url text NULL,
  external_ref varchar(191) NULL,
  status varchar(20) NOT NULL DEFAULT 'manual',
  settings longtext NULL,
  credentials_enc longtext NULL,
  credentials_rev int(10) unsigned NOT NULL DEFAULT 0,
  key_id varchar(16) NULL,
  sync_cursor longtext NULL,
  last_sync_at datetime NULL,
  last_error_code varchar(64) NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY platform_ref (platform,external_ref),
  KEY status (status)
) {$c};",
			'jobs'           => "CREATE TABLE {$jobs} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  type varchar(100) NOT NULL,
  idempotency_key varchar(191) NOT NULL,
  payload longtext NULL,
  status varchar(20) NOT NULL DEFAULT 'queued',
  attempts int(10) unsigned NOT NULL DEFAULT 0,
  max_attempts int(10) unsigned NOT NULL DEFAULT 8,
  available_at datetime NOT NULL,
  lease_owner varchar(64) NULL,
  lease_expires_at datetime NULL,
  last_error_code varchar(64) NULL,
  last_error_message varchar(500) NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY idempotency_key (idempotency_key),
  KEY status_available (status,available_at),
  KEY type_status (type,status),
  KEY lease_expires_at (lease_expires_at)
) {$c};",
			'import_batches' => "CREATE TABLE {$import_batches} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  filename varchar(191) NOT NULL DEFAULT '',
  status varchar(20) NOT NULL DEFAULT 'completed',
  rows_total int(10) unsigned NOT NULL DEFAULT 0,
  rows_imported int(10) unsigned NOT NULL DEFAULT 0,
  rows_skipped int(10) unsigned NOT NULL DEFAULT 0,
  created_by bigint(20) unsigned NULL,
  created_at datetime NOT NULL,
  rolled_back_at datetime NULL,
  PRIMARY KEY  (id),
  KEY status (status)
) {$c};",
			'audit_log'      => "CREATE TABLE {$audit_log} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  event varchar(80) NOT NULL,
  object_type varchar(40) NOT NULL DEFAULT '',
  object_id varchar(64) NOT NULL DEFAULT '',
  user_id bigint(20) unsigned NULL,
  context longtext NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY event (event),
  KEY created_at (created_at),
  KEY object (object_type,object_id)
) {$c};",
			'review_tags'    => "CREATE TABLE {$review_tags} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  review_id bigint(20) unsigned NOT NULL,
  tag varchar(40) NOT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY review_tag (review_id,tag),
  KEY tag (tag)
) {$c};",
			'review_reports' => "CREATE TABLE {$review_reports} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  review_id bigint(20) unsigned NOT NULL,
  reason varchar(30) NOT NULL DEFAULT 'other',
  created_at datetime NOT NULL,
  resolved_at datetime NULL,
  PRIMARY KEY  (id),
  KEY review_id (review_id),
  KEY resolved_at (resolved_at)
) {$c};",
			'review_media'   => "CREATE TABLE {$review_media} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  review_id bigint(20) unsigned NOT NULL,
  file varchar(100) NOT NULL,
  mime varchar(30) NOT NULL,
  bytes int(10) unsigned NOT NULL DEFAULT 0,
  width int(10) unsigned NOT NULL DEFAULT 0,
  height int(10) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY file (file),
  KEY review_id (review_id)
) {$c};",
			'questions'      => "CREATE TABLE {$questions} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  uuid char(36) NOT NULL,
  context_type varchar(40) NOT NULL,
  context_id varchar(64) NOT NULL,
  question text NOT NULL,
  answer text NULL,
  author_name varchar(191) NOT NULL DEFAULT '',
  author_email varchar(191) NULL,
  status varchar(20) NOT NULL DEFAULT 'pending',
  answered_by bigint(20) unsigned NULL,
  answered_at datetime NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uuid (uuid),
  KEY context_status (context_type,context_id,status),
  KEY author_email (author_email),
  KEY status (status)
) {$c};",
		);
	}

	/**
	 * Create/upgrade all tables (idempotent).
	 */
	public static function install(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ( self::definitions() as $sql ) {
			dbDelta( $sql );
		}
	}

	/**
	 * Drop all tables (explicit data deletion only).
	 */
	public static function drop(): void {
		global $wpdb;
		foreach ( self::TABLES as $key ) {
			$wpdb->query( 'DROP TABLE IF EXISTS ' . self::table( $key ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		}
	}
}
