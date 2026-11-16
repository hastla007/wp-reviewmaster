<?php
/**
 * Widget type registry (RC-118).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Widgets;

/**
 * Holds renderable widget types. Core registers `reviews`, `form` and `cta`; Pro and
 * other add-ons register further types on the versioned action `review_captain_widgets_v1`:
 *
 *     add_action( 'review_captain_widgets_v1', function ( WidgetRegistry $registry ) {
 *         $registry->register( 'carousel', fn( WidgetDefinition $d ): string => '…', array( 'label' => 'Karussell' ) );
 *     } );
 *
 * Renderers receive the normalised WidgetDefinition (Pro-specific attributes in `$def->raw`)
 * and must return escaped HTML. Core types cannot be replaced.
 */
final class WidgetRegistry {

	public const ACTION = 'review_captain_widgets_v1';

	/**
	 * Types reserved for the core.
	 */
	public const CORE_TYPES = array( 'reviews', 'form', 'cta' );

	/**
	 * Registered types.
	 *
	 * @var array<string, array{render: callable, label: string, owner: string}>
	 */
	private array $types = array();

	/**
	 * Whether third-party registration already ran.
	 */
	private bool $booted = false;

	/**
	 * Register a core type (bypasses the reservation).
	 *
	 * @param string   $type   Type key.
	 * @param callable $render Renderer.
	 * @param string   $label  Label.
	 */
	public function register_core( string $type, callable $render, string $label ): void {
		$this->types[ $type ] = array(
			'render' => $render,
			'label'  => $label,
			'owner'  => 'core',
		);
	}

	/**
	 * Register a widget type.
	 *
	 * @param string               $type   Type key ([a-z0-9_-], max 40 chars).
	 * @param callable             $render fn( WidgetDefinition $definition ): string.
	 * @param array<string, mixed> $args   Optional: label.
	 * @return bool False when the key is invalid, reserved or already taken.
	 */
	public function register( string $type, callable $render, array $args = array() ): bool {
		if ( ! preg_match( '/^[a-z0-9_-]{1,40}$/', $type ) || in_array( $type, self::CORE_TYPES, true ) || isset( $this->types[ $type ] ) ) {
			return false;
		}
		$this->types[ $type ] = array(
			'render' => $render,
			'label'  => isset( $args['label'] ) && is_string( $args['label'] ) ? $args['label'] : $type,
			'owner'  => 'extension',
		);
		return true;
	}

	/**
	 * Fire the registration action once.
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;
		/**
		 * Register additional widget types (contract v1).
		 *
		 * @param WidgetRegistry $registry Registry.
		 */
		do_action( self::ACTION, $this );
	}

	/**
	 * Whether the core types are registered.
	 */
	public function has_core(): bool {
		foreach ( self::CORE_TYPES as $type ) {
			if ( ! isset( $this->types[ $type ] ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Whether a type exists.
	 *
	 * @param string $type Type.
	 */
	public function has( string $type ): bool {
		$this->boot();
		return isset( $this->types[ $type ] );
	}

	/**
	 * Registered types (key → label).
	 *
	 * @return array<string, string>
	 */
	public function types(): array {
		$this->boot();
		return array_map( static fn( array $t ): string => $t['label'], $this->types );
	}

	/**
	 * Render a definition. Incompatible versions and unknown types yield an HTML comment only.
	 *
	 * @param WidgetDefinition $definition Definition.
	 */
	public function render( WidgetDefinition $definition ): string {
		$this->boot();
		if ( ! $definition->compatible ) {
			return self::comment( sprintf( 'Review Captain: Widget-Vertragsversion "%s" wird nicht unterstützt.', $definition->version ) );
		}
		if ( ! isset( $this->types[ $definition->type ] ) ) {
			return self::comment( sprintf( 'Review Captain: unbekannter Widget-Typ "%s".', $definition->type ) );
		}
		$out = ( $this->types[ $definition->type ]['render'] )( $definition );
		return is_string( $out ) ? $out : '';
	}

	/**
	 * Safe HTML comment (no "--" sequences, escaped).
	 *
	 * @param string $text Text.
	 */
	public static function comment( string $text ): string {
		return '<!-- ' . esc_html( str_replace( '--', '', $text ) ) . ' -->';
	}
}
