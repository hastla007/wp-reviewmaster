<?php
/**
 * CSV export with formula-injection protection (RC-044) and import with dry-run and rollback (RC-045).
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\ImportExport;

use ReviewCaptain\Audit\AuditLog;
use ReviewCaptain\Database\Schema;
use ReviewCaptain\Reviews\ReviewRepository;
use ReviewCaptain\Reviews\ReviewService;

defined( 'ABSPATH' ) || exit;

final class Csv {

	public const COLUMNS = array(
		'uuid', 'origin', 'provider', 'external_id', 'type', 'rating_value', 'rating_min', 'rating_max', 'title', 'content', 'language',
		'author_name', 'context_type', 'context_id', 'source_url', 'attribution', 'status', 'verification', 'published_at', 'created_at',
	);

	/** Columns accepted on import. */
	public const IMPORT_COLUMNS = array(
		'provider', 'external_id', 'type', 'rating_value', 'rating_min', 'rating_max', 'title', 'content', 'language', 'author_name',
		'context_type', 'context_id', 'source_url', 'attribution', 'status', 'published_at',
	);

	public const MAX_IMPORT_ROWS = 5000;

	/**
	 * Neutralize values that spreadsheet software would interpret as formulas.
	 */
	public static function safe_cell( $value ): string {
		$value = (string) $value;
		if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			// Keep plain negative numbers usable.
			if ( ! preg_match( '/^-\d+(\.\d+)?$/', $value ) ) {
				$value = "'" . $value;
			}
		}
		return $value;
	}

	/**
	 * Stream a CSV export to the given handle in batches (constant memory).
	 *
	 * @param resource            $handle  Output stream.
	 * @param array<string,mixed> $filters Review filters.
	 */
	public static function export( $handle, array $filters = array() ): int {
		fwrite( $handle, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- UTF-8 BOM for spreadsheet apps.
		fputcsv( $handle, self::COLUMNS );
		$offset = 0;
		$total  = 0;
		do {
			$rows = ReviewRepository::query( $filters, 500, $offset, 'id', 'ASC' );
			foreach ( $rows as $row ) {
				$line = array();
				foreach ( self::COLUMNS as $col ) {
					$value  = $row[ $col ] ?? '';
					$line[] = ( in_array( $col, array( 'published_at', 'created_at' ), true ) && $value ) ? gmdate( 'c', strtotime( $value . ' UTC' ) ) : self::safe_cell( $value );
				}
				fputcsv( $handle, $line );
				++$total;
			}
			$offset += 500;
		} while ( count( $rows ) === 500 );
		AuditLog::log( 'reviews_exported', null, null, array( 'count' => $total, 'format' => 'csv' ) );
		return $total;
	}

	/**
	 * Parse a CSV file into associative rows.
	 *
	 * @return array{rows: array<int,array<string,string>>, errors: array<int,string>, header: string[]}
	 */
	public static function parse( string $path ): array {
		$out = array(
			'rows'   => array(),
			'errors' => array(),
			'header' => array(),
		);
		$fh  = fopen( $path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $fh ) {
			$out['errors'][0] = __( 'The file could not be read.', 'review-captain' );
			return $out;
		}
		$first = fgets( $fh );
		if ( false === $first ) {
			$out['errors'][0] = __( 'The file is empty.', 'review-captain' );
			return $out;
		}
		$first     = preg_replace( '/^\xEF\xBB\xBF/', '', $first );
		$delimiter = substr_count( $first, ';' ) > substr_count( $first, ',' ) ? ';' : ',';
		$header    = array_map( 'trim', str_getcsv( $first, $delimiter ) );
		$header    = array_map( 'sanitize_key', $header );
		$out['header'] = $header;
		$line          = 1;
		while ( false !== ( $data = fgetcsv( $fh, 0, $delimiter ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			++$line;
			if ( array( null ) === $data ) {
				continue;
			}
			if ( count( $out['rows'] ) >= self::MAX_IMPORT_ROWS ) {
				/* translators: %d: max rows */
				$out['errors'][ $line ] = sprintf( __( 'Only the first %d rows are imported per file.', 'review-captain' ), self::MAX_IMPORT_ROWS );
				break;
			}
			$row = array();
			foreach ( $header as $i => $col ) {
				$value = isset( $data[ $i ] ) ? (string) $data[ $i ] : '';
				// Undo our own export protection prefix.
				if ( '' !== $value && "'" === $value[0] && isset( $value[1] ) && in_array( $value[1], array( '=', '+', '-', '@' ), true ) ) {
					$value = substr( $value, 1 );
				}
				$row[ $col ] = $value;
			}
			$out['rows'][ $line ] = $row;
		}
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return $out;
	}

	/**
	 * Validate rows without writing (dry run).
	 *
	 * @param array<int,array<string,string>> $rows Parsed rows keyed by line number.
	 * @return array{valid: array<int,array<string,mixed>>, errors: array<int,string>, duplicates: array<int,string>}
	 */
	public static function dry_run( array $rows ): array {
		$valid      = array();
		$errors     = array();
		$duplicates = array();
		$seen       = array();
		foreach ( $rows as $line => $row ) {
			$provider = $row['provider'] ?? '';
			if ( '' === $provider ) {
				$provider = 'site';
			}
			$is_site = 'site' === $provider;
			if ( ! $is_site && ! in_array( $provider, ReviewService::EXTERNAL_PROVIDERS, true ) ) {
				$errors[ $line ] = __( 'Unknown platform.', 'review-captain' );
				continue;
			}
			$min = '' !== ( $row['rating_min'] ?? '' ) ? (int) $row['rating_min'] : 1;
			$max = '' !== ( $row['rating_max'] ?? '' ) ? (int) $row['rating_max'] : 5;
			$val = $row['rating_value'] ?? '';
			if ( '' !== $val && ( ! is_numeric( $val ) || (float) $val < $min || (float) $val > $max ) ) {
				$errors[ $line ] = __( 'Rating outside of the scale.', 'review-captain' );
				continue;
			}
			if ( '' === trim( ( $row['content'] ?? '' ) . ( $row['title'] ?? '' ) ) && '' === $val ) {
				$errors[ $line ] = __( 'Row has neither rating nor text.', 'review-captain' );
				continue;
			}
			if ( ! empty( $row['published_at'] ) && false === strtotime( $row['published_at'] ) ) {
				$errors[ $line ] = __( 'Invalid date.', 'review-captain' );
				continue;
			}
			if ( ! $is_site ) {
				$url = (string) ( $row['source_url'] ?? '' );
				if ( '' === $url || ! ReviewService::valid_https_url( $url ) ) {
					$errors[ $line ] = __( 'External reviews need a valid source link.', 'review-captain' );
					continue;
				}
			}
			// Duplicate detection: external ID, or same author+date+text hash.
			if ( ! empty( $row['external_id'] ) && ! $is_site ) {
				$key = self::external_key( $provider, (string) $row['external_id'] );
				if ( isset( $seen[ $key ] ) || ReviewRepository::find_by_external_key( $key ) ) {
					$duplicates[ $line ] = __( 'Already imported (same platform ID).', 'review-captain' );
					continue;
				}
			} else {
				$key = 'h:' . md5( strtolower( ( $row['author_name'] ?? '' ) . '|' . ( $row['published_at'] ?? '' ) . '|' . ( $row['content'] ?? '' ) ) );
				if ( isset( $seen[ $key ] ) ) {
					$duplicates[ $line ] = __( 'Duplicate row in this file.', 'review-captain' );
					continue;
				}
			}
			$seen[ $key ]   = true;
			$valid[ $line ] = array_intersect_key( $row, array_flip( self::IMPORT_COLUMNS ) ) + array( 'provider' => $provider );
		}
		return array(
			'valid'      => $valid,
			'errors'     => $errors,
			'duplicates' => $duplicates,
		);
	}

	public static function external_key( string $provider, string $external_id, string $profile_ref = '' ): string {
		return hash( 'sha256', $provider . '|' . $profile_ref . '|' . $external_id );
	}

	/**
	 * Import validated rows as one batch. Imported reviews start as pending unless status says approved.
	 *
	 * @param array<int,array<string,mixed>> $valid Rows from dry_run().
	 * @return array{batch_id:int, imported:int, skipped:int}
	 */
	public static function import( array $valid, string $filename = '' ): array {
		global $wpdb;
		$batch_uuid = wp_generate_uuid4();
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			Schema::table( 'rc_import_batches' ),
			array(
				'uuid'       => $batch_uuid,
				'filename'   => substr( sanitize_file_name( $filename ), 0, 191 ),
				'rows_total' => count( $valid ),
				'status'     => 'imported',
				'user_id'    => get_current_user_id(),
				'created_at' => current_time( 'mysql', true ),
			)
		);
		$batch_id = (int) $wpdb->insert_id;
		$imported = 0;
		$skipped  = 0;
		foreach ( $valid as $row ) {
			$provider = (string) $row['provider'];
			$is_site  = 'site' === $provider;
			$data     = array(
				'origin'          => 'import',
				'provider'        => $provider,
				'external_id'     => ! empty( $row['external_id'] ) ? substr( (string) $row['external_id'], 0, 191 ) : null,
				'external_key'    => ( ! empty( $row['external_id'] ) && ! $is_site ) ? self::external_key( $provider, (string) $row['external_id'] ) : null,
				'type'            => in_array( $row['type'] ?? '', ReviewRepository::TYPES, true ) ? $row['type'] : 'general',
				'rating_value'    => ( isset( $row['rating_value'] ) && '' !== $row['rating_value'] ) ? round( (float) $row['rating_value'], 2 ) : null,
				'rating_min'      => ! empty( $row['rating_min'] ) ? (int) $row['rating_min'] : 1,
				'rating_max'      => ! empty( $row['rating_max'] ) ? (int) $row['rating_max'] : 5,
				'title'           => ReviewService::clean_line( $row['title'] ?? '', 255 ),
				'content'         => ReviewService::clean_text( $row['content'] ?? '' ),
				'language'        => ReviewService::clean_line( $row['language'] ?? '', 20 ),
				'author_name'     => ReviewService::clean_line( $row['author_name'] ?? '', 191 ),
				'context_type'    => ! empty( $row['context_type'] ) ? substr( sanitize_key( (string) $row['context_type'] ), 0, 32 ) : null,
				'context_id'      => ReviewService::clean_line( $row['context_id'] ?? '', 64 ),
				'source_url'      => ! empty( $row['source_url'] ) ? esc_url_raw( (string) $row['source_url'] ) : null,
				'attribution'     => ReviewService::clean_line( $row['attribution'] ?? '', 255 ),
				'status'          => ( 'approved' === ( $row['status'] ?? '' ) ) ? 'approved' : 'pending',
				// Imported reviews are never verified (RC-041); external ones are marked external.
				'verification'    => $is_site ? 'unverified' : 'external',
				'published_at'    => ! empty( $row['published_at'] ) ? gmdate( 'Y-m-d H:i:s', strtotime( (string) $row['published_at'] ) ) : null,
				'import_batch_id' => $batch_id,
			);
			$id = ReviewRepository::insert( $data );
			if ( is_int( $id ) ) {
				ReviewRepository::log( $id, 'created', 'import' );
				++$imported;
			} else {
				++$skipped;
			}
		}
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			Schema::table( 'rc_import_batches' ),
			array(
				'rows_imported' => $imported,
				'rows_skipped'  => $skipped,
			),
			array( 'id' => $batch_id )
		);
		AuditLog::log( 'reviews_imported', 'import_batch', $batch_id, array( 'count' => $imported ) );
		ReviewService::bump_cache();
		return array(
			'batch_id' => $batch_id,
			'imported' => $imported,
			'skipped'  => $skipped,
		);
	}

	/**
	 * Roll back an import batch: removes all reviews of the batch.
	 */
	public static function rollback( int $batch_id ): int {
		global $wpdb;
		$ids     = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . Schema::table( 'rc_reviews' ) . ' WHERE import_batch_id = %d', $batch_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		$removed = 0;
		foreach ( $ids as $id ) {
			$removed += ReviewRepository::delete( $id ) ? 1 : 0;
		}
		$wpdb->update( Schema::table( 'rc_import_batches' ), array( 'status' => 'rolled_back' ), array( 'id' => $batch_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		AuditLog::log( 'import_rolled_back', 'import_batch', $batch_id, array( 'count' => $removed ) );
		ReviewService::bump_cache();
		return $removed;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function batches( int $limit = 20 ): array {
		global $wpdb;
		$table = Schema::table( 'rc_import_batches' );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	}
}
