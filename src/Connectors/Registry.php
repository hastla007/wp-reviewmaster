<?php
/**
 * Core connector registry and contract validator (CON-008). Never fatals on incompatible connectors.
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Connectors;

use ReviewCaptain\Contracts\ConnectorInterface;

defined( 'ABSPATH' ) || exit;

final class Registry {

	public const CAPABILITIES = array(
		'experience_source'  => \ReviewCaptain\Contracts\ExperienceSourceInterface::class,
		'recipient_resolver' => \ReviewCaptain\Contracts\RecipientResolverInterface::class,
		'review_source'      => \ReviewCaptain\Contracts\ReviewSourceInterface::class,
		'request_transport'  => \ReviewCaptain\Contracts\RequestTransportInterface::class,
		'context_provider'   => \ReviewCaptain\Contracts\ContextProviderInterface::class,
	);

	private static ?Registry $instance = null;

	/** @var array<string,array<string,mixed>> key => entry (connector, metadata, status, code, capabilities, notices). */
	private array $entries = array();

	/** @var array<int,array<string,string>> Rejected registrations. */
	private array $rejected = array();

	private bool $loaded = false;

	public static function instance(): Registry {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** For tests. */
	public static function reset(): void {
		self::$instance = null;
	}

	public function register_hooks(): void {
		add_action( 'init', array( $this, 'load' ), 30 );
	}

	public function load(): void {
		if ( $this->loaded ) {
			return;
		}
		$this->loaded = true;
		/**
		 * Register local connectors (CON-002).
		 *
		 * @param Registry $registry Registry instance; call ->register( ConnectorInterface ).
		 */
		do_action( 'wp_review_captain_register_connectors_v1', $this );
	}

	/**
	 * @param mixed $connector Connector object.
	 * @return array{accepted:bool, code:string}
	 */
	public function register( $connector ): array {
		if ( ! $connector instanceof ConnectorInterface ) {
			return $this->reject( '', 'invalid_payload', 'Connector must implement ConnectorInterface.' );
		}
		$meta = $connector->metadata();
		$key  = isset( $meta['key'] ) ? (string) $meta['key'] : '';
		foreach ( array( 'key', 'label', 'version', 'contract' ) as $required ) {
			if ( empty( $meta[ $required ] ) || ! is_string( $meta[ $required ] ) ) {
				return $this->reject( $key, 'invalid_payload', "Missing metadata: {$required}." );
			}
		}
		if ( ! preg_match( '/^[a-z0-9-]{3,40}$/', $key ) ) {
			return $this->reject( $key, 'invalid_payload', 'Invalid connector key.' );
		}
		if ( isset( $this->entries[ $key ] ) ) {
			return $this->reject( $key, 'duplicate', 'A connector with this key is already registered.' );
		}

		$status       = 'active';
		$code         = 'ok';
		$notices      = array();
		$capabilities = array();

		if ( ! self::contract_compatible( (string) $meta['contract'] ) ) {
			$status = 'paused';
			$code   = 'incompatible_contract';
		}
		if ( ! empty( $meta['requires_core'] ) && version_compare( REVIEW_CAPTAIN_VERSION, (string) $meta['requires_core'], '<' ) ) {
			$status = 'paused';
			$code   = 'incompatible_contract';
		}
		foreach ( (array) ( $meta['capabilities'] ?? array() ) as $cap ) {
			$cap = (string) $cap;
			if ( ! isset( self::CAPABILITIES[ $cap ] ) ) {
				$notices[] = "Unknown capability ignored: {$cap}.";
				continue;
			}
			if ( ! is_a( $connector, self::CAPABILITIES[ $cap ] ) ) {
				$notices[] = "Capability declared but interface missing: {$cap}.";
				if ( 'ok' === $code ) {
					$code = 'invalid_payload';
				}
				continue;
			}
			$capabilities[] = $cap;
		}

		$this->entries[ $key ] = array(
			'connector'    => $connector,
			'metadata'     => array(
				'key'       => $key,
				'label'     => sanitize_text_field( (string) $meta['label'] ),
				'version'   => sanitize_text_field( (string) $meta['version'] ),
				'contract'  => sanitize_text_field( (string) $meta['contract'] ),
				'developer' => sanitize_text_field( (string) ( $meta['developer'] ?? '' ) ),
			),
			'status'       => $status,
			'code'         => $code,
			'capabilities' => 'active' === $status ? $capabilities : array(),
			'notices'      => $notices,
		);
		return array(
			'accepted' => true,
			'code'     => $code,
		);
	}

	public static function contract_compatible( string $version ): bool {
		$major = (int) strtok( $version, '.' );
		$core  = (int) strtok( ConnectorInterface::CONNECTOR_CONTRACT, '.' );
		return $major === $core && preg_match( '/^\d+\.\d+$/', $version );
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	public function all(): array {
		$this->load();
		return $this->entries;
	}

	/**
	 * Active connectors offering a capability.
	 *
	 * @return array<string,object>
	 */
	public function with_capability( string $capability ): array {
		$out = array();
		foreach ( $this->all() as $key => $entry ) {
			if ( in_array( $capability, $entry['capabilities'], true ) ) {
				$out[ $key ] = $entry['connector'];
			}
		}
		return $out;
	}

	/**
	 * @return array<int,array<string,string>>
	 */
	public function rejected(): array {
		return $this->rejected;
	}

	/**
	 * @return array{accepted:bool, code:string}
	 */
	private function reject( string $key, string $code, string $reason ): array {
		$this->rejected[] = array(
			'key'    => $key,
			'code'   => $code,
			'reason' => $reason,
		);
		return array(
			'accepted' => false,
			'code'     => $code,
		);
	}
}
