<?php
/**
 * Connectors module (CON-002, CON-006, CON-008, CON-013, CON-014).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Connectors;

use ReviewCaptain\Module;
use ReviewCaptain\Plugin;

/**
 * Provides the connector registry as service 'connectors', fires the registration hook on
 * `init` (priority 20) and contributes a read-only diagnostics section.
 */
final class ConnectorsModule implements Module {

	private ?Registry $registry = null;

	/**
	 * Register hooks.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function register( Plugin $plugin ): void {
		$plugin->factory( 'connectors', fn() => $this->registry() );
		add_action( 'init', array( $this, 'fire' ), 20 );
		add_filter( 'review_captain_diagnostics_sections', array( $this, 'diagnostics_section' ) );
	}

	/**
	 * Registry instance (one per request).
	 */
	public function registry(): Registry {
		if ( null === $this->registry ) {
			$this->registry = new Registry();
		}
		return $this->registry;
	}

	/**
	 * Fire `review_captain_connectors_v1` once.
	 */
	public function fire(): void {
		$this->registry()->fire();
	}

	/**
	 * Add the diagnostics section.
	 *
	 * @param mixed $sections Sections.
	 * @return array<string, array{title: string, render: callable}>
	 */
	public function diagnostics_section( mixed $sections ): array {
		$sections               = is_array( $sections ) ? $sections : array();
		$sections['connectors'] = array(
			'title'  => __( 'Connectoren', 'review-captain' ),
			'render' => array( $this, 'render_diagnostics' ),
		);
		return $sections;
	}

	/**
	 * Render the diagnostics table (read-only; activation is part of Review Captain Pro).
	 */
	public function render_diagnostics(): void {
		$registry = $this->registry();
		$registry->fire();

		echo '<p>' . esc_html(
			sprintf(
				/* translators: %s: contract version */
				__( 'Unterstützter Connector-Vertrag: %s (Hook review_captain_connectors_v1). Aktivierung und automatische Ausführung erfolgen über Review Captain Pro.', 'review-captain' ),
				ContractValidator::CORE_CONTRACT
			)
		) . '</p>';

		$keys        = $registry->keys();
		$unavailable = $registry->unavailable();
		$rejected    = $registry->rejected();

		if ( ! $keys && ! $unavailable && ! $rejected ) {
			echo '<p>' . esc_html__( 'Es sind keine Connectoren registriert.', 'review-captain' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped rc-diagnostics-connectors"><thead><tr>';
		foreach ( array( __( 'Schlüssel', 'review-captain' ), __( 'Bezeichnung', 'review-captain' ), __( 'Version', 'review-captain' ), __( 'Vertrag', 'review-captain' ), __( 'Fähigkeiten', 'review-captain' ), __( 'Status', 'review-captain' ), __( 'Fehler / Hinweise', 'review-captain' ) ) as $head ) {
			echo '<th scope="col">' . esc_html( $head ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		foreach ( $keys as $key ) {
			$details = (array) $registry->details( $key );
			$meta    = (array) ( $details['meta'] ?? array() );
			$status  = $registry->status( $key );
			$notes   = array_merge( (array) ( $details['messages'] ?? array() ), (array) ( $details['notices'] ?? array() ) );
			$this->row(
				$key,
				(string) ( $meta['label'] ?? '' ) . ( ! empty( $meta['developer'] ) ? ' (' . (string) $meta['developer'] . ')' : '' ),
				(string) ( $meta['version'] ?? '' ),
				(string) ( $meta['contract_version'] ?? '' ),
				implode( ', ', (array) ( $meta['capabilities'] ?? array() ) ),
				$status,
				$notes
			);
		}
		foreach ( $unavailable as $key => $info ) {
			$this->row( $key, $info['label'], $info['version'], '', '', $registry->status( $key ), array() );
		}
		foreach ( $rejected as $item ) {
			$this->row(
				(string) ( $item['key'] ?? '–' ),
				$item['class'],
				'',
				'',
				'',
				array(
					'state'       => __( 'verworfen', 'review-captain' ),
					'error_code'  => $item['error_code'],
					'next_action' => __( 'Connector entspricht nicht dem Vertrag und wurde nicht registriert.', 'review-captain' ),
				),
				$item['messages']
			);
		}
		echo '</tbody></table>';
	}

	/**
	 * Render one table row (all values escaped).
	 *
	 * @param string                                                     $key     Key.
	 * @param string                                                     $label   Label.
	 * @param string                                                     $version Version.
	 * @param string                                                     $contract Contract.
	 * @param string                                                     $caps    Capabilities.
	 * @param array{state: string, error_code: ?string, next_action: string} $status Status.
	 * @param list<string>                                               $notes   Notes.
	 */
	private function row( string $key, string $label, string $version, string $contract, string $caps, array $status, array $notes ): void {
		echo '<tr>';
		echo '<td><code>' . esc_html( $key ) . '</code></td>';
		echo '<td>' . esc_html( $label ) . '</td>';
		echo '<td>' . esc_html( $version ) . '</td>';
		echo '<td>' . esc_html( $contract ) . '</td>';
		echo '<td>' . esc_html( $caps ) . '</td>';
		echo '<td>' . esc_html( self::state_label( $status['state'] ) ) . '</td>';
		echo '<td>';
		if ( null !== $status['error_code'] ) {
			echo '<code>' . esc_html( $status['error_code'] ) . '</code> ';
		}
		echo esc_html( $status['next_action'] );
		foreach ( $notes as $note ) {
			echo '<br />' . esc_html( $note );
		}
		echo '</td></tr>';
	}

	/**
	 * Translated state label.
	 *
	 * @param string $state State.
	 */
	public static function state_label( string $state ): string {
		return match ( $state ) {
			Registry::STATE_ACTIVE             => __( 'aktiv', 'review-captain' ),
			Registry::STATE_INACTIVE           => __( 'inaktiv', 'review-captain' ),
			Registry::STATE_PAUSED             => __( 'pausiert', 'review-captain' ),
			Registry::STATE_SOURCE_UNAVAILABLE => __( 'Quelle nicht verfügbar', 'review-captain' ),
			default                            => $state,
		};
	}
}
