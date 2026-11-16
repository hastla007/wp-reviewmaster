<?php
/**
 * Providers module (RC-020).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Providers;

use ReviewCaptain\Module;
use ReviewCaptain\Plugin;

/**
 * Services 'providers' (ProviderRegistry) and 'provider_sync' (SyncService); fires
 * `review_captain_providers_v1` on init (priority 20). Core ships no live provider.
 */
final class ProvidersModule implements Module {

	private ?ProviderRegistry $registry = null;

	/**
	 * Register hooks.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function register( Plugin $plugin ): void {
		$plugin->factory( 'providers', fn() => $this->registry() );
		$plugin->factory( 'provider_sync', static fn( Plugin $p ) => new SyncService( $p->reviews() ) );
		add_action( 'init', array( $this, 'fire' ), 20 );
		add_action( ProviderRegistry::HOOK, array( $this, 'register_dev_fixtures' ), 1 );
		add_filter( 'review_captain_diagnostics_sections', array( $this, 'diagnostics_section' ) );
	}

	/**
	 * Registry (one per request).
	 */
	public function registry(): ProviderRegistry {
		if ( null === $this->registry ) {
			$this->registry = new ProviderRegistry();
		}
		return $this->registry;
	}

	/**
	 * Fire the registration hook once.
	 */
	public function fire(): void {
		$this->registry()->fire();
	}

	/**
	 * Whether the fixture provider may be registered (never in production).
	 */
	public static function dev_fixtures_enabled(): bool {
		$env = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
		return defined( 'REVIEW_CAPTAIN_DEV_FIXTURES' ) && true === REVIEW_CAPTAIN_DEV_FIXTURES && in_array( $env, array( 'local', 'development' ), true );
	}

	/**
	 * Register the fixture provider in local development only.
	 *
	 * @param ProviderRegistry $registry Registry.
	 */
	public function register_dev_fixtures( $registry ): void {
		$dir = REVIEW_CAPTAIN_DIR . 'tests/fixtures/providers/demo';
		if ( $registry instanceof ProviderRegistry && self::dev_fixtures_enabled() && is_dir( $dir ) ) {
			$registry->register( new FixtureProvider( $dir ) );
		}
	}

	/**
	 * Diagnostics section.
	 *
	 * @param mixed $sections Sections.
	 * @return array<string, array{title: string, render: callable}>
	 */
	public function diagnostics_section( mixed $sections ): array {
		$sections              = is_array( $sections ) ? $sections : array();
		$sections['providers'] = array(
			'title'  => __( 'Review-Provider', 'review-captain' ),
			'render' => array( $this, 'render_diagnostics' ),
		);
		return $sections;
	}

	/**
	 * Render provider list.
	 */
	public function render_diagnostics(): void {
		$registry = $this->registry();
		$registry->fire();
		echo '<p>' . esc_html(
			sprintf(
				/* translators: %s: contract version */
				__( 'Unterstützter Provider-Vertrag: %s (Hook review_captain_providers_v1).', 'review-captain' ),
				ProviderRegistry::CORE_CONTRACT
			)
		) . '</p>';
		$keys = $registry->keys();
		if ( ! $keys && ! $registry->rejected() ) {
			echo '<p>' . esc_html__( 'Es sind keine Provider registriert.', 'review-captain' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Schlüssel', 'review-captain' ) . '</th><th>' . esc_html__( 'Plattform', 'review-captain' ) . '</th><th>' . esc_html__( 'Version', 'review-captain' ) . '</th><th>' . esc_html__( 'Vertrag', 'review-captain' ) . '</th><th>' . esc_html__( 'Fähigkeiten', 'review-captain' ) . '</th><th>' . esc_html__( 'Status', 'review-captain' ) . '</th></tr></thead><tbody>';
		foreach ( $keys as $key ) {
			$d      = (array) $registry->details( $key );
			$meta   = (array) ( $d['meta'] ?? array() );
			$status = $registry->status( $key );
			echo '<tr><td><code>' . esc_html( $key ) . '</code></td><td>' . esc_html( (string) ( $meta['platform'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $meta['version'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $meta['contract_version'] ?? '' ) ) . '</td><td>' . esc_html( implode( ', ', (array) ( $meta['capabilities'] ?? array() ) ) ) . '</td><td>' . esc_html( $status['state'] . ( $status['error_code'] ? ' (' . $status['error_code'] . ')' : '' ) );
			foreach ( (array) ( $d['messages'] ?? array() ) as $msg ) {
				echo '<br />' . esc_html( (string) $msg );
			}
			echo '</td></tr>';
		}
		foreach ( $registry->rejected() as $item ) {
			echo '<tr><td><code>' . esc_html( (string) ( $item['key'] ?? '–' ) ) . '</code></td><td colspan="4">' . esc_html( $item['class'] ) . '</td><td>' . esc_html( $item['error_code'] . ': ' . implode( ' ', $item['messages'] ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}
}
