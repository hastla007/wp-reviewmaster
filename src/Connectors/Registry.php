<?php
/**
 * Connector registry (CON-002, CON-006, CON-008, CON-013, CON-014).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Connectors;

use ReviewCaptain\Connectors\Contract\ConnectorInterface;
use ReviewCaptain\Support\ErrorCode;

/**
 * Holds connectors registered via `review_captain_connectors_v1`.
 *
 * The core only registers, validates and reports status. Activation and the automated runtime
 * belong to Review Captain Pro (entitlement review.custom_connectors); Pro writes the activation
 * state through set_state().
 */
class Registry {

	public const HOOK = 'review_captain_connectors_v1';

	/**
	 * Option with per-connector state: key => {state: active|paused, error_code: ?string, updated_at}.
	 */
	public const OPTION_STATE = 'review_captain_connectors_state';

	/**
	 * Option with all keys ever seen: key => {label, version, first_seen, last_seen}.
	 */
	public const OPTION_KNOWN = 'review_captain_connectors_known';

	public const STATE_ACTIVE             = 'active';
	public const STATE_INACTIVE           = 'inactive';
	public const STATE_PAUSED             = 'paused';
	public const STATE_SOURCE_UNAVAILABLE = 'source_unavailable';

	/**
	 * Registered connectors.
	 *
	 * @var array<string, array{connector: ConnectorInterface, compatible: bool, error_code: ?string, messages: list<string>, notices: list<string>, meta: array<string, mixed>}>
	 */
	private array $entries = array();

	/**
	 * Rejected registrations (for diagnostics).
	 *
	 * @var list<array{key: ?string, class: string, error_code: ?string, messages: list<string>}>
	 */
	private array $rejected = array();

	private bool $fired = false;

	private ContractValidator $validator;

	/**
	 * Constructor.
	 *
	 * @param ContractValidator|null $validator Validator.
	 */
	public function __construct( ?ContractValidator $validator = null ) {
		$this->validator = $validator ?? new ContractValidator();
	}

	/**
	 * Fire the registration hook exactly once per request and update the list of known keys.
	 */
	public function fire(): void {
		if ( $this->fired ) {
			return;
		}
		$this->fired = true;
		try {
			/**
			 * Register connectors (contract v1).
			 *
			 * @param Registry $registry Registry.
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
		$this->remember_known();
	}

	/**
	 * Whether the registration hook already ran.
	 */
	public function fired(): bool {
		return $this->fired;
	}

	/**
	 * Register a connector. Never throws; the return value is not part of the public contract.
	 *
	 * @param mixed $connector Connector (should implement ConnectorInterface).
	 */
	public function register( mixed $connector ): bool {
		try {
			$result = $this->validator->validate( $connector, $this->allow_reserved( $connector ) );
		} catch ( \Throwable $e ) {
			$result = array(
				'outcome'    => ContractValidator::OUTCOME_REJECTED,
				'error_code' => ErrorCode::INTERNAL,
				'messages'   => array( __( 'Validierung fehlgeschlagen.', 'review-captain' ) ),
				'notices'    => array(),
				'meta'       => array(
					'key'   => null,
					'class' => is_object( $connector ) ? get_class( $connector ) : gettype( $connector ),
				),
			);
		}

		if ( ContractValidator::OUTCOME_REJECTED === $result['outcome'] ) {
			$this->reject( $result['meta']['key'] ?? null, (string) $result['meta']['class'], $result['error_code'], $result['messages'] );
			return false;
		}

		$key = (string) $result['meta']['key'];
		if ( isset( $this->entries[ $key ] ) ) {
			$this->reject( $key, (string) $result['meta']['class'], ErrorCode::DATA_DUPLICATE, array( __( 'Doppelter Schlüssel – die erste Registrierung bleibt bestehen.', 'review-captain' ) ) );
			return false;
		}

		/** @var ConnectorInterface $connector */
		$this->entries[ $key ] = array(
			'connector'  => $connector,
			'compatible' => ContractValidator::OUTCOME_OK === $result['outcome'],
			'error_code' => $result['error_code'],
			'messages'   => $result['messages'],
			'notices'    => $result['notices'],
			'meta'       => $result['meta'],
		);
		return true;
	}

	/**
	 * Connector by key (also incompatible ones; use is_executable() before calling methods).
	 *
	 * @param string $key Key.
	 */
	public function get( string $key ): ?ConnectorInterface {
		return isset( $this->entries[ $key ] ) ? $this->entries[ $key ]['connector'] : null;
	}

	/**
	 * Whether a key is registered in this request.
	 *
	 * @param string $key Key.
	 */
	public function has( string $key ): bool {
		return isset( $this->entries[ $key ] );
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
	 * Registration details (metadata, compatibility, notices) for diagnostics.
	 *
	 * @param string $key Key.
	 * @return array<string, mixed>|null
	 */
	public function details( string $key ): ?array {
		if ( ! isset( $this->entries[ $key ] ) ) {
			return null;
		}
		$entry = $this->entries[ $key ];
		unset( $entry['connector'] );
		return $entry;
	}

	/**
	 * Rejected registrations.
	 *
	 * @return list<array{key: ?string, class: string, error_code: ?string, messages: list<string>}>
	 */
	public function rejected(): array {
		return $this->rejected;
	}

	/**
	 * Whether a connector declares a capability and is compatible.
	 *
	 * @param string $key        Key.
	 * @param string $capability Capability.
	 */
	public function supports( string $key, string $capability ): bool {
		return isset( $this->entries[ $key ] ) && $this->entries[ $key ]['compatible'] && in_array( $capability, $this->entries[ $key ]['meta']['capabilities'], true );
	}

	/**
	 * Whether connector methods may be executed (registered, compatible, activated).
	 *
	 * @param string $key Key.
	 */
	public function is_executable( string $key ): bool {
		return self::STATE_ACTIVE === $this->status( $key )['state'];
	}

	/**
	 * Health/status for a connector (CON-013).
	 *
	 * @param string $key Key.
	 * @return array{state: string, error_code: ?string, next_action: string}
	 */
	public function status( string $key ): array {
		$stored = $this->stored_state( $key );

		if ( ! isset( $this->entries[ $key ] ) ) {
			$known = get_option( self::OPTION_KNOWN, array() );
			if ( is_array( $known ) && isset( $known[ $key ] ) ) {
				return array(
					'state'       => self::STATE_SOURCE_UNAVAILABLE,
					'error_code'  => ErrorCode::SOURCE_UNAVAILABLE,
					'next_action' => __( 'Das Quell- bzw. Connector-Plugin ist nicht aktiv. Plugin wieder aktivieren; offene Vorgänge werden erst nach erneuter Prüfung von Vertrag, Einwilligung und Fristen fortgesetzt.', 'review-captain' ),
				);
			}
			return array(
				'state'       => self::STATE_INACTIVE,
				'error_code'  => null,
				'next_action' => __( 'Connector ist nicht registriert.', 'review-captain' ),
			);
		}

		$entry = $this->entries[ $key ];
		if ( ! $entry['compatible'] ) {
			return array(
				'state'       => self::STATE_PAUSED,
				'error_code'  => ErrorCode::CONTRACT_INCOMPATIBLE,
				'next_action' => __( 'Connector auf eine mit Vertrag 1.x kompatible Version aktualisieren. Bis dahin wird er nicht ausgeführt.', 'review-captain' ),
			);
		}

		if ( self::STATE_PAUSED === $stored['state'] ) {
			$code = $stored['error_code'];
			return array(
				'state'       => self::STATE_PAUSED,
				'error_code'  => $code,
				'next_action' => null !== $code ? ErrorCode::admin_action( $code ) : __( 'Connector wurde pausiert. Fortsetzen im Connector-Manager (Review Captain Pro).', 'review-captain' ),
			);
		}
		if ( self::STATE_ACTIVE === $stored['state'] ) {
			return array(
				'state'       => self::STATE_ACTIVE,
				'error_code'  => null,
				'next_action' => __( 'Keine Aktion nötig.', 'review-captain' ),
			);
		}
		return array(
			'state'       => self::STATE_INACTIVE,
			'error_code'  => null,
			'next_action' => __( 'Neu erkannte Connectoren sind inaktiv. Die Aktivierung erfolgt im Connector-Manager von Review Captain Pro.', 'review-captain' ),
		);
	}

	/**
	 * Persist activation state. Used by the Pro connector manager; the core UI never activates.
	 *
	 * @param string      $key        Key.
	 * @param string      $state      active|paused|inactive.
	 * @param string|null $error_code Reason for a pause.
	 */
	public function set_state( string $key, string $state, ?string $error_code = null ): bool {
		if ( ! preg_match( ContractValidator::KEY_PATTERN, $key ) || ! in_array( $state, array( self::STATE_ACTIVE, self::STATE_PAUSED, self::STATE_INACTIVE ), true ) ) {
			return false;
		}
		if ( self::STATE_ACTIVE === $state && ( ! isset( $this->entries[ $key ] ) || ! $this->entries[ $key ]['compatible'] ) ) {
			return false;
		}
		$all = get_option( self::OPTION_STATE, array() );
		$all = is_array( $all ) ? $all : array();
		if ( self::STATE_INACTIVE === $state ) {
			unset( $all[ $key ] );
		} else {
			$all[ $key ] = array(
				'state'      => $state,
				'error_code' => $error_code,
				'updated_at' => gmdate( 'Y-m-d H:i:s' ),
			);
		}
		update_option( self::OPTION_STATE, $all, false );
		return true;
	}

	/**
	 * Remove a key from the list of known connectors (admin "forget").
	 *
	 * @param string $key Key.
	 */
	public function forget( string $key ): void {
		$known = get_option( self::OPTION_KNOWN, array() );
		if ( is_array( $known ) && isset( $known[ $key ] ) ) {
			unset( $known[ $key ] );
			update_option( self::OPTION_KNOWN, $known, false );
		}
		$this->set_state( $key, self::STATE_INACTIVE );
	}

	/**
	 * Connector key that owns a job type ({connector_key}.{name}), if registered and compatible.
	 *
	 * @param string $type Job type.
	 */
	public function owner_of_job_type( string $type ): ?string {
		if ( ! preg_match( '/^([a-z0-9][a-z0-9_-]{2,63})\.[a-z0-9_]{1,30}$/', $type, $m ) ) {
			return null;
		}
		return isset( $this->entries[ $m[1] ] ) && $this->entries[ $m[1] ]['compatible'] ? $m[1] : null;
	}

	/**
	 * Remember registered keys so a later absence can be shown as source_unavailable (CON-014).
	 * Only writes when something changed.
	 */
	private function remember_known(): void {
		$known   = get_option( self::OPTION_KNOWN, array() );
		$known   = is_array( $known ) ? $known : array();
		$changed = false;
		foreach ( $this->entries as $key => $entry ) {
			$label   = (string) $entry['meta']['label'];
			$version = (string) $entry['meta']['version'];
			if ( ! isset( $known[ $key ] ) || $known[ $key ]['version'] !== $version || $known[ $key ]['label'] !== $label ) {
				$known[ $key ] = array(
					'label'      => mb_substr( $label, 0, 191 ),
					'version'    => mb_substr( $version, 0, 64 ),
					'first_seen' => $known[ $key ]['first_seen'] ?? gmdate( 'Y-m-d H:i:s' ),
				);
				$changed       = true;
			}
		}
		if ( $changed ) {
			update_option( self::OPTION_KNOWN, $known, false );
		}
	}

	/**
	 * Known but currently unregistered connectors.
	 *
	 * @return array<string, array{label: string, version: string}>
	 */
	public function unavailable(): array {
		$known = get_option( self::OPTION_KNOWN, array() );
		$out   = array();
		foreach ( is_array( $known ) ? $known : array() as $key => $info ) {
			if ( ! isset( $this->entries[ $key ] ) ) {
				$out[ (string) $key ] = array(
					'label'   => (string) ( $info['label'] ?? $key ),
					'version' => (string) ( $info['version'] ?? '' ),
				);
			}
		}
		return $out;
	}

	/**
	 * Stored state for a key.
	 *
	 * @param string $key Key.
	 * @return array{state: string, error_code: ?string}
	 */
	private function stored_state( string $key ): array {
		$all = get_option( self::OPTION_STATE, array() );
		$row = is_array( $all ) && isset( $all[ $key ] ) && is_array( $all[ $key ] ) ? $all[ $key ] : array();
		return array(
			'state'      => (string) ( $row['state'] ?? self::STATE_INACTIVE ),
			'error_code' => isset( $row['error_code'] ) && is_string( $row['error_code'] ) ? $row['error_code'] : null,
		);
	}

	/**
	 * Record a rejected registration.
	 *
	 * @param string|null  $key      Key.
	 * @param string       $class    Class.
	 * @param string|null  $code     Code.
	 * @param list<string> $messages Messages.
	 */
	private function reject( ?string $key, string $class, ?string $code, array $messages ): void {
		$this->rejected[] = array(
			'key'        => $key,
			'class'      => $class,
			'error_code' => $code,
			'messages'   => $messages,
		);
	}

	/**
	 * Reserved keys may only be used by Review Captain's own adapters (namespaces ReviewCaptain*, e.g. Pro).
	 *
	 * @param mixed $connector Connector.
	 */
	private function allow_reserved( mixed $connector ): bool {
		return is_object( $connector ) && str_starts_with( get_class( $connector ), 'ReviewCaptain' );
	}
}
