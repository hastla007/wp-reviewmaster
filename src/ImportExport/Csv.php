<?php
/**
 * CSV helpers shared by export and import (RC-044, RC-045).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\ImportExport;

/**
 * Column definitions and spreadsheet formula-injection protection.
 */
final class Csv {

	/**
	 * Import columns in docs/migration.md order.
	 */
	public const IMPORT_COLUMNS = array(
		'kind',
		'platform',
		'external_id',
		'rating_value',
		'rating_scale_max',
		'title',
		'body',
		'language',
		'author_name',
		'published_at',
		'source_url',
		'verification',
		'status',
		'context_type',
		'context_id',
	);

	/**
	 * Additional export-only columns.
	 */
	public const EXPORT_EXTRA_COLUMNS = array( 'uuid', 'origin', 'created_at' );

	/**
	 * Personal column (only exported on explicit request).
	 */
	public const PERSONAL_COLUMNS = array( 'author_email' );

	public const BOM = "\xEF\xBB\xBF";

	/**
	 * Characters that make spreadsheet software interpret a cell as formula.
	 */
	private const FORMULA_TRIGGERS = array( '=', '+', '-', '@', "\t", "\r" );

	/**
	 * Prefix cells that could be interpreted as formula with an apostrophe.
	 *
	 * @param string $value Cell value.
	 */
	public static function protect( string $value ): string {
		if ( '' !== $value && in_array( $value[0], self::FORMULA_TRIGGERS, true ) ) {
			return "'" . $value;
		}
		return $value;
	}

	/**
	 * Reverse protect() for values read back from a Review Captain export.
	 *
	 * @param string $value Cell value.
	 */
	public static function unprotect( string $value ): string {
		if ( strlen( $value ) > 1 && "'" === $value[0] && in_array( $value[1], self::FORMULA_TRIGGERS, true ) ) {
			return substr( $value, 1 );
		}
		return $value;
	}

	/**
	 * Write one protected row.
	 *
	 * @param resource     $handle Stream.
	 * @param list<string> $cells  Cells.
	 */
	public static function put( $handle, array $cells ): void {
		fputcsv( $handle, array_map( array( self::class, 'protect' ), $cells ), ',', '"', '' );
	}
}
