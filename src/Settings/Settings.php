<?php
/**
 * Settings access with defaults, sanitizing and override resolution (SET-003/004/005).
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Settings;

defined( 'ABSPATH' ) || exit;

final class Settings {

	public const VERSION_OPTION = 'review_captain_settings_version';
	public const VERSION        = 1;

	public static function option_name( string $group ): string {
		return 'review_captain_' . $group;
	}

	/**
	 * Read a setting. Missing values fall back to defaults without writing.
	 *
	 * @return mixed
	 */
	public static function get( string $key ) {
		$fields = Schema::fields();
		if ( ! isset( $fields[ $key ] ) ) {
			return null;
		}
		$field  = $fields[ $key ];
		$stored = get_option( self::option_name( $field['group'] ), array() );
		$value  = ( is_array( $stored ) && array_key_exists( $key, $stored ) ) ? $stored[ $key ] : self::default_value( $key );
		if ( ! empty( $field['locked'] ) ) {
			$value = $field['default'];
		}
		return $value;
	}

	/**
	 * Defaults that depend on WordPress state.
	 *
	 * @return mixed
	 */
	public static function default_value( string $key ) {
		$fields = Schema::fields();
		switch ( $key ) {
			case 'brand_name':
			case 'sender_name':
				return (string) get_bloginfo( 'name' );
			case 'default_locale':
				return (string) get_locale();
			case 'date_format':
				return (string) get_option( 'date_format', 'Y-m-d' );
			case 'test_recipient':
				return (string) get_option( 'admin_email', '' );
		}
		return $fields[ $key ]['default'] ?? null;
	}

	/**
	 * All values of a group including defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function group( string $group ): array {
		$values = array();
		foreach ( array_keys( Schema::fields_for_group( $group ) ) as $key ) {
			$values[ $key ] = self::get( $key );
		}
		return $values;
	}

	/**
	 * Sanitize and validate a submitted group. Unknown keys are dropped; invalid values keep the previous value.
	 *
	 * @param array<string,mixed> $input Raw input.
	 * @return array{values: array<string,mixed>, errors: array<string,string>}
	 */
	public static function sanitize_group( string $group, array $input ): array {
		$values = array();
		$errors = array();
		foreach ( Schema::fields_for_group( $group ) as $key => $field ) {
			if ( ! empty( $field['locked'] ) || ! empty( $field['internal'] ) ) {
				$values[ $key ] = self::get( $key );
				continue;
			}
			$raw = $input[ $key ] ?? null;
			switch ( $field['type'] ) {
				case 'bool':
					$values[ $key ] = ! empty( $raw );
					break;
				case 'int':
					$int = is_numeric( $raw ) ? (int) $raw : null;
					if ( null === $int || $int < $field['min'] || $int > $field['max'] ) {
						/* translators: 1: minimum, 2: maximum */
						$errors[ $key ] = sprintf( __( 'Please enter a number between %1$d and %2$d.', 'review-captain' ), $field['min'], $field['max'] );
						$values[ $key ] = self::get( $key );
					} else {
						$values[ $key ] = $int;
					}
					break;
				case 'enum':
					$raw            = is_string( $raw ) ? $raw : '';
					$values[ $key ] = in_array( $raw, $field['options'], true ) ? $raw : self::get( $key );
					break;
				case 'email':
					$raw = is_string( $raw ) ? trim( wp_unslash( $raw ) ) : '';
					if ( '' !== $raw && ! is_email( $raw ) ) {
						$errors[ $key ] = __( 'Please enter a valid email address.', 'review-captain' );
						$values[ $key ] = self::get( $key );
					} else {
						$values[ $key ] = sanitize_email( $raw );
					}
					break;
				case 'time':
					$raw            = is_string( $raw ) ? $raw : '';
					$values[ $key ] = preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $raw ) ? $raw : self::get( $key );
					break;
				case 'textarea':
					$values[ $key ] = is_string( $raw ) ? sanitize_textarea_field( wp_unslash( $raw ) ) : '';
					break;
				default:
					$values[ $key ] = is_string( $raw ) ? sanitize_text_field( wp_unslash( $raw ) ) : '';
			}
		}
		if ( 'requests' === $group && $values['send_window_start'] >= $values['send_window_end'] ) {
			$errors['send_window_end'] = __( 'The end of the send window must be after its start.', 'review-captain' );
			$values['send_window_end'] = self::get( 'send_window_end' );
			$values['send_window_start'] = self::get( 'send_window_start' );
		}
		return array(
			'values' => $values,
			'errors' => $errors,
		);
	}

	/**
	 * Persist a group and return changed keys.
	 *
	 * @param array<string,mixed> $values Sanitized values.
	 * @return string[] Changed keys.
	 */
	public static function save_group( string $group, array $values ): array {
		$before  = self::group( $group );
		$changed = array();
		foreach ( $values as $key => $value ) {
			if ( ( $before[ $key ] ?? null ) !== $value ) {
				$changed[] = $key;
			}
		}
		$autoload = in_array( $group, array( 'general', 'display', 'submissions' ), true );
		update_option( self::option_name( $group ), $values, $autoload );
		update_option( self::VERSION_OPTION, self::VERSION, false );
		return $changed;
	}

	public static function reset_group( string $group ): void {
		delete_option( self::option_name( $group ) );
	}

	public static function reset_all(): void {
		foreach ( Schema::GROUPS as $group ) {
			self::reset_group( $group );
		}
	}

	/**
	 * Resolve the effective value for a context (SET-004).
	 *
	 * @param array<string,array<string,mixed>> $overrides Level => [key => value], levels: profile|campaign|form|widget.
	 * @return array{value: mixed, source: string}
	 */
	public static function effective( string $key, array $overrides = array() ): array {
		$fields = Schema::fields();
		$value  = self::get( $key );
		$source = 'global';
		if ( ! isset( $fields[ $key ] ) ) {
			return array(
				'value'  => null,
				'source' => 'none',
			);
		}
		$field = $fields[ $key ];
		foreach ( array( 'profile', 'campaign', 'form', 'widget' ) as $level ) {
			if ( ! isset( $overrides[ $level ] ) || ! array_key_exists( $key, $overrides[ $level ] ) ) {
				continue;
			}
			if ( empty( $field['overridable'] ) || ! in_array( $level, $field['overridable'], true ) ) {
				continue;
			}
			$candidate = $overrides[ $level ][ $key ];
			if ( self::override_allowed( $field, $value, $candidate ) ) {
				$value  = $candidate;
				$source = $level;
			}
		}
		return array(
			'value'  => $value,
			'source' => $source,
		);
	}

	/**
	 * @param array<string,mixed> $field Field definition.
	 * @param mixed               $current Current effective value.
	 * @param mixed               $candidate Override candidate.
	 */
	private static function override_allowed( array $field, $current, $candidate ): bool {
		switch ( $field['override_rule'] ?? '' ) {
			case 'lower':
				return (int) $candidate <= (int) $current && (int) $candidate >= (int) ( $field['min'] ?? PHP_INT_MIN );
			case 'higher':
				return (int) $candidate >= (int) $current;
			case 'only_disable':
				return false === (bool) $candidate;
			case 'stricter':
				// For default_status: only 'pending' is stricter than 'approved'.
				return 'pending' === $candidate || $candidate === $current;
			case 'narrower':
				// Time windows: caller validates start/end pairs; individual values must stay inside the global window.
				$start = (string) self::get( 'send_window_start' );
				$end   = (string) self::get( 'send_window_end' );
				return is_string( $candidate ) && $candidate >= $start && $candidate <= $end;
			default:
				return true;
		}
	}
}
