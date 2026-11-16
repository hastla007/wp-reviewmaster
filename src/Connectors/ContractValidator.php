<?php
/**
 * Connector contract validator (CON-008). See docs/connectors/contract-v1.md §1.
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Connectors;

use ReviewCaptain\Connectors\Contract\ConnectorInterface;
use ReviewCaptain\Connectors\Contract\ContextProviderInterface;
use ReviewCaptain\Connectors\Contract\ExperienceSourceInterface;
use ReviewCaptain\Connectors\Contract\RecipientResolverInterface;
use ReviewCaptain\Connectors\Contract\RequestTransportInterface;
use ReviewCaptain\Connectors\Contract\ReviewSourceInterface;
use ReviewCaptain\Support\ErrorCode;

/**
 * Validates connector metadata. Never throws.
 *
 * Outcomes:
 * - `rejected`: connector is discarded (not registered), e.g. invalid key, empty metadata.
 * - `incompatible`: connector is registered but paused with rc.contract.incompatible.
 * - `ok`: compatible (may carry notices).
 */
final class ContractValidator {

	public const KEY_PATTERN = '/^[a-z0-9][a-z0-9_-]{2,63}$/';

	public const CONTRACT_PATTERN = '/^1\.(\d+|x)$/';

	/**
	 * Highest contract minor version this core implements.
	 */
	public const CORE_CONTRACT_MINOR = 0;

	/**
	 * Contract version supported by the core (shown in diagnostics).
	 */
	public const CORE_CONTRACT = '1.0';

	/**
	 * Capability → required interface.
	 */
	public const CAPABILITIES = array(
		'experience_source'  => ExperienceSourceInterface::class,
		'recipient_resolver' => RecipientResolverInterface::class,
		'review_source'      => ReviewSourceInterface::class,
		'request_transport'  => RequestTransportInterface::class,
		'context_provider'   => ContextProviderInterface::class,
	);

	/**
	 * Reserved keys (official suite adapters).
	 */
	public const RESERVED_KEYS = array( 'woocommerce', 'voucher-captain', 'pass-captain', 'affiliate-captain', 'form-captain' );

	/**
	 * Reserved key prefixes.
	 */
	public const RESERVED_PREFIXES = array( 'rc-', 'review-captain' );

	public const OUTCOME_OK           = 'ok';
	public const OUTCOME_INCOMPATIBLE = 'incompatible';
	public const OUTCOME_REJECTED     = 'rejected';

	/**
	 * Validate a connector candidate.
	 *
	 * @param mixed $candidate Anything passed to Registry::register().
	 * @param bool  $allow_reserved Allow reserved keys (official adapters shipped by Review Captain Pro).
	 * @return array{outcome: string, error_code: ?string, messages: list<string>, notices: list<string>, meta: array{key: ?string, label: string, version: string, contract_version: string, developer: string, capabilities: list<string>, class: string}}
	 */
	public function validate( mixed $candidate, bool $allow_reserved = false ): array {
		$meta = array(
			'key'              => null,
			'label'            => '',
			'version'          => '',
			'contract_version' => '',
			'developer'        => '',
			'capabilities'     => array(),
			'class'            => is_object( $candidate ) ? get_class( $candidate ) : gettype( $candidate ),
		);

		if ( ! $candidate instanceof ConnectorInterface ) {
			return self::result( self::OUTCOME_REJECTED, ErrorCode::CONTRACT_INCOMPATIBLE, array( __( 'Objekt implementiert ConnectorInterface nicht.', 'review-captain' ) ), array(), $meta );
		}

		try {
			$key              = $candidate->key();
			$label            = $candidate->label();
			$version          = $candidate->version();
			$contract_version = $candidate->contract_version();
			$developer        = $candidate->developer();
			$capabilities     = $candidate->capabilities();
		} catch ( \Throwable $e ) {
			return self::result( self::OUTCOME_REJECTED, ErrorCode::CONTRACT_INVALID, array( __( 'Metadaten-Methode hat einen Fehler ausgelöst.', 'review-captain' ) ), array(), $meta );
		}

		$meta['key']              = $key;
		$meta['label']            = $label;
		$meta['version']          = $version;
		$meta['contract_version'] = $contract_version;
		$meta['developer']        = $developer;

		if ( ! preg_match( self::KEY_PATTERN, $key ) ) {
			$meta['key'] = null;
			return self::result( self::OUTCOME_REJECTED, ErrorCode::DATA_INVALID_PAYLOAD, array( __( 'Ungültiger Connector-Schlüssel.', 'review-captain' ) ), array(), $meta );
		}
		if ( ! $allow_reserved && self::is_reserved( $key ) ) {
			return self::result( self::OUTCOME_REJECTED, ErrorCode::CONTRACT_INVALID, array( __( 'Der Schlüssel ist für offizielle Adapter reserviert.', 'review-captain' ) ), array(), $meta );
		}

		$errors = array();
		if ( ! self::valid_text( $label, 191 ) ) {
			$errors[] = __( 'Bezeichnung fehlt oder ist zu lang.', 'review-captain' );
		}
		if ( ! self::valid_text( $developer, 191 ) ) {
			$errors[] = __( 'Entwicklerangabe fehlt oder ist zu lang.', 'review-captain' );
		}
		if ( ! self::valid_text( $version, 64 ) ) {
			$errors[] = __( 'Version fehlt oder ist zu lang.', 'review-captain' );
		}
		if ( array() === $capabilities ) {
			$errors[] = __( 'Keine Fähigkeiten deklariert.', 'review-captain' );
		}
		if ( $errors ) {
			return self::result( self::OUTCOME_REJECTED, ErrorCode::CONTRACT_INVALID, $errors, array(), $meta );
		}

		$notices      = array();
		$incompatible = array();

		// Capabilities: deterministic de-duplication, only known strings.
		$clean = array();
		foreach ( $capabilities as $cap ) {
			if ( ! is_string( $cap ) ) {
				$incompatible[] = __( 'Fähigkeit ist kein Text.', 'review-captain' );
				continue;
			}
			if ( in_array( $cap, $clean, true ) ) {
				$notices[] = sprintf( /* translators: %s: capability */ __( 'Doppelte Fähigkeit „%s“ wurde zusammengeführt.', 'review-captain' ), $cap );
				continue;
			}
			$clean[] = $cap;
		}
		$meta['capabilities'] = $clean;

		if ( ! preg_match( self::CONTRACT_PATTERN, $contract_version, $m ) ) {
			$incompatible[] = sprintf( /* translators: %s: version */ __( 'Vertragsversion „%s“ wird nicht unterstützt (erwartet 1.x).', 'review-captain' ), $contract_version );
		} elseif ( 'x' !== $m[1] && (int) $m[1] > self::CORE_CONTRACT_MINOR ) {
			$notices[] = sprintf( /* translators: 1: connector contract, 2: core contract */ __( 'Connector meldet Vertrag %1$s, der Core unterstützt %2$s; zusätzliche Fähigkeiten werden ignoriert.', 'review-captain' ), $contract_version, self::CORE_CONTRACT );
		}

		foreach ( $clean as $cap ) {
			if ( ! isset( self::CAPABILITIES[ $cap ] ) ) {
				$incompatible[] = sprintf( /* translators: %s: capability */ __( 'Unbekannte Fähigkeit „%s“.', 'review-captain' ), $cap );
				continue;
			}
			$interface = self::CAPABILITIES[ $cap ];
			if ( ! $candidate instanceof $interface ) {
				$incompatible[] = sprintf( /* translators: %s: capability */ __( 'Fähigkeit „%s“ deklariert, aber Interface fehlt.', 'review-captain' ), $cap );
			}
		}

		if ( $incompatible ) {
			return self::result( self::OUTCOME_INCOMPATIBLE, ErrorCode::CONTRACT_INCOMPATIBLE, $incompatible, $notices, $meta );
		}
		return self::result( self::OUTCOME_OK, null, array(), $notices, $meta );
	}

	/**
	 * Whether a key is reserved.
	 *
	 * @param string $key Key.
	 */
	public static function is_reserved( string $key ): bool {
		if ( in_array( $key, self::RESERVED_KEYS, true ) ) {
			return true;
		}
		foreach ( self::RESERVED_PREFIXES as $prefix ) {
			if ( str_starts_with( $key, $prefix ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Non-empty string within a length limit.
	 *
	 * @param mixed $value Value.
	 * @param int   $max   Max length.
	 */
	private static function valid_text( mixed $value, int $max ): bool {
		return is_string( $value ) && '' !== trim( $value ) && mb_strlen( $value ) <= $max;
	}

	/**
	 * Build a result array.
	 *
	 * @param string               $outcome  Outcome.
	 * @param string|null          $code     Error code.
	 * @param list<string>         $messages Messages.
	 * @param list<string>         $notices  Notices.
	 * @param array<string, mixed> $meta     Meta.
	 * @return array{outcome: string, error_code: ?string, messages: list<string>, notices: list<string>, meta: array<string, mixed>}
	 */
	private static function result( string $outcome, ?string $code, array $messages, array $notices, array $meta ): array {
		return array(
			'outcome'    => $outcome,
			'error_code' => $code,
			'messages'   => $messages,
			'notices'    => $notices,
			'meta'       => $meta,
		);
	}
}
