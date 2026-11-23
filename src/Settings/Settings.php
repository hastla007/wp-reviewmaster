<?php
/**
 * Settings store (SET-003/004/005).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Settings;

/**
 * Reads and writes the `review_captain_settings` option with defaults merged in.
 */
class Settings {

	public const OPTION         = 'review_captain_settings';
	public const OPTION_VERSION = 'review_captain_settings_version';

	/**
	 * Cached values.
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	private ?array $cache = null;

	/**
	 * All effective values (stored merged over defaults).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function all(): array {
		if ( null === $this->cache ) {
			$stored      = get_option( self::OPTION, array() );
			$this->cache = self::merge( SettingsSchema::defaults(), is_array( $stored ) ? $stored : array() );
		}
		return $this->cache;
	}

	/**
	 * Get a value.
	 *
	 * @param string $tab      Tab.
	 * @param string $key      Key.
	 * @param mixed  $fallback Fallback if unknown.
	 */
	public function get( string $tab, string $key, mixed $fallback = null ): mixed {
		$all = $this->all();
		return $all[ $tab ][ $key ] ?? $fallback;
	}

	/**
	 * Resolve an effective value with object-level overrides (SET-004):
	 * override wins if it is valid for the field, otherwise the global value applies.
	 *
	 * @param string               $tab       Tab.
	 * @param string               $key       Key.
	 * @param array<string, mixed> $overrides Object-level overrides keyed by field key.
	 * @return array{value: mixed, source: string}
	 */
	public function effective( string $tab, string $key, array $overrides ): array {
		$fields = SettingsSchema::fields();
		if ( array_key_exists( $key, $overrides ) && isset( $fields[ $tab ][ $key ] ) ) {
			$field     = $fields[ $tab ][ $key ];
			$sanitized = SettingsSchema::sanitize_value( $field, $overrides[ $key ] );
			if ( $sanitized === $overrides[ $key ] || ( 'bool' === $field['type'] && is_bool( $overrides[ $key ] ) ) ) {
				return array(
					'value'  => $sanitized,
					'source' => 'override',
				);
			}
		}
		return array(
			'value'  => $this->get( $tab, $key ),
			'source' => 'global',
		);
	}

	/**
	 * Sanitize a (partial) input for given tabs and merge into current values.
	 * Pro-owned fields keep their stored value unless $allow_pro is true, so a license
	 * expiry or a core-only save never clears them (SET-006).
	 *
	 * @param array<string, mixed> $input     Raw input keyed by tab.
	 * @param list<string>|null    $tabs      Tabs to accept (null = all).
	 * @param bool                 $allow_pro Whether pro-owned fields may be written.
	 * @return array<string, array<string, mixed>>
	 */
	public function sanitize( array $input, ?array $tabs = null, bool $allow_pro = false ): array {
		$current = $this->all();
		foreach ( SettingsSchema::fields() as $tab => $fields ) {
			if ( null !== $tabs && ! in_array( $tab, $tabs, true ) ) {
				continue;
			}
			$tab_input = isset( $input[ $tab ] ) && is_array( $input[ $tab ] ) ? $input[ $tab ] : array();
			foreach ( $fields as $key => $field ) {
				if ( ( 'pro' === $field['owner'] && ! $allow_pro ) || ! empty( $field['internal'] ) ) {
					continue;
				}
				if ( 'bool' === $field['type'] ) {
					$current[ $tab ][ $key ] = SettingsSchema::sanitize_value( $field, $tab_input[ $key ] ?? false );
				} elseif ( array_key_exists( $key, $tab_input ) ) {
					$current[ $tab ][ $key ] = SettingsSchema::sanitize_value( $field, $tab_input[ $key ] );
				}
			}
		}
		return $current;
	}

	/**
	 * Persist values. Returns list of changed "tab.key" strings.
	 *
	 * @param array<string, array<string, mixed>> $values Values (already sanitized).
	 * @return list<string>
	 */
	public function save( array $values ): array {
		$before  = $this->all();
		$changed = array();
		foreach ( $values as $tab => $fields ) {
			foreach ( (array) $fields as $key => $value ) {
				if ( ( $before[ $tab ][ $key ] ?? null ) !== $value ) {
					$changed[] = $tab . '.' . $key;
				}
			}
		}
		update_option( self::OPTION, $values, true );
		update_option( self::OPTION_VERSION, SettingsSchema::VERSION, false );
		$this->cache = null;
		return $changed;
	}

	/**
	 * Reset tabs to defaults (never touches review data; SET-005).
	 *
	 * @param list<string>|null $tabs Tabs, null = all core tabs.
	 */
	public function reset( ?array $tabs = null ): void {
		$values   = $this->all();
		$defaults = SettingsSchema::defaults();
		foreach ( SettingsSchema::fields() as $tab => $fields ) {
			if ( null !== $tabs && ! in_array( $tab, $tabs, true ) ) {
				continue;
			}
			foreach ( $fields as $key => $field ) {
				if ( 'core' === $field['owner'] ) {
					$values[ $tab ][ $key ] = $defaults[ $tab ][ $key ];
				}
			}
		}
		$this->save( $values );
	}

	/**
	 * Flush cache (after external update_option).
	 */
	public function flush(): void {
		$this->cache = null;
	}

	/**
	 * Recursive merge keeping only known tabs from defaults plus unknown stored tabs (Pro).
	 *
	 * @param array<string, mixed> $defaults Defaults.
	 * @param array<string, mixed> $stored   Stored.
	 * @return array<string, array<string, mixed>>
	 */
	private static function merge( array $defaults, array $stored ): array {
		foreach ( $stored as $tab => $fields ) {
			if ( ! is_array( $fields ) ) {
				continue;
			}
			foreach ( $fields as $key => $value ) {
				$defaults[ $tab ][ $key ] = $value;
			}
		}
		return $defaults;
	}
}
