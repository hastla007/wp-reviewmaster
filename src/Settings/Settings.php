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
	 * Site the cache belongs to (Multisite: settings are per site, also after switch_to_blog()).
	 *
	 * @var int
	 */
	private int $cache_site = 0;

	/**
	 * Sites whose settings version was checked in this request (SettingsMigrator).
	 *
	 * @var array<int, true>
	 */
	private array $migrated = array();

	/**
	 * All effective values (stored merged over defaults).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function all(): array {
		$site = function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 0;
		if ( null === $this->cache || $site !== $this->cache_site ) {
			if ( ! isset( $this->migrated[ $site ] ) ) {
				// Bring stored values up to the current schema before anyone reads a new default (E5, P2-13).
				$this->migrated[ $site ] = true;
				SettingsMigrator::maybe_run();
			}
			$stored           = get_option( self::OPTION, array() );
			$this->cache      = self::merge( SettingsSchema::defaults(), is_array( $stored ) ? $stored : array() );
			$this->cache_site = $site;
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
	 * Pro-owned fields keep their stored value unless $allow_pro allows them, so a license
	 * expiry or a core-only save never clears them (SET-006). $allow_pro is either one answer
	 * for all add-on fields or a per-field check (tab, key) → bool.
	 *
	 * @param array<string, mixed> $input     Raw input keyed by tab.
	 * @param list<string>|null    $tabs      Tabs to accept (null = all).
	 * @param bool|callable        $allow_pro Whether pro-owned fields may be written (bool or callable(string $tab, string $key): bool).
	 * @return array<string, array<string, mixed>>
	 */
	public function sanitize( array $input, ?array $tabs = null, bool|callable $allow_pro = false ): array {
		$current = $this->all();
		foreach ( SettingsSchema::fields() as $tab => $fields ) {
			if ( null !== $tabs && ! in_array( $tab, $tabs, true ) ) {
				continue;
			}
			$tab_input = isset( $input[ $tab ] ) && is_array( $input[ $tab ] ) ? $input[ $tab ] : array();
			foreach ( $fields as $key => $field ) {
				if ( ( 'pro' === $field['owner'] && ! self::pro_allowed( $allow_pro, (string) $tab, (string) $key ) ) || ! empty( $field['internal'] ) ) {
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
	 * Resolve an add-on write permission for one field.
	 *
	 * @param bool|callable $allow_pro Answer for all fields or per-field check (tab, key) → bool.
	 * @param string        $tab       Tab key.
	 * @param string        $key       Field key.
	 */
	public static function pro_allowed( bool|callable $allow_pro, string $tab, string $key ): bool {
		return is_bool( $allow_pro ) ? $allow_pro : (bool) $allow_pro( $tab, $key );
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
