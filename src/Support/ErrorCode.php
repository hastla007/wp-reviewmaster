<?php
/**
 * Standard error codes and retry classes (RC-030, RC-082, TEC-006, CON-013).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Support;

/**
 * Error codes in the format rc.<area>.<code>.
 */
final class ErrorCode {

	public const AUTH_INVALID          = 'rc.auth.invalid';
	public const AUTH_EXPIRED          = 'rc.auth.expired';
	public const AUTH_REVOKED          = 'rc.auth.revoked';
	public const PERMISSION_DENIED     = 'rc.auth.permission_denied';
	public const QUOTA_EXCEEDED        = 'rc.quota.exceeded';
	public const RATE_LIMITED          = 'rc.quota.rate_limited';
	public const BUDGET_EXHAUSTED      = 'rc.quota.budget_exhausted';
	public const NET_TIMEOUT           = 'rc.net.timeout';
	public const NET_UNREACHABLE       = 'rc.net.unreachable';
	public const TEMP_UNAVAILABLE      = 'rc.temp.unavailable';
	public const CONTRACT_INCOMPATIBLE = 'rc.contract.incompatible';
	public const CONTRACT_INVALID      = 'rc.contract.invalid';
	public const CONTRACT_UNAVAILABLE  = 'rc.contract.unavailable';
	public const DATA_INVALID_PAYLOAD  = 'rc.data.invalid_payload';
	public const DATA_DUPLICATE        = 'rc.data.duplicate';
	public const REMOTE_REMOVED        = 'rc.remote.removed';
	public const SOURCE_UNAVAILABLE    = 'rc.source.unavailable';
	public const CONSENT_MISSING       = 'rc.consent.missing';
	public const PERMANENT_FAILURE     = 'rc.perm.failed';
	public const CRYPTO_KEY_MISSING    = 'rc.crypto.key_missing';
	public const CRYPTO_DECRYPT_FAILED = 'rc.crypto.decrypt_failed';
	public const INTERNAL              = 'rc.internal.error';

	public const CLASS_TRANSIENT  = 'transient';
	public const CLASS_RATE_LIMIT = 'rate_limit';
	public const CLASS_AUTH       = 'auth';
	public const CLASS_PERMANENT  = 'permanent';
	public const CLASS_DATA       = 'data';
	public const CLASS_CONFIG     = 'config';

	/**
	 * Retry class for a code. Unknown codes are treated as transient.
	 *
	 * @param string $code Error code.
	 */
	public static function retry_class( string $code ): string {
		return match ( $code ) {
			self::AUTH_INVALID, self::AUTH_EXPIRED, self::AUTH_REVOKED, self::PERMISSION_DENIED, self::CRYPTO_KEY_MISSING, self::CRYPTO_DECRYPT_FAILED => self::CLASS_AUTH,
			self::QUOTA_EXCEEDED, self::RATE_LIMITED, self::BUDGET_EXHAUSTED => self::CLASS_RATE_LIMIT,
			self::DATA_INVALID_PAYLOAD, self::DATA_DUPLICATE, self::CONSENT_MISSING => self::CLASS_DATA,
			self::CONTRACT_INCOMPATIBLE, self::CONTRACT_UNAVAILABLE, self::SOURCE_UNAVAILABLE => self::CLASS_CONFIG,
			self::CONTRACT_INVALID, self::REMOTE_REMOVED, self::PERMANENT_FAILURE => self::CLASS_PERMANENT,
			default => self::CLASS_TRANSIENT,
		};
	}

	/**
	 * Whether the code is a known core code.
	 *
	 * @param string $code Code.
	 */
	public static function is_known( string $code ): bool {
		return in_array( $code, self::all(), true );
	}

	/**
	 * All codes.
	 *
	 * @return list<string>
	 */
	public static function all(): array {
		$codes = array_filter(
			( new \ReflectionClass( self::class ) )->getConstants(),
			static fn( $value ) => is_string( $value ) && str_starts_with( $value, 'rc.' )
		);
		return array_values( $codes );
	}

	/**
	 * Human readable next admin action for a code.
	 *
	 * @param string $code Code.
	 */
	public static function admin_action( string $code ): string {
		return match ( self::retry_class( $code ) ) {
			self::CLASS_AUTH       => __( 'Verbindung neu autorisieren bzw. Schlüssel prüfen.', 'review-captain' ),
			self::CLASS_RATE_LIMIT => __( 'Keine Aktion nötig – automatischer Neuversuch nach Ablauf des Limits.', 'review-captain' ),
			self::CLASS_CONFIG     => __( 'Quell-Plugin bzw. Connector prüfen (aktiv, kompatible Vertragsversion); der Vorgang wird danach automatisch fortgesetzt.', 'review-captain' ),
			self::CLASS_DATA       => __( 'Eingangsdaten bzw. Mapping prüfen; der Vorgang wird nicht wiederholt.', 'review-captain' ),
			self::CLASS_PERMANENT  => __( 'Vorgang ist endgültig fehlgeschlagen; Konfiguration oder Vertrag prüfen.', 'review-captain' ),
			default                => __( 'Keine Aktion nötig – automatischer Neuversuch mit Wartezeit.', 'review-captain' ),
		};
	}
}
