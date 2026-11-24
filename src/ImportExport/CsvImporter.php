<?php
/**
 * CSV import with dry run and rollback (RC-045).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\ImportExport;

use ReviewCaptain\Domain\Kind;
use ReviewCaptain\Domain\Origin;
use ReviewCaptain\Domain\Platform;
use ReviewCaptain\Domain\Review;
use ReviewCaptain\Domain\ReviewStatus;
use ReviewCaptain\Domain\Verification;
use ReviewCaptain\Install\Schema;
use ReviewCaptain\Plugin;
use ReviewCaptain\Repository\ReviewRepository;

/**
 * Imports reviews from the CSV format in docs/migration.md §3.
 *
 * Line numbers refer to CSV records (header = 1, first data record = 2).
 * Only new records are created; duplicates (same dedupe_key in DB or file, or suspected duplicates
 * without external_id) are skipped. purchase_verified/attendance_confirmed are downgraded (RC-041).
 */
class CsvImporter {

	public const MAX_BYTES = 5242880;

	public const MAX_ROWS = 10000;

	/**
	 * Required columns.
	 */
	public const REQUIRED_COLUMNS = array( 'kind', 'platform', 'body', 'author_name', 'published_at' );

	/**
	 * Statuses that may be taken over from the file; anything else becomes pending.
	 */
	public const IMPORTABLE_STATUSES = array( 'pending', 'approved', 'archived' );

	/**
	 * Constructor.
	 *
	 * @param ReviewRepository $reviews Repository.
	 */
	public function __construct( private ReviewRepository $reviews ) {}

	/**
	 * Validate a file without writing anything.
	 *
	 * @param string $path File path.
	 * @return array{rows: int, valid: int, errors: list<array{line: int, code: string, message: string}>, duplicates: list<int>, suspected: list<int>, warnings: list<array{line: int, code: string, message: string}>}
	 */
	public function dry_run( string $path ): array {
		$analysis = $this->analyze( $path );
		unset( $analysis['importable'] );
		return $analysis;
	}

	/**
	 * Import a file. Creates an import_batches row and inserts all importable records.
	 *
	 * @param string $path     File path.
	 * @param int    $user_id  User ID.
	 * @param string $filename Original filename (for the batch list).
	 * @return array{batch_id: int, imported: int, skipped: int}
	 */
	public function import( string $path, int $user_id, string $filename = '' ): array {
		global $wpdb;
		$analysis = $this->analyze( $path );
		$fatal    = array_filter( $analysis['errors'], static fn( array $e ): bool => $e['line'] <= 1 );
		if ( $fatal ) {
			return array(
				'batch_id' => 0,
				'imported' => 0,
				'skipped'  => $analysis['rows'],
			);
		}

		$table = Schema::table( 'import_batches' );
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$table,
			array(
				'filename'      => mb_substr( sanitize_file_name( '' !== $filename ? $filename : basename( $path ) ), 0, 191 ),
				'status'        => 'running',
				'rows_total'    => $analysis['rows'],
				'rows_imported' => 0,
				'rows_skipped'  => 0,
				'created_by'    => $user_id > 0 ? $user_id : null,
				'created_at'    => gmdate( 'Y-m-d H:i:s' ),
			)
		);
		$batch_id = (int) $wpdb->insert_id;
		if ( $batch_id <= 0 ) {
			return array(
				'batch_id' => 0,
				'imported' => 0,
				'skipped'  => $analysis['rows'],
			);
		}

		$imported = 0;
		foreach ( $analysis['importable'] as $review ) {
			$review->import_batch_id = $batch_id;
			try {
				$this->reviews->insert( $review );
				++$imported;
			} catch ( \InvalidArgumentException | \RuntimeException $e ) {
				continue; // Counted as skipped (e.g. lost race on dedupe_key).
			}
		}
		$skipped = $analysis['rows'] - $imported;

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'status'        => 'completed',
				'rows_imported' => $imported,
				'rows_skipped'  => $skipped,
			),
			array( 'id' => $batch_id )
		);
		Plugin::instance()->audit()->record(
			'import.completed',
			'import_batch',
			$batch_id,
			array(
				'count'    => $imported,
				'batch_id' => $batch_id,
			)
		);

		return array(
			'batch_id' => $batch_id,
			'imported' => $imported,
			'skipped'  => $skipped,
		);
	}

	/**
	 * Delete exactly the reviews of one batch (origin csv_import) and mark it rolled back.
	 * Idempotent: a rolled back or unknown batch returns 0.
	 *
	 * @param int $batch_id Batch ID.
	 * @return int Number of deleted reviews.
	 */
	public function rollback( int $batch_id ): int {
		global $wpdb;
		$batch = $this->batch( $batch_id );
		if ( null === $batch || ! empty( $batch['rolled_back_at'] ) ) {
			return 0;
		}
		$reviews = $this->reviews->table();
		$deleted = 0;
		do {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$reviews} WHERE import_batch_id = %d AND origin = %s ORDER BY id ASC LIMIT 200", $batch_id, Origin::CSV_IMPORT->value ) );
			$ids = array_map( 'intval', (array) $ids );
			foreach ( $ids as $id ) {
				if ( $this->reviews->delete( $id ) ) {
					++$deleted;
				}
			}
		} while ( count( $ids ) === 200 );

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			Schema::table( 'import_batches' ),
			array(
				'status'         => 'rolled_back',
				'rolled_back_at' => gmdate( 'Y-m-d H:i:s' ),
			),
			array( 'id' => $batch_id )
		);
		Plugin::instance()->audit()->record(
			'import.rolled_back',
			'import_batch',
			$batch_id,
			array(
				'count'    => $deleted,
				'batch_id' => $batch_id,
			)
		);
		return $deleted;
	}

	/**
	 * Number of reviews of a batch that were moderated/edited after the import (needs extra confirmation).
	 *
	 * @param int $batch_id Batch ID.
	 */
	public function modified_since_import( int $batch_id ): int {
		global $wpdb;
		$batch = $this->batch( $batch_id );
		if ( null === $batch ) {
			return 0;
		}
		$reviews = $this->reviews->table();
		$events  = Schema::table( 'review_events' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT r.id) FROM {$reviews} r INNER JOIN {$events} e ON e.review_id = r.id WHERE r.import_batch_id = %d AND e.action <> %s", $batch_id, 'created' ) );
	}

	/**
	 * One batch row.
	 *
	 * @param int $batch_id Batch ID.
	 * @return array<string, mixed>|null
	 */
	public function batch( int $batch_id ): ?array {
		global $wpdb;
		$table = Schema::table( 'import_batches' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $batch_id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Latest batches.
	 *
	 * @param int $limit Limit.
	 * @return list<array<string, mixed>>
	 */
	public function batches( int $limit = 50 ): array {
		global $wpdb;
		$table = Schema::table( 'import_batches' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", max( 1, min( 200, $limit ) ) ), ARRAY_A );
		return is_array( $rows ) ? array_values( $rows ) : array();
	}

	/**
	 * Full analysis including importable Review objects.
	 *
	 * @param string $path File path.
	 * @return array{rows: int, valid: int, errors: list<array{line: int, code: string, message: string}>, duplicates: list<int>, suspected: list<int>, warnings: list<array{line: int, code: string, message: string}>, importable: list<Review>}
	 */
	private function analyze( string $path ): array {
		$out = array(
			'rows'       => 0,
			'valid'      => 0,
			'errors'     => array(),
			'duplicates' => array(),
			'suspected'  => array(),
			'warnings'   => array(),
			'importable' => array(),
		);

		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			$out['errors'][] = self::issue( 0, 'file_unreadable' );
			return $out;
		}
		if ( (int) filesize( $path ) > self::MAX_BYTES ) {
			$out['errors'][] = self::issue( 0, 'file_too_large' );
			return $out;
		}
		$handle = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( false === $handle ) {
			$out['errors'][] = self::issue( 0, 'file_unreadable' );
			return $out;
		}

		try {
			$first = (string) fgets( $handle );
			if ( str_starts_with( $first, Csv::BOM ) ) {
				$first = substr( $first, 3 );
				fseek( $handle, 3 );
			} else {
				rewind( $handle );
			}
			$delimiter = substr_count( $first, ';' ) > substr_count( $first, ',' ) ? ';' : ',';

			$header = fgetcsv( $handle, 0, $delimiter, '"', '' );
			if ( ! is_array( $header ) || array( null ) === $header ) {
				$out['errors'][] = self::issue( 1, 'missing_header' );
				return $out;
			}
			$header = array_map( static fn( $h ): string => strtolower( trim( (string) $h ) ), $header );
			foreach ( self::REQUIRED_COLUMNS as $col ) {
				if ( ! in_array( $col, $header, true ) ) {
					$out['errors'][] = self::issue( 1, 'missing_column', $col );
				}
			}
			if ( $out['errors'] ) {
				return $out;
			}
			foreach ( $header as $col ) {
				if ( ! in_array( $col, Csv::IMPORT_COLUMNS, true ) ) {
					$out['warnings'][] = self::issue( 1, 'unknown_column', $col );
				}
			}

			$seen_keys    = array();
			$seen_suspect = array();
			$line         = 1;
			while ( false !== ( $cells = fgetcsv( $handle, 0, $delimiter, '"', '' ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
				++$line;
				if ( array( null ) === $cells ) {
					continue; // Empty line.
				}
				++$out['rows'];
				if ( $out['rows'] > self::MAX_ROWS ) {
					$out['errors']     = array( self::issue( 0, 'too_many_rows' ) );
					$out['importable'] = array();
					$out['valid']      = 0;
					$out['duplicates'] = array();
					$out['suspected']  = array();
					return $out;
				}
				$row = array();
				foreach ( $header as $i => $col ) {
					$row[ $col ] = isset( $cells[ $i ] ) ? (string) $cells[ $i ] : '';
				}

				[ $review, $errors, $warnings ] = $this->build( $row );
				foreach ( $warnings as $w ) {
					$out['warnings'][] = self::issue( $line, $w[0], $w[1] );
				}
				if ( $errors || null === $review ) {
					foreach ( $errors as $err ) {
						$out['errors'][] = self::issue( $line, $err[0], $err[1] );
					}
					continue;
				}

				$key = $review->dedupe_key();
				if ( null !== $key ) {
					if ( isset( $seen_keys[ $key ] ) || null !== $this->reviews->find_by_dedupe_key( $key ) ) {
						$out['duplicates'][] = $line;
						continue;
					}
					$seen_keys[ $key ] = true;
				} else {
					$hash = self::suspect_hash( $review );
					if ( isset( $seen_suspect[ $hash ] ) || $this->suspect_in_db( $review, $hash ) ) {
						$out['duplicates'][] = $line;
						$out['suspected'][]  = $line;
						continue;
					}
					$seen_suspect[ $hash ] = true;
				}
				$out['importable'][] = $review;
				++$out['valid'];
			}
		} finally {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		}
		return $out;
	}

	/**
	 * Build a review from one row.
	 *
	 * @param array<string, string> $row Row keyed by column.
	 * @return array{0: ?Review, 1: list<array{0: string, 1: string}>, 2: list<array{0: string, 1: string}>}
	 */
	private function build( array $row ): array {
		$errors   = array();
		$warnings = array();
		$cell     = static function ( string $col ) use ( $row ): string {
			$value = Csv::unprotect( trim( (string) ( $row[ $col ] ?? '' ) ) );
			return str_replace( "\r\n", "\n", $value );
		};

		foreach ( $row as $col => $value ) {
			if ( '' !== $value && ! mb_check_encoding( $value, 'UTF-8' ) ) {
				return array( null, array( array( 'invalid_encoding', (string) $col ) ), array() );
			}
		}

		$r         = new Review();
		$r->origin = Origin::CSV_IMPORT;

		$kind = Kind::tryFrom( $cell( 'kind' ) );
		if ( null === $kind ) {
			$errors[] = array( 'invalid_kind', 'kind' );
		} else {
			$r->kind = $kind;
		}
		$platform = Platform::tryFrom( $cell( 'platform' ) );
		if ( null === $platform ) {
			$errors[] = array( 'invalid_platform', 'platform' );
		} else {
			$r->platform = $platform;
		}

		$external = $cell( 'external_id' );
		if ( mb_strlen( $external ) > 191 ) {
			$errors[] = array( 'too_long', 'external_id' );
		}
		$r->external_id = '' === $external ? null : $external;

		$rating = str_replace( ',', '.', $cell( 'rating_value' ) );
		if ( '' !== $rating ) {
			if ( ! is_numeric( $rating ) ) {
				$errors[] = array( 'invalid_rating', 'rating_value' );
			} else {
				$r->rating_value = (float) $rating;
			}
		} elseif ( Kind::REVIEW === $r->kind ) {
			$errors[] = array( 'missing_rating', 'rating_value' );
		}
		$scale = str_replace( ',', '.', $cell( 'rating_scale_max' ) );
		if ( '' !== $scale ) {
			if ( ! is_numeric( $scale ) || (float) $scale <= 1 ) {
				$errors[] = array( 'invalid_scale', 'rating_scale_max' );
			} else {
				$r->rating_scale_max = (float) $scale;
			}
		}

		$r->title = wp_strip_all_tags( $cell( 'title' ), true );
		if ( mb_strlen( $r->title ) > 255 ) {
			$errors[] = array( 'too_long', 'title' );
		}
		$r->body = trim( wp_strip_all_tags( $cell( 'body' ) ) );
		if ( '' === $r->body ) {
			$errors[] = array( 'missing_value', 'body' );
		}
		$r->language = $cell( 'language' );
		if ( mb_strlen( $r->language ) > 20 || ( '' !== $r->language && ! preg_match( '/^[A-Za-z]{2,3}([_-][A-Za-z0-9]{2,8})*$/', $r->language ) ) ) {
			$errors[] = array( 'invalid_language', 'language' );
		}
		$r->author_name = sanitize_text_field( $cell( 'author_name' ) );
		if ( '' === $r->author_name ) {
			$errors[] = array( 'missing_value', 'author_name' );
		} elseif ( mb_strlen( $r->author_name ) > 191 ) {
			$errors[] = array( 'too_long', 'author_name' );
		}

		$published = self::parse_date( $cell( 'published_at' ) );
		if ( null === $published ) {
			$errors[] = array( 'invalid_date', 'published_at' );
		} elseif ( strtotime( $published . ' UTC' ) > time() + 300 ) {
			$errors[] = array( 'future_date', 'published_at' );
		} else {
			$r->published_at = $published;
		}

		$url           = $cell( 'source_url' );
		$r->source_url = '' === $url ? null : esc_url_raw( $url );

		// Verification (RC-041): only a source contract may verify.
		$verification = $cell( 'verification' );
		$parsed       = '' === $verification ? null : Verification::tryFrom( $verification );
		if ( '' !== $verification && null === $parsed ) {
			$errors[] = array( 'invalid_verification', 'verification' );
		}
		if ( null !== $parsed && $parsed->requires_source_contract() ) {
			$warnings[] = array( 'verification_downgraded', 'verification' );
		}
		$r->verification = $r->platform->is_external() ? Verification::EXTERNAL_PLATFORM : Verification::UNVERIFIED;

		$status = $cell( 'status' );
		if ( in_array( $status, self::IMPORTABLE_STATUSES, true ) ) {
			$r->status = ReviewStatus::from( $status );
		} else {
			$r->status = ReviewStatus::PENDING;
			if ( '' !== $status ) {
				$warnings[] = array( 'status_reset', 'status' );
			}
		}

		$ctype           = $cell( 'context_type' );
		$cid             = $cell( 'context_id' );
		$r->context_type = '' === $ctype ? null : $ctype;
		$r->context_id   = '' === $cid ? null : $cid;
		if ( mb_strlen( $cid ) > 64 ) {
			$errors[] = array( 'too_long', 'context_id' );
		}

		// SET-RC-12: benefit flag; an unreadable value is an error, never silently "no".
		$flag = strtolower( $cell( 'incentivised' ) );
		if ( in_array( $flag, array( '1', 'yes', 'true', 'ja', 'x' ), true ) ) {
			$r->incentivised = true;
		} elseif ( ! in_array( $flag, array( '', '0', 'no', 'false', 'nein' ), true ) ) {
			$errors[] = array( 'invalid_flag', 'incentivised' );
		}

		$r->consent_publish = false;
		$r->author_email    = null;

		if ( ! $errors ) {
			foreach ( $r->validate() as $code ) {
				$errors[] = array( $code, '' );
			}
		}
		return array( $errors ? null : $r, $errors, $warnings );
	}

	/**
	 * Parse ISO 8601 date/datetime; without zone the site time zone applies. Returns UTC Y-m-d H:i:s.
	 *
	 * @param string $value Value.
	 */
	public static function parse_date( string $value ): ?string {
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{2}):(\d{2})(?::(\d{2}))?(?:\.\d+)?(Z|[+\-]\d{2}:?\d{2})?)?$/', $value, $m ) ) {
			return null;
		}
		if ( ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return null;
		}
		try {
			$zone = ! empty( $m[7] ) ? null : ( function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' ) );
			$date = new \DateTimeImmutable( $value, $zone );
		} catch ( \Exception $e ) {
			return null;
		}
		return $date->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Hash for suspected duplicates without external_id.
	 *
	 * @param Review $r Review.
	 */
	public static function suspect_hash( Review $r ): string {
		return sha1( $r->platform->value . '|' . mb_strtolower( trim( $r->author_name ) ) . '|' . self::local_day( $r->published_at ) . '|' . mb_substr( trim( $r->body ), 0, 200 ) );
	}

	/**
	 * Calendar day (Y-m-d) of a UTC datetime in the site time zone.
	 *
	 * @param string|null $utc UTC datetime.
	 */
	private static function local_day( ?string $utc ): string {
		if ( null === $utc || '' === $utc ) {
			return '';
		}
		try {
			$date = new \DateTimeImmutable( $utc, new \DateTimeZone( 'UTC' ) );
		} catch ( \Exception $e ) {
			return substr( $utc, 0, 10 );
		}
		return $date->setTimezone( self::site_zone() )->format( 'Y-m-d' );
	}

	/**
	 * Site time zone.
	 */
	private static function site_zone(): \DateTimeZone {
		return function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' );
	}

	/**
	 * Whether a suspected duplicate already exists in the database.
	 *
	 * @param Review $r    Review.
	 * @param string $hash Hash.
	 */
	private function suspect_in_db( Review $r, string $hash ): bool {
		global $wpdb;
		if ( null === $r->published_at ) {
			return false;
		}
		$start = new \DateTimeImmutable( self::local_day( $r->published_at ) . ' 00:00:00', self::site_zone() );
		$utc   = new \DateTimeZone( 'UTC' );
		$from  = $start->setTimezone( $utc )->format( 'Y-m-d H:i:s' );
		$to    = $start->modify( '+1 day' )->setTimezone( $utc )->format( 'Y-m-d H:i:s' );
		$table = $this->reviews->table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT platform, author_name, published_at, body FROM {$table} WHERE platform = %s AND LOWER(author_name) = LOWER(%s) AND published_at >= %s AND published_at < %s LIMIT 50", $r->platform->value, $r->author_name, $from, $to ), ARRAY_A );
		foreach ( (array) $rows as $row ) {
			if ( self::suspect_hash( Review::from_row( $row ) ) === $hash ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Build an issue entry with a translated message.
	 *
	 * @param int    $line  Line.
	 * @param string $code  Code.
	 * @param string $field Column.
	 * @return array{line: int, code: string, message: string}
	 */
	private static function issue( int $line, string $code, string $field = '' ): array {
		$message = match ( $code ) {
			'file_unreadable'          => __( 'Die Datei konnte nicht gelesen werden.', 'review-captain' ),
			'file_too_large'           => __( 'Die Datei ist größer als 5 MB.', 'review-captain' ),
			'too_many_rows'            => __( 'Die Datei enthält mehr als 10.000 Datenzeilen.', 'review-captain' ),
			'missing_header'           => __( 'Kopfzeile fehlt.', 'review-captain' ),
			'missing_column'           => __( 'Pflichtspalte fehlt.', 'review-captain' ),
			'unknown_column'           => __( 'Unbekannte Spalte wird ignoriert.', 'review-captain' ),
			'invalid_encoding'         => __( 'Zelle ist nicht UTF-8-kodiert.', 'review-captain' ),
			'invalid_kind'             => __( 'Ungültiger Typ (erlaubt: review, testimonial).', 'review-captain' ),
			'invalid_platform'         => __( 'Ungültige Plattform.', 'review-captain' ),
			'too_long'                 => __( 'Wert ist zu lang.', 'review-captain' ),
			'invalid_rating'           => __( 'Bewertung ist keine Zahl.', 'review-captain' ),
			'missing_rating'           => __( 'Bewertung fehlt (Pflicht bei Typ review).', 'review-captain' ),
			'invalid_scale'            => __( 'Ungültiges Skalenmaximum.', 'review-captain' ),
			'missing_value'            => __( 'Pflichtwert fehlt.', 'review-captain' ),
			'invalid_language'         => __( 'Ungültige Sprachangabe.', 'review-captain' ),
			'invalid_date'             => __( 'Ungültiges Datum (ISO 8601 erwartet).', 'review-captain' ),
			'future_date'              => __( 'Datum liegt in der Zukunft.', 'review-captain' ),
			'invalid_verification'     => __( 'Ungültiger Verifizierungsstatus.', 'review-captain' ),
			'verification_downgraded'  => __( 'Kauf-/Teilnahmeverifizierung kann nicht importiert werden; der Datensatz wird als ungeprüft bzw. externe Plattformbewertung gespeichert.', 'review-captain' ),
			'status_reset'             => __( 'Status ist nicht importierbar und wird auf „Wartet auf Moderation“ gesetzt.', 'review-captain' ),
			'rating_out_of_scale'      => __( 'Bewertung liegt außerhalb der Skala.', 'review-captain' ),
			'empty_review'             => __( 'Weder Text noch Bewertung vorhanden.', 'review-captain' ),
			'missing_source_url'       => __( 'Quellenlink fehlt (Pflicht bei externen Plattformen).', 'review-captain' ),
			'source_url_not_https'     => __( 'Quellenlink muss mit https:// beginnen.', 'review-captain' ),
			'testimonial_must_be_local' => __( 'Testimonials sind nur mit Plattform local erlaubt.', 'review-captain' ),
			'invalid_context_type'     => __( 'Ungültiger Kontexttyp.', 'review-captain' ),
			'invalid_flag'             => __( 'Ungültiger Ja/Nein-Wert (erlaubt: 1, 0, yes, no, true, false, ja, nein).', 'review-captain' ),
			default                    => __( 'Ungültiger Wert.', 'review-captain' ),
		};
		if ( '' !== $field ) {
			$message .= ' (' . $field . ')';
		}
		return array(
			'line'    => $line,
			'code'    => $code,
			'message' => $message,
		);
	}
}
