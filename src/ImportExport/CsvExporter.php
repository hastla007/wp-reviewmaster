<?php
/**
 * Streaming CSV export (RC-044).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\ImportExport;

use ReviewCaptain\Domain\Review;
use ReviewCaptain\Repository\ReviewRepository;

/**
 * Exports reviews as UTF-8 CSV with BOM in the import format (docs/migration.md) plus uuid,
 * origin and created_at. author_email is only exported when explicitly requested.
 */
class CsvExporter {

	/**
	 * Rows fetched per query (bounded by ReviewRepository::MAX_LIMIT).
	 */
	public const CHUNK = 500;

	/**
	 * Allowed filter keys passed through to ReviewRepository::query().
	 */
	public const FILTERS = array( 'kind', 'platform', 'status', 'date_from', 'date_to' );

	/**
	 * Constructor.
	 *
	 * @param ReviewRepository $reviews Repository.
	 */
	public function __construct( private ReviewRepository $reviews ) {}

	/**
	 * Column header.
	 *
	 * @param bool $include_personal Include personal data columns.
	 * @return list<string>
	 */
	public static function columns( bool $include_personal = false ): array {
		$cols = array_merge( Csv::IMPORT_COLUMNS, Csv::EXPORT_EXTRA_COLUMNS );
		return $include_personal ? array_merge( $cols, Csv::PERSONAL_COLUMNS ) : $cols;
	}

	/**
	 * Write the export to a stream.
	 *
	 * @param resource             $handle           Writable stream.
	 * @param array<string, mixed> $filters          kind, platform, status, date_from, date_to (Y-m-d).
	 * @param bool                 $include_personal Include author_email.
	 * @return int Number of data rows written.
	 */
	public function write( $handle, array $filters, bool $include_personal = false ): int {
		$args = array();
		foreach ( self::FILTERS as $key ) {
			if ( isset( $filters[ $key ] ) && '' !== $filters[ $key ] && array() !== $filters[ $key ] ) {
				$args[ $key ] = $filters[ $key ];
			}
		}
		$args['orderby'] = 'id';
		$args['order']   = 'ASC';
		$args['limit']   = min( self::CHUNK, ReviewRepository::MAX_LIMIT );

		fwrite( $handle, Csv::BOM ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		Csv::put( $handle, self::columns( $include_personal ) );

		$count  = 0;
		$offset = 0;
		do {
			$args['offset'] = $offset;
			$batch          = $this->reviews->query( $args );
			foreach ( $batch as $review ) {
				Csv::put( $handle, self::row( $review, $include_personal ) );
				++$count;
			}
			$offset += count( $batch );
		} while ( count( $batch ) === $args['limit'] );

		return $count;
	}

	/**
	 * Map a review to cells.
	 *
	 * @param Review $r                Review.
	 * @param bool   $include_personal Include author_email.
	 * @return list<string>
	 */
	public static function row( Review $r, bool $include_personal = false ): array {
		$cells = array(
			$r->kind->value,
			$r->platform->value,
			(string) $r->external_id,
			self::number( $r->rating_value ),
			self::number( $r->rating_scale_max ),
			$r->title,
			$r->body,
			$r->language,
			$r->author_name,
			self::iso( $r->published_at ),
			(string) $r->source_url,
			$r->verification->value,
			$r->status->value,
			(string) $r->context_type,
			(string) $r->context_id,
			$r->uuid,
			$r->origin->value,
			self::iso( '' === $r->created_at ? null : $r->created_at ),
		);
		if ( $include_personal ) {
			$cells[] = (string) $r->author_email;
		}
		return $cells;
	}

	/**
	 * Format number without trailing zeros.
	 *
	 * @param float|null $value Value.
	 */
	private static function number( ?float $value ): string {
		if ( null === $value ) {
			return '';
		}
		return rtrim( rtrim( number_format( $value, 2, '.', '' ), '0' ), '.' );
	}

	/**
	 * UTC DB datetime → ISO 8601 with Z.
	 *
	 * @param string|null $value Value.
	 */
	private static function iso( ?string $value ): string {
		if ( null === $value || '' === $value ) {
			return '';
		}
		$ts = strtotime( $value . ' UTC' );
		return false === $ts ? '' : gmdate( 'Y-m-d\TH:i:s\Z', $ts );
	}

	/**
	 * Send download headers and stream the export to php://output.
	 *
	 * @param array<string, mixed> $filters          Filters.
	 * @param bool                 $include_personal Include author_email.
	 */
	public function stream( array $filters, bool $include_personal = false ): int {
		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}
		if ( ! headers_sent() ) {
			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="review-captain-export-' . gmdate( 'Ymd-His' ) . '.csv"' );
			header( 'X-Content-Type-Options: nosniff' );
		}
		$out = fopen( 'php://output', 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( false === $out ) {
			return 0;
		}
		$count = $this->write( $out, $filters, $include_personal );
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return $count;
	}
}
