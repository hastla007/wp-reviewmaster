<?php
/**
 * Configuration export/import with preview, validation and rollback (SET-018).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Admin;

use ReviewCaptain\Settings\Settings;
use ReviewCaptain\Settings\SettingsSchema;

/**
 * Pure logic, no output. Export never contains secrets or personal data:
 * fields with privacy 'personal', fields with 'export' => false (the uninstall switch, S10) and
 * keys that look like secrets are omitted; an import never touches them either.
 * Contact data of the operator (privacy 'config', P1: notification recipients, brand name and
 * add-on fields such as the sender address) is only exported on request ("mit Kontaktangaben
 * exportieren", P1-8); an import takes such fields when the file contains them.
 */
final class ConfigTransfer {

	public const SCHEMA         = 'review_captain.settings.v1';
	public const BACKUP_OPTION  = 'review_captain_settings_backup';
	public const SECRET_PATTERN = '/(secret|token|key|password|passwd)/i';

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings.
	 */
	public function __construct( private Settings $settings ) {}

	/**
	 * Whether a field may leave the site in a configuration export (or be taken from an import).
	 *
	 * @param string               $key           Key.
	 * @param array<string, mixed> $field         Field definition.
	 * @param bool                 $with_contacts Include contact data of the operator (privacy 'config', P1-8).
	 */
	public static function exportable( string $key, array $field, bool $with_contacts = true ): bool {
		if ( ! $with_contacts && self::is_contact( $field ) ) {
			return false;
		}
		if ( 'personal' === ( $field['privacy'] ?? 'none' ) || ! empty( $field['internal'] ) || false === ( $field['export'] ?? true ) ) {
			return false;
		}
		if ( preg_match( self::SECRET_PATTERN, $key ) ) {
			return false;
		}
		return 'secret' !== ( $field['type'] ?? '' );
	}

	/**
	 * Whether a field holds contact data of the operator (P1, privacy 'config').
	 *
	 * @param array<string, mixed> $field Field definition.
	 */
	public static function is_contact( array $field ): bool {
		return 'config' === ( $field['privacy'] ?? 'none' );
	}

	/**
	 * Build the export document. Contact data (privacy 'config') only with $with_contacts (P1-8).
	 *
	 * @param bool $with_contacts Include contact data of the operator.
	 * @return array<string, mixed>
	 */
	public function export( bool $with_contacts = false ): array {
		$values = $this->settings->all();
		$out    = array();
		foreach ( SettingsSchema::fields() as $tab => $fields ) {
			if ( preg_match( self::SECRET_PATTERN, (string) $tab ) ) {
				continue;
			}
			foreach ( $fields as $key => $field ) {
				if ( ! self::exportable( (string) $key, $field, $with_contacts ) ) {
					continue;
				}
				$out[ $tab ][ $key ] = $values[ $tab ][ $key ] ?? $field['default'];
			}
		}
		return array(
			'schema'         => self::SCHEMA,
			'schema_version' => SettingsSchema::VERSION,
			'plugin_version' => defined( 'REVIEW_CAPTAIN_VERSION' ) ? REVIEW_CAPTAIN_VERSION : '',
			'exported_at'    => gmdate( 'c' ),
			'with_contacts'  => $with_contacts,
			'settings'       => $out,
		);
	}

	/**
	 * Parse and validate an import document. Returns the sanitized target values and a diff, or null.
	 *
	 * Rules: schema must match and schema_version must not be newer than ours; only known,
	 * exportable fields of the allowed tabs are taken; values pass the same sanitizer as the
	 * settings page; add-on fields (owner 'pro') are only taken while the add-on lets them be
	 * written ($allow_pro, per field as on the settings page), otherwise they stay untouched; unknown roles
	 * are dropped. Fields of a failed cross-field check (SettingsSchema::cross_check(), e.g.
	 * minimum ≥ maximum text length) keep their current values; `kept` lists those checks.
	 *
	 * @param string            $json         JSON document.
	 * @param list<string>|null $allowed_tabs Tabs the user may write (null = all).
	 * @param bool|callable     $allow_pro    Whether add-on fields may be written (bool or callable(string $tab, string $key): bool).
	 * @return array{values: array<string, array<string, mixed>>, diff: list<array{tab: string, key: string, old: mixed, new: mixed}>, tabs: list<string>, kept: list<array{tab: string, fields: list<string>, message: string}>}|null
	 */
	public function preview( string $json, ?array $allowed_tabs = null, bool|callable $allow_pro = false ): ?array {
		$doc = json_decode( $json, true );
		if ( ! is_array( $doc ) || self::SCHEMA !== ( $doc['schema'] ?? null ) ) {
			return null;
		}
		$version = $doc['schema_version'] ?? null;
		if ( ! is_int( $version ) || $version < 1 || $version > SettingsSchema::VERSION ) {
			return null;
		}
		if ( ! isset( $doc['settings'] ) || ! is_array( $doc['settings'] ) ) {
			return null;
		}

		$current = $this->settings->all();
		$schema  = SettingsSchema::fields();
		$input   = array();
		$tabs    = array();
		foreach ( $doc['settings'] as $tab => $values ) {
			$tab = (string) $tab;
			if ( ! isset( $schema[ $tab ] ) || ! is_array( $values ) ) {
				continue;
			}
			if ( null !== $allowed_tabs && ! in_array( $tab, $allowed_tabs, true ) ) {
				continue;
			}
			// Start from current values so keys missing in the file keep their value (bools included).
			$input[ $tab ] = $current[ $tab ] ?? array();
			foreach ( $values as $key => $value ) {
				$key = (string) $key;
				if ( ! isset( $schema[ $tab ][ $key ] ) || ! self::exportable( $key, $schema[ $tab ][ $key ] ) ) {
					continue;
				}
				if ( 'array' === $schema[ $tab ][ $key ]['type'] && str_ends_with( $key, '_roles' ) && is_array( $value ) ) {
					$value = array_values( array_filter( $value, static fn( $role ) => is_string( $role ) && null !== get_role( $role ) ) );
					$value = array_values( array_unique( array_merge( array( 'administrator' ), $value ) ) );
				}
				$input[ $tab ][ $key ] = $value;
			}
			$tabs[] = $tab;
		}
		if ( ! $tabs ) {
			return array(
				'values' => $current,
				'diff'   => array(),
				'tabs'   => array(),
				'kept'   => array(),
			);
		}

		$values = $this->settings->sanitize( $input, $tabs, $allow_pro );
		$kept   = array();
		foreach ( $tabs as $tab ) {
			foreach ( SettingsSchema::cross_check( $tab, (array) ( $values[ $tab ] ?? array() ) ) as $check ) {
				$involved = array_values( array_filter( array_map( 'strval', (array) ( $check['fields'] ?? array() ) ), static fn( string $key ): bool => isset( $schema[ $tab ][ $key ] ) ) );
				$reverted = array();
				foreach ( $involved as $key ) {
					if ( ( $values[ $tab ][ $key ] ?? null ) !== ( $current[ $tab ][ $key ] ?? null ) ) {
						$values[ $tab ][ $key ] = $current[ $tab ][ $key ] ?? $schema[ $tab ][ $key ]['default'];
						$reverted[]             = $key;
					}
				}
				if ( $reverted ) {
					$kept[] = array(
						'tab'     => $tab,
						'fields'  => $involved,
						'message' => (string) ( $check['message'] ?? '' ),
					);
				}
			}
		}
		if ( in_array( 'privacy', $tabs, true ) ) {
			// SET-RC-20: an import can never switch on data deletion without the typed confirmation.
			$values['privacy'] = SettingsSchema::guard_uninstall( (array) ( $values['privacy'] ?? array() ), (array) ( $current['privacy'] ?? array() ) )[0];
		}
		$diff = array();
		foreach ( $tabs as $tab ) {
			foreach ( $schema[ $tab ] as $key => $field ) {
				$old = $current[ $tab ][ $key ] ?? null;
				$new = $values[ $tab ][ $key ] ?? null;
				if ( $old !== $new ) {
					$diff[] = array(
						'tab' => $tab,
						'key' => (string) $key,
						'old' => $old,
						'new' => $new,
					);
				}
			}
		}
		return array(
			'values' => $values,
			'diff'   => $diff,
			'tabs'   => $tabs,
			'kept'   => $kept,
		);
	}

	/**
	 * Store a rollback snapshot of the current stored option.
	 */
	public function backup(): void {
		update_option(
			self::BACKUP_OPTION,
			array(
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
				'version'    => SettingsSchema::VERSION,
				'settings'   => get_option( Settings::OPTION, array() ),
			),
			false
		);
	}

	/**
	 * Current backup, if any.
	 *
	 * @return array{created_at: string, version: int, settings: array<string, mixed>}|null
	 */
	public function get_backup(): ?array {
		$backup = get_option( self::BACKUP_OPTION, null );
		return is_array( $backup ) && isset( $backup['settings'] ) && is_array( $backup['settings'] ) ? $backup : null;
	}

	/**
	 * Apply previewed values (after a backup). Returns changed "tab.key" list.
	 *
	 * @param array<string, array<string, mixed>> $values Sanitized values from preview().
	 * @return list<string>
	 */
	public function apply( array $values ): array {
		$this->backup();
		return $this->settings->save( $values );
	}

	/**
	 * Restore the backup. Returns changed keys or null when no backup exists.
	 *
	 * @return list<string>|null
	 */
	public function rollback(): ?array {
		$backup = $this->get_backup();
		if ( null === $backup ) {
			return null;
		}
		$current  = get_option( Settings::OPTION, array() );
		$restored = $backup['settings'];
		if ( (int) ( $backup['version'] ?? 1 ) < SettingsSchema::VERSION ) {
			// A snapshot from before a settings migration keeps that site's behaviour (E5, P2-13).
			$restored = \ReviewCaptain\Settings\SettingsMigrator::migrate( $restored, max( 1, (int) ( $backup['version'] ?? 1 ) ) );
		}
		$changed = $this->settings->save( $restored );
		// Keep the replaced state as the new backup so a rollback can itself be undone.
		update_option(
			self::BACKUP_OPTION,
			array(
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
				'version'    => SettingsSchema::VERSION,
				'settings'   => is_array( $current ) ? $current : array(),
			),
			false
		);
		return $changed;
	}

	/**
	 * Printable value for previews.
	 *
	 * @param mixed $value Value.
	 */
	public static function display_value( mixed $value ): string {
		if ( is_bool( $value ) ) {
			return $value ? __( 'an', 'review-captain' ) : __( 'aus', 'review-captain' );
		}
		if ( is_array( $value ) ) {
			return implode( ', ', array_map( 'strval', $value ) );
		}
		if ( null === $value || '' === $value ) {
			return '–';
		}
		return (string) $value;
	}
}
