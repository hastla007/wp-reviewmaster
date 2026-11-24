<?php
/**
 * One-time settings migrations between settings schema versions (E5, P2-13).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Settings;

/**
 * Brings the stored `review_captain_settings` option up to SettingsSchema::VERSION.
 *
 * The stored version lives in Settings::OPTION_VERSION. A fresh installation (no stored
 * settings, no settings version and no trace of an earlier installation) only records the
 * current version, so the current schema defaults apply. An existing installation runs every
 * step from its stored version (missing = 1) up to the current one; a step writes explicit
 * values where a new default must not change the behaviour of that site.
 *
 * Steps:
 * - 2 (E5, owner decision 24.11.2026): `privacy.retention_contact_days` defaults to 365 days
 *   for new installations; existing installations without a stored value keep 0 = unlimited.
 *   P2-13: sites with `submissions.notify_admin` switched off also get no report and
 *   question mails (`report_notify` = none, `notify_questions` = off).
 *
 * Settings::all() calls maybe_run() once per request and site (per Settings instance) before reading, so the stored
 * values are migrated before any reader (cron, retention, frontend) sees the new defaults.
 */
final class SettingsMigrator {

	/**
	 * Options that show an earlier installation of the plugin on this site.
	 */
	private const INSTALL_TRACES = array( 'review_captain_db_version', 'review_captain_installed_at' );

	/**
	 * Whether a migration is running (guards against re-entry through Settings::all()).
	 *
	 * @var bool
	 */
	private static bool $running = false;

	/**
	 * Run pending migrations for the current site if the stored settings version is behind.
	 *
	 * @param bool|null $fresh True = new installation, false = existing one, null = detect.
	 * @return list<string>|null Changed "tab.key" entries, null when nothing had to run.
	 */
	public static function maybe_run( ?bool $fresh = null ): ?array {
		if ( self::$running ) {
			return null;
		}
		$version = get_option( Settings::OPTION_VERSION, false );
		if ( false !== $version && (int) $version >= SettingsSchema::VERSION ) {
			return null;
		}
		$stored = get_option( Settings::OPTION, false );
		if ( null === $fresh ) {
			$fresh = false === $version && ! is_array( $stored ) && ! self::has_install_trace();
		}
		self::$running = true;
		try {
			$changed = array();
			if ( ! $fresh ) {
				$from     = false === $version ? 1 : max( 1, (int) $version );
				$before   = is_array( $stored ) ? $stored : array();
				$migrated = self::migrate( $before, $from );
				$changed  = self::changed_keys( $before, $migrated );
				if ( $changed ) {
					update_option( Settings::OPTION, $migrated, true );
				}
			}
			update_option( Settings::OPTION_VERSION, SettingsSchema::VERSION, false );
			if ( $changed ) {
				self::record( $changed, false === $version ? 1 : (int) $version );
			}
			return $changed;
		} finally {
			self::$running = false;
		}
	}

	/**
	 * Apply all steps after $from to stored settings (pure, no I/O).
	 *
	 * @param array<string, mixed> $stored Stored option value.
	 * @param int                  $from   Version the values were written with.
	 * @return array<string, mixed>
	 */
	public static function migrate( array $stored, int $from ): array {
		if ( $from < 2 ) {
			$stored = self::step_2( $stored );
		}
		return $stored;
	}

	/**
	 * Step 2: keep the behaviour of existing installations (E5, P2-13).
	 *
	 * @param array<string, mixed> $stored Stored values.
	 * @return array<string, mixed>
	 */
	private static function step_2( array $stored ): array {
		$privacy = isset( $stored['privacy'] ) && is_array( $stored['privacy'] ) ? $stored['privacy'] : array();
		if ( ! array_key_exists( 'retention_contact_days', $privacy ) ) {
			// Existing sites keep "unlimited" until the operator sets a period (E5).
			$privacy['retention_contact_days'] = 0;
		}
		$stored['privacy'] = $privacy;

		$submissions = isset( $stored['submissions'] ) && is_array( $stored['submissions'] ) ? $stored['submissions'] : null;
		if ( null !== $submissions && array_key_exists( 'notify_admin', $submissions ) && ! $submissions['notify_admin'] ) {
			// Review mails were off: report and question mails stay off as well (P2-13).
			$submissions['report_notify']    = 'none';
			$submissions['notify_questions'] = false;
			$stored['submissions']           = $submissions;
		}
		return $stored;
	}

	/**
	 * Whether this site shows traces of an earlier installation.
	 */
	private static function has_install_trace(): bool {
		foreach ( self::INSTALL_TRACES as $option ) {
			if ( false !== get_option( $option, false ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Keys whose stored value differs.
	 *
	 * @param array<string, mixed> $before Before.
	 * @param array<string, mixed> $after  After.
	 * @return list<string>
	 */
	private static function changed_keys( array $before, array $after ): array {
		$changed = array();
		foreach ( $after as $tab => $fields ) {
			if ( ! is_array( $fields ) ) {
				continue;
			}
			foreach ( $fields as $key => $value ) {
				$old = is_array( $before[ $tab ] ?? null ) && array_key_exists( $key, $before[ $tab ] ) ? $before[ $tab ][ $key ] : null;
				if ( ! ( is_array( $before[ $tab ] ?? null ) && array_key_exists( $key, $before[ $tab ] ) ) || $old !== $value ) {
					$changed[] = $tab . '.' . $key;
				}
			}
		}
		return $changed;
	}

	/**
	 * Audit entry for the migration (best effort: never blocks reading the settings).
	 *
	 * @param list<string> $changed Changed keys.
	 * @param int          $from    Previous version.
	 */
	private static function record( array $changed, int $from ): void {
		if ( ! class_exists( \ReviewCaptain\Plugin::class, false ) ) {
			return;
		}
		try {
			\ReviewCaptain\Plugin::instance()->audit()->record(
				'settings.migrated',
				'settings',
				'',
				array(
					'from'   => $from,
					'to'     => SettingsSchema::VERSION,
					'fields' => $changed,
				)
			);
		} catch ( \Throwable $e ) {
			unset( $e ); // The audit table may not exist yet; the migration itself is done.
		}
	}
}
