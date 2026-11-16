<?php
/**
 * How a record entered Review Captain.
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Domain;

/**
 * How a record entered Review Captain.
 */
enum Origin: string {
	case LOCAL_SUBMISSION = 'local_submission';
	case MANUAL = 'manual';
	case CSV_IMPORT = 'csv_import';
	case PROVIDER_SYNC = 'provider_sync';
	case CONNECTOR = 'connector';

	/**
	 * All values.
	 *
	 * @return list<string>
	 */
	public static function values(): array {
		return array_map( static fn( self $c ): string => $c->value, self::cases() );
	}
}
