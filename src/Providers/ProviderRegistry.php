<?php
/**
 * Provider registry (RC-020).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Providers;

use ReviewCaptain\Domain\Platform;
use ReviewCaptain\Providers\Contract\ProviderInterface;
use ReviewCaptain\Support\ErrorCode;

/**
 * Holds providers registered via `review_captain_providers_v1`. register() never throws;
 * incompatible providers are kept as paused with rc.contract.incompatible.
 */
class ProviderRegistry {

	public const HOOK = 'review_captain_providers_v1';

	public const KEY_PATTERN = '/^[a-z0-9][a-z0-9_.-]{1,63}$/';

	public const CONTRACT_PATTERN = '/^1\.(\d+|x)$/';

	public const CORE_CONTRACT = '1.0';

	/**
	 * Write capabilities are known but never executed by the v1 core.
	 */
	public const WRITE_CAPABILITIES = array( 'invite', 'reply' );

	/**
	 * Registered providers.
	 *
	 * @var array<string, array{provider: ProviderInterface, compatible: bool, error_code: ?string, messages: list<string>, meta: array<string, mixed>}>
	 */
	private array $entries = array();

	/**
	 * Rejected registrations.
	 *
	 * @var list<array{key: ?string, class: string, error_code: string, messages: list<string>}>
	 */
	private array $rejected = array();

	private bool $fired = false;

	/**
	 * Fire the registration hook once per request.
	 */
	public function fire(): void {
		if ( $this->fired ) {
			return;
		}
		$this->fired = true;
		try {
			/**
			 * Register review providers (contract v1).
			 *
			 * @param ProviderRegistry $registry Registry.
			 */
			do_action( self::HOOK, $this );
		} catch ( \Throwable $e ) {
			$this->rejected[] = array(
				'key'        => null,
				'class'      => get_class( $e ),
				'error_code' => ErrorCode::INTERNAL,
				'messages'   => array( __( 'Ein Registrierungs-Callback hat einen Fehler ausgelöst.', 'review-captain' ) ),
			);
		}
	}

	/**
	 * Register a provider. Never throws.
	 *
	 * @param mixed $provider Provider.
	 */
	public function register( mixed $provider ): bool {
		$class = is_object( $provider ) ? get_class( $provider ) : gettype( $provider );
		if ( ! $provider instanceof ProviderInterface ) {
			return $this->reject( null, $class, ErrorCode::CONTRACT_INCOMPATIBLE, __( 'Objekt implementiert ProviderInterface nicht.', 'review-captain' ) );
		}
		try {
			$key              = $provider->key();
			$label            = $provider->label();
			$version          = $provider->version();
			$platform         = $provider->platform();
			$contract_version = $provider->contract_version();
			$capabilities     = $provider->capabilities();
		} catch ( \Throwable $e ) {
			return $this->reject( null, $class, ErrorCode::CONTRACT_INVALID, __( 'Metadaten-Methode hat einen Fehler ausgelöst.', 'review-captain' ) );
		}
		if ( ! preg_match( self::KEY_PATTERN, $key ) ) {
			return $this->reject( null, $class, ErrorCode::CONTRACT_INVALID, __( 'Ungültiger Provider-Schlüssel.', 'review-captain' ) );
		}
		if ( isset( $this->entries[ $key ] ) ) {
			return $this->reject( $key, $class, ErrorCode::DATA_DUPLICATE, __( 'Doppelter Schlüssel – die erste Registrierung bleibt bestehen.', 'review-captain' ) );
		}
		if ( '' === trim( $label ) || mb_strlen( $label ) > 191 || '' === trim( $version ) || mb_strlen( $version ) > 64 ) {
			return $this->reject( $key, $class, ErrorCode::CONTRACT_INVALID, __( 'Bezeichnung oder Version fehlt bzw. ist zu lang.', 'review-captain' ) );
		}
		if ( Platform::LOCAL === $platform ) {
			return $this->reject( $key, $class, ErrorCode::CONTRACT_INVALID, __( 'Provider dürfen nicht die Plattform „local“ verwenden.', 'review-captain' ) );
		}

		$messages = array();
		if ( ! preg_match( self::CONTRACT_PATTERN, $contract_version ) ) {
			$messages[] = sprintf( /* translators: %s: version */ __( 'Vertragsversion „%s“ wird nicht unterstützt (erwartet 1.x).', 'review-captain' ), $contract_version );
		}
		$caps = array();
		foreach ( $capabilities as $cap ) {
			if ( ! is_string( $cap ) || ! in_array( $cap, ProviderInterface::CAPABILITIES, true ) ) {
				$messages[] = sprintf( /* translators: %s: capability */ __( 'Unbekannte Fähigkeit „%s“.', 'review-captain' ), is_scalar( $cap ) ? (string) $cap : gettype( $cap ) );
				continue;
			}
			$caps[] = $cap;
		}
		$caps = array_values( array_unique( $caps ) );
		if ( ! in_array( 'read_reviews', $caps, true ) ) {
			$messages[] = __( 'Pflichtfähigkeit „read_reviews“ fehlt.', 'review-captain' );
		}
		$notices = array();
		if ( array_intersect( $caps, self::WRITE_CAPABILITIES ) ) {
			$notices[] = __( 'Schreibfähigkeiten (invite, reply) werden in Vertrag 1.0 nicht ausgeführt.', 'review-captain' );
		}

		$this->entries[ $key ] = array(
			'provider'   => $provider,
			'compatible' => ! $messages,
			'error_code' => $messages ? ErrorCode::CONTRACT_INCOMPATIBLE : null,
			'messages'   => array_merge( $messages, $notices ),
			'meta'       => array(
				'key'              => $key,
				'label'            => $label,
				'version'          => $version,
				'platform'         => $platform->value,
				'contract_version' => $contract_version,
				'capabilities'     => $caps,
				'class'            => $class,
			),
		);
		return true;
	}

	/**
	 * Provider by key (only compatible providers).
	 *
	 * @param string $key Key.
	 */
	public function get( string $key ): ?ProviderInterface {
		return isset( $this->entries[ $key ] ) && $this->entries[ $key ]['compatible'] ? $this->entries[ $key ]['provider'] : null;
	}

	/**
	 * Registered keys.
	 *
	 * @return list<string>
	 */
	public function keys(): array {
		return array_keys( $this->entries );
	}

	/**
	 * Registration details for diagnostics.
	 *
	 * @param string $key Key.
	 * @return array<string, mixed>|null
	 */
	public function details( string $key ): ?array {
		if ( ! isset( $this->entries[ $key ] ) ) {
			return null;
		}
		$entry = $this->entries[ $key ];
		unset( $entry['provider'] );
		return $entry;
	}

	/**
	 * Rejected registrations.
	 *
	 * @return list<array{key: ?string, class: string, error_code: string, messages: list<string>}>
	 */
	public function rejected(): array {
		return $this->rejected;
	}

	/**
	 * Status of a provider.
	 *
	 * @param string $key Key.
	 * @return array{state: string, error_code: ?string, next_action: string}
	 */
	public function status( string $key ): array {
		if ( ! isset( $this->entries[ $key ] ) ) {
			return array(
				'state'       => 'source_unavailable',
				'error_code'  => ErrorCode::SOURCE_UNAVAILABLE,
				'next_action' => __( 'Provider ist nicht registriert (Add-on inaktiv?).', 'review-captain' ),
			);
		}
		if ( ! $this->entries[ $key ]['compatible'] ) {
			return array(
				'state'       => 'paused',
				'error_code'  => ErrorCode::CONTRACT_INCOMPATIBLE,
				'next_action' => __( 'Provider auf eine mit Vertrag 1.x kompatible Version aktualisieren.', 'review-captain' ),
			);
		}
		return array(
			'state'       => 'active',
			'error_code'  => null,
			'next_action' => __( 'Keine Aktion nötig.', 'review-captain' ),
		);
	}

	/**
	 * Record a rejection.
	 *
	 * @param string|null $key     Key.
	 * @param string      $class   Class.
	 * @param string      $code    Code.
	 * @param string      $message Message.
	 */
	private function reject( ?string $key, string $class, string $code, string $message ): bool {
		$this->rejected[] = array(
			'key'        => $key,
			'class'      => $class,
			'error_code' => $code,
			'messages'   => array( $message ),
		);
		return false;
	}
}
