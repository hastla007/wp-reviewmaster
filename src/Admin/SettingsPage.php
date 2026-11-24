<?php
/**
 * Settings page with tabs (SET-007, SET-009..SET-019).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Admin;

use ReviewCaptain\Audit\SettingsChanges;
use ReviewCaptain\Plugin;
use ReviewCaptain\Security\Capabilities;
use ReviewCaptain\Settings\Settings;
use ReviewCaptain\Settings\SettingsSchema;

/**
 * Renders fields from SettingsSchema automatically; saves only the submitted tab.
 */
final class SettingsPage {

	public const SLUG = 'review-captain-settings';

	/**
	 * Input name of the settings array.
	 */
	public const INPUT = 'review_captain_settings';

	/**
	 * Transient prefix for a pending import preview.
	 */
	private const IMPORT_TRANSIENT = 'review_captain_import_';

	/**
	 * Max import size in bytes.
	 */
	private const IMPORT_MAX_BYTES = 200000;

	/**
	 * Transient prefix for field errors of the last save (SET-RC-19).
	 */
	private const ERRORS_TRANSIENT = 'review_captain_settings_errors_';

	/**
	 * Characters of a refused input kept to show it again (longer than every text field's limit).
	 */
	private const GIVEN_MAX = 20000;

	/**
	 * Role keys of the access tab and the capability each one grants (SET-RC-16: all seven).
	 */
	private const ROLE_CAPS = Capabilities::ROLE_KEYS;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( private Plugin $plugin ) {}

	/**
	 * Field errors of the last save of the rendered tab: errors and given values by key (SET-RC-19).
	 *
	 * @var array{errors: array<string, string>, given: array<string, string>}
	 */
	private array $field_errors = array(
		'errors' => array(),
		'given'  => array(),
	);

	/**
	 * Settings service.
	 */
	private function settings(): Settings {
		return $this->plugin->settings();
	}

	/**
	 * Transfer helper.
	 */
	private function transfer(): ConfigTransfer {
		return new ConfigTransfer( $this->settings() );
	}

	/**
	 * Whether add-on fields (owner 'pro') may be written (the add-on opts in; the core never does).
	 */
	public static function pro_writable(): bool {
		/**
		 * Filter whether add-on settings fields (owner 'pro') are editable on the settings page.
		 * The add-on that registered them answers; the core has no opinion of its own.
		 *
		 * @param bool $writable Default false.
		 */
		return (bool) apply_filters( 'review_captain_pro_settings_writable', false );
	}

	/**
	 * Whether one add-on field (owner 'pro') may be written. Each add-on feature answers for its
	 * own fields only; the answer of the shared filter above is the default, so an add-on that
	 * only knows the shared filter keeps working (decision of 24.11.2026: write permission per field).
	 *
	 * @param string    $tab           Tab key.
	 * @param string    $key           Field key.
	 * @param bool|null $shared_answer Answer of the shared filter (null = ask it now).
	 */
	public static function pro_field_writable( string $tab, string $key, ?bool $shared_answer = null ): bool {
		/**
		 * Filter whether one add-on settings field (owner 'pro') may be written – on the settings
		 * page, by the import (page and WP-CLI) and by `wp review-captain settings set`. The
		 * manage_settings check and the tab rights stay in force regardless of the answer.
		 *
		 * @param bool   $writable Default: answer of `review_captain_pro_settings_writable`.
		 * @param string $tab      Tab key.
		 * @param string $key      Field key.
		 */
		return (bool) apply_filters( 'review_captain_pro_field_writable', $shared_answer ?? self::pro_writable(), $tab, $key );
	}

	/**
	 * Per-field write check for add-on fields as a callable (tab, key) → bool. The shared filter
	 * is asked once per call of this method; the per-field filter once per field.
	 *
	 * @return \Closure(string, string): bool
	 */
	public static function pro_field_gate(): \Closure {
		$shared = self::pro_writable();
		return static fn( string $tab, string $key ): bool => self::pro_field_writable( $tab, $key, $shared );
	}

	/**
	 * Whether the current user may write a tab. Privacy needs manage_privacy, access needs
	 * promote_users in addition to manage_settings (no privilege escalation via roles tab).
	 *
	 * @param string $tab Tab.
	 */
	public static function can_write_tab( string $tab ): bool {
		if ( ! current_user_can( Capabilities::MANAGE_SETTINGS ) ) {
			return false;
		}
		if ( 'privacy' === $tab ) {
			return current_user_can( Capabilities::MANAGE_PRIVACY );
		}
		if ( 'access' === $tab ) {
			return current_user_can( 'promote_users' );
		}
		return true;
	}

	/**
	 * Tabs the current user may write.
	 *
	 * @return list<string>
	 */
	public static function writable_tabs(): array {
		return array_values( array_filter( array_keys( SettingsSchema::tabs() ), array( self::class, 'can_write_tab' ) ) );
	}

	/**
	 * Current tab from request.
	 */
	public static function current_tab(): string {
		$tab = View::req( $_GET, 'tab', 'general' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return array_key_exists( $tab, SettingsSchema::tabs() ) ? $tab : 'general';
	}

	/**
	 * Tab URL.
	 *
	 * @param string               $tab  Tab.
	 * @param array<string, mixed> $args Extra args.
	 */
	public static function tab_url( string $tab, array $args = array() ): string {
		return View::page_url( self::SLUG, array_merge( array( 'tab' => $tab ), $args ) );
	}

	// ---------------------------------------------------------------------------------------------
	// Write logic.

	/**
	 * Fill role lists of the access tab that are not stored yet from the roles that hold the
	 * capability today (SET-RC-16). Older versions stored only moderate_roles and settings_roles;
	 * mapping the others from the real role state keeps later imports, resets and saves from
	 * revoking a capability granted outside the plugin. Runs once per missing key; no audit entry,
	 * because no right changes.
	 *
	 * @param Settings $settings Settings.
	 * @return bool Whether the option was written.
	 */
	public static function migrate_access( Settings $settings ): bool {
		$stored = get_option( Settings::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$access = isset( $stored['access'] ) && is_array( $stored['access'] ) ? $stored['access'] : array();
		$filled = false;
		foreach ( self::ROLE_CAPS as $key => $cap ) {
			if ( array_key_exists( $key, $access ) ) {
				continue;
			}
			$access[ $key ] = array_values( array_unique( array_merge( array( 'administrator' ), Capabilities::holders( $cap ) ) ) );
			$filled         = true;
		}
		if ( ! $filled ) {
			return false;
		}
		$stored['access'] = $access;
		update_option( Settings::OPTION, $stored, true );
		$settings->flush();
		return true;
	}

	/**
	 * Save one tab. Only the given tab is processed; Pro fields stay untouched unless Pro opts in.
	 * Invalid values are not corrected but reported per field and keep their stored value
	 * (SET-RC-19); fields of a failed cross-check keep their stored values together.
	 *
	 * @param string               $tab   Tab key.
	 * @param array<string, mixed> $input Raw (unslashed) input keyed by tab.
	 * @return array{changed: list<string>, invalid: list<string>, errors: array<string, string>, given: array<string, string>}
	 */
	public function save_tab( string $tab, array $input ): array {
		if ( 'access' === $tab ) {
			self::migrate_access( $this->settings() );
		}
		$fields  = SettingsSchema::fields()[ $tab ] ?? array();
		$raw     = isset( $input[ $tab ] ) && is_array( $input[ $tab ] ) ? $input[ $tab ] : array();
		$pro     = self::pro_field_gate();
		$current = $this->settings()->all();
		$values  = $current;
		$errors  = array();
		$given   = array();
		$open    = array();

		foreach ( $fields as $key => $field ) {
			if ( ( 'pro' === $field['owner'] && ! $pro( $tab, $key ) ) || ! empty( $field['internal'] ) ) {
				continue;
			}
			$open[] = $key;
			if ( 'bool' === $field['type'] ) {
				$values[ $tab ][ $key ] = SettingsSchema::sanitize_value( $field, $raw[ $key ] ?? false );
				continue;
			}
			if ( ! array_key_exists( $key, $raw ) ) {
				continue;
			}
			[ $value, $error ] = SettingsSchema::validate_value( $field, $raw[ $key ] );
			if ( null !== $error ) {
				$errors[ $key ] = $error;
				$given[ $key ]  = is_scalar( $raw[ $key ] ) ? mb_substr( (string) $raw[ $key ], 0, self::GIVEN_MAX ) : '';
				continue;
			}
			$values[ $tab ][ $key ] = $value;
		}

		foreach ( SettingsSchema::cross_check( $tab, (array) ( $values[ $tab ] ?? array() ) ) as $check ) {
			$involved = array_values( array_map( 'strval', (array) ( $check['fields'] ?? array() ) ) );
			if ( ! $involved || array_diff( $involved, $open ) ) {
				continue; // Never report on fields the user cannot change here.
			}
			foreach ( $involved as $key ) {
				$values[ $tab ][ $key ] = $current[ $tab ][ $key ] ?? null;
				$errors[ $key ]       ??= (string) ( $check['message'] ?? '' );
				if ( ! isset( $given[ $key ] ) && array_key_exists( $key, $raw ) && is_scalar( $raw[ $key ] ) ) {
					$given[ $key ] = mb_substr( (string) $raw[ $key ], 0, self::GIVEN_MAX );
				}
			}
		}

		if ( 'privacy' === $tab ) {
			[ $values['privacy'], $error ] = SettingsSchema::guard_uninstall( (array) $values['privacy'], (array) ( $current['privacy'] ?? array() ), $raw['uninstall_confirm_word'] ?? null );
			if ( null !== $error ) {
				$errors['delete_data_on_uninstall'] = $error;
			}
		}

		if ( 'access' === $tab ) {
			$existing = array_keys( wp_roles()->role_objects );
			foreach ( array_keys( self::ROLE_CAPS ) as $key ) {
				$roles                    = array_values( array_intersect( (array) ( $values['access'][ $key ] ?? array() ), $existing ) );
				$values['access'][ $key ] = array_values( array_unique( array_merge( array( 'administrator' ), $roles ) ) );
			}
			$values['access'] = self::keep_unheld_role_keys( $values['access'], $current['access'] ?? array() );
		}

		$changed = $this->settings()->save( $values );
		if ( 'access' === $tab ) {
			$this->sync_roles( $values['access'] );
		}
		if ( $changed ) {
			$this->plugin->audit()->record(
				'settings.changed',
				'settings',
				$tab,
				array(
					'tab'     => $tab,
					'fields'  => $changed,
					'changes' => SettingsChanges::between( $current, $values, $changed ),
				)
			);
		}
		$ids = array();
		foreach ( $errors as $key => $message ) {
			$ids[ $tab . '-' . $key ] = $message;
		}
		return array(
			'changed' => $changed,
			'invalid' => array_keys( $ids ),
			'errors'  => $ids,
			'given'   => $given,
		);
	}

	/**
	 * Apply role selections to capabilities (administrator always keeps everything). Only
	 * capabilities the acting user holds are touched; one audit entry per capability whose
	 * holders actually changed (SET-RC-16).
	 *
	 * @param array<string, mixed> $access Access tab values.
	 */
	private function sync_roles( array $access ): void {
		foreach ( self::ROLE_CAPS as $key => $cap ) {
			if ( ! current_user_can( $cap ) ) {
				// Only capabilities the acting user holds may be (re)assigned (capabilities.md §3).
				continue;
			}
			$roles  = array_values( array_unique( array_merge( array( 'administrator' ), array_map( 'strval', (array) ( $access[ $key ] ?? array() ) ) ) ) );
			$before = Capabilities::holders( $cap );
			Capabilities::sync_roles( $cap, $roles );
			$after = Capabilities::holders( $cap );
			$a     = $before;
			$b     = $after;
			sort( $a );
			sort( $b );
			if ( $a === $b ) {
				continue;
			}
			$this->plugin->audit()->record(
				'roles.capabilities_changed',
				'capability',
				$cap,
				array(
					'capability' => $cap,
					'from'       => $before,
					'roles'      => $after,
				)
			);
		}
	}

	/**
	 * Keep the stored role list for every capability the acting user does not hold, so the roles
	 * tab (save, import, reset, restore) can never hand out a capability its user lacks.
	 *
	 * @param array<string, mixed> $access  New access tab values.
	 * @param array<string, mixed> $current Stored access tab values.
	 * @return array<string, mixed>
	 */
	public static function keep_unheld_role_keys( array $access, array $current ): array {
		$defaults = SettingsSchema::defaults()['access'] ?? array();
		foreach ( self::ROLE_CAPS as $key => $cap ) {
			if ( ! current_user_can( $cap ) ) {
				$access[ $key ] = $current[ $key ] ?? ( $defaults[ $key ] ?? array( 'administrator' ) );
			}
		}
		return $access;
	}

	/**
	 * After a bulk write (reset/import/restore): put back role lists the user may not change.
	 *
	 * @param array<string, mixed> $before Access tab values before the write.
	 */
	private function restore_unheld_role_keys( array $before ): void {
		$all  = $this->settings()->all();
		$kept = self::keep_unheld_role_keys( (array) ( $all['access'] ?? array() ), $before );
		if ( $kept !== ( $all['access'] ?? array() ) ) {
			$all['access'] = $kept;
			$this->settings()->save( $all );
		}
	}

	/**
	 * Reset tabs to defaults (reviews untouched). Returns reset tabs.
	 *
	 * @param list<string> $tabs Requested tabs.
	 * @return list<string>
	 */
	public function reset_tabs( array $tabs ): array {
		$tabs = array_values( array_intersect( $tabs, self::writable_tabs(), self::resettable_tabs() ) );
		if ( ! $tabs ) {
			return array();
		}
		self::migrate_access( $this->settings() );
		$this->transfer()->backup();
		$all    = $this->settings()->all();
		$before = (array) ( $all['access'] ?? array() );
		$this->settings()->reset( $tabs );
		if ( in_array( 'access', $tabs, true ) ) {
			$this->restore_unheld_role_keys( $before );
			$this->sync_roles( $this->settings()->all()['access'] ?? array() );
		}
		$after = $this->settings()->all();
		$this->plugin->audit()->record(
			'settings.reset',
			'settings',
			implode( ',', $tabs ),
			array(
				'fields'  => $tabs,
				'changes' => SettingsChanges::between( $all, $after, self::changed_keys( $all, $after ) ),
			)
		);
		return $tabs;
	}

	/**
	 * Tabs with own (core) fields; a reset never touches add-on fields, so other tabs (requests)
	 * have nothing to reset.
	 *
	 * @return list<string>
	 */
	public static function resettable_tabs(): array {
		$out = array();
		foreach ( SettingsSchema::fields() as $tab => $fields ) {
			foreach ( $fields as $field ) {
				if ( 'core' === $field['owner'] && empty( $field['internal'] ) ) {
					$out[] = (string) $tab;
					break;
				}
			}
		}
		return $out;
	}

	/**
	 * "tab.key" names whose value differs between two settings states.
	 *
	 * @param array<string, mixed> $before Before.
	 * @param array<string, mixed> $after  After.
	 * @return list<string>
	 */
	public static function changed_keys( array $before, array $after ): array {
		$out = array();
		foreach ( SettingsSchema::fields() as $tab => $fields ) {
			foreach ( array_keys( $fields ) as $key ) {
				if ( ( $before[ $tab ][ $key ] ?? null ) !== ( $after[ $tab ][ $key ] ?? null ) ) {
					$out[] = $tab . '.' . $key;
				}
			}
		}
		return $out;
	}

	// ---------------------------------------------------------------------------------------------
	// Handlers (admin-post).

	/**
	 * Handler: save current tab.
	 */
	public function handle_save(): void {
		Capabilities::verify_request( Capabilities::MANAGE_SETTINGS, 'save_settings' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above.
		$tab   = sanitize_key( wp_unslash( (string) ( $_POST['tab'] ?? '' ) ) );
		$input = isset( $_POST[ self::INPUT ] ) && is_array( $_POST[ self::INPUT ] ) ? wp_unslash( $_POST[ self::INPUT ] ) : array();
		// phpcs:enable
		if ( ! array_key_exists( $tab, SettingsSchema::tabs() ) ) {
			$tab = 'general';
		}
		if ( ! self::can_write_tab( $tab ) ) {
			View::redirect( Notices::url( self::tab_url( $tab ), 'tab_forbidden' ) );
			return;
		}
		$result = $this->save_tab( $tab, $input );
		if ( $result['invalid'] ) {
			set_transient(
				self::ERRORS_TRANSIENT . get_current_user_id(),
				array(
					'tab'    => $tab,
					'errors' => $result['errors'],
					'given'  => $result['given'],
				),
				10 * MINUTE_IN_SECONDS
			);
			View::redirect( Notices::url( self::tab_url( $tab ), 'saved_invalid', $result['invalid'] ) );
			return;
		}
		View::redirect( Notices::url( self::tab_url( $tab ), $result['changed'] ? 'saved' : 'unchanged' ) );
	}

	/**
	 * Handler: reset settings (tab or all) – requires confirmation.
	 */
	public function handle_reset(): void {
		Capabilities::verify_request( Capabilities::MANAGE_SETTINGS, 'reset_settings' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above.
		$tab     = sanitize_key( wp_unslash( (string) ( $_POST['tab'] ?? 'privacy' ) ) );
		$scope   = sanitize_key( wp_unslash( (string) ( $_POST['scope'] ?? 'tab' ) ) );
		$confirm = ! empty( $_POST['confirm'] );
		// phpcs:enable
		$back = self::tab_url( array_key_exists( $tab, SettingsSchema::tabs() ) ? $tab : 'privacy' );
		if ( ! $confirm ) {
			View::redirect( Notices::url( $back, 'confirm_required' ) );
			return;
		}
		$tabs = 'all' === $scope ? array_keys( SettingsSchema::tabs() ) : array( $tab );
		$done = $this->reset_tabs( $tabs );
		View::redirect( Notices::url( $back, $done ? 'reset_done' : 'tab_forbidden' ) );
	}

	/**
	 * Handler: download configuration export (JSON).
	 */
	public function handle_export(): void {
		Capabilities::verify_request( Capabilities::MANAGE_SETTINGS, 'export_settings' );
		// P1-8: contact data of the operator only when the box was ticked.
		$with_contacts = '1' === View::req( $_POST, 'with_contacts' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$doc           = $this->transfer()->export( $with_contacts );
		$this->plugin->audit()->record(
			'settings.exported',
			'settings',
			'',
			array(
				'version'       => SettingsSchema::VERSION,
				'with_contacts' => $with_contacts,
			)
		);
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="review-captain-settings-' . gmdate( 'Y-m-d' ) . '.json"' );
		header( 'X-Content-Type-Options: nosniff' );
		echo wp_json_encode( $doc, JSON_PRETTY_PRINT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download.
		exit;
	}

	/**
	 * Handler: upload/paste a configuration and create a preview.
	 */
	public function handle_import_preview(): void {
		Capabilities::verify_request( Capabilities::MANAGE_SETTINGS, 'import_settings' );
		$json = $this->read_import_payload();
		$back = self::tab_url( 'advanced' );
		if ( null === $json ) {
			View::redirect( Notices::url( $back, 'import_invalid' ) );
			return;
		}
		$preview = $this->transfer()->preview( $json, self::writable_tabs(), self::pro_field_gate() );
		if ( null === $preview ) {
			View::redirect( Notices::url( $back, 'import_invalid' ) );
			return;
		}
		if ( self::migrate_access( $this->settings() ) ) {
			// Role lists missing in the file must keep today's roles, not the defaults.
			$preview = (array) $this->transfer()->preview( $json, self::writable_tabs(), self::pro_field_gate() );
		}
		if ( ! $preview['diff'] ) {
			View::redirect( Notices::url( $back, 'import_empty' ) );
			return;
		}
		set_transient( self::IMPORT_TRANSIENT . get_current_user_id(), $preview, 15 * MINUTE_IN_SECONDS );
		View::redirect( Notices::url( self::tab_url( 'advanced', array( 'import' => 'preview' ) ), 'import_preview' ) );
	}

	/**
	 * Handler: apply or discard a pending import.
	 */
	public function handle_import_apply(): void {
		Capabilities::verify_request( Capabilities::MANAGE_SETTINGS, 'import_apply' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$decision = sanitize_key( wp_unslash( (string) ( $_POST['decision'] ?? '' ) ) );
		$key      = self::IMPORT_TRANSIENT . get_current_user_id();
		$preview  = get_transient( $key );
		delete_transient( $key );
		$back = self::tab_url( 'advanced' );
		if ( 'apply' !== $decision ) {
			View::redirect( Notices::url( $back, 'import_discarded' ) );
			return;
		}
		if ( ! is_array( $preview ) || ! isset( $preview['values'], $preview['tabs'] ) ) {
			View::redirect( Notices::url( $back, 'import_expired' ) );
			return;
		}
		// Re-check permissions per tab at apply time.
		$tabs = array_values( array_intersect( (array) $preview['tabs'], self::writable_tabs() ) );
		$changed = $this->apply_import( (array) $preview['values'], $tabs );
		View::redirect( Notices::url( $back, $changed ? 'import_applied' : 'import_empty' ) );
	}

	/**
	 * Apply a previewed import: backup, save, sync roles, audit.
	 *
	 * @param array<string, array<string, mixed>> $values Previewed values.
	 * @param list<string>                        $tabs   Tabs to apply.
	 * @return list<string> Changed keys.
	 */
	public function apply_import( array $values, array $tabs ): array {
		self::migrate_access( $this->settings() );
		$before  = $this->settings()->all();
		$current = $before;
		$pro     = self::pro_field_gate();
		$schema  = SettingsSchema::fields();
		foreach ( $tabs as $tab ) {
			if ( isset( $values[ $tab ] ) && is_array( $values[ $tab ] ) ) {
				$current[ $tab ] = $values[ $tab ];
				foreach ( $schema[ $tab ] ?? array() as $key => $field ) {
					if ( 'pro' === $field['owner'] && ! $pro( $tab, $key ) && array_key_exists( $key, (array) ( $before[ $tab ] ?? array() ) ) ) {
						// The add-on stopped allowing writes since the preview: keep its stored value.
						$current[ $tab ][ $key ] = $before[ $tab ][ $key ];
					}
				}
			}
		}
		if ( in_array( 'access', $tabs, true ) ) {
			foreach ( array_keys( self::ROLE_CAPS ) as $role_key ) {
				$current['access'][ $role_key ] = array_values( array_unique( array_merge( array( 'administrator' ), (array) ( $current['access'][ $role_key ] ?? array() ) ) ) );
			}
			$current['access'] = self::keep_unheld_role_keys( $current['access'], $this->settings()->all()['access'] ?? array() );
		}
		$changed = $this->transfer()->apply( $current );
		if ( in_array( 'access', $tabs, true ) ) {
			$this->sync_roles( $current['access'] );
		}
		$this->plugin->audit()->record(
			'settings.imported',
			'settings',
			'',
			array(
				'fields'  => $changed,
				'changes' => SettingsChanges::between( $before, $current, $changed ),
			)
		);
		return $changed;
	}

	/**
	 * Handler: restore the settings backup.
	 */
	public function handle_rollback(): void {
		Capabilities::verify_request( Capabilities::MANAGE_SETTINGS, 'rollback_settings' );
		$back = self::tab_url( 'advanced' );
		if ( count( self::writable_tabs() ) < count( SettingsSchema::tabs() ) ) {
			// A full restore touches every tab; only users who may write all tabs may do it.
			View::redirect( Notices::url( $back, 'tab_forbidden' ) );
			return;
		}
		self::migrate_access( $this->settings() );
		$all     = $this->settings()->all();
		$before  = (array) ( $all['access'] ?? array() );
		$changed = $this->transfer()->rollback();
		if ( null === $changed ) {
			View::redirect( Notices::url( $back, 'rollback_missing' ) );
			return;
		}
		$this->settings()->flush();
		// A backup from before the role matrix lacks some role lists: map them from today's roles.
		self::migrate_access( $this->settings() );
		$this->restore_unheld_role_keys( $before );
		$this->sync_roles( $this->settings()->all()['access'] ?? array() );
		$after = $this->settings()->all();
		$this->plugin->audit()->record(
			'settings.restored',
			'settings',
			'',
			array(
				'fields'  => $changed,
				'changes' => SettingsChanges::between( $all, $after, self::changed_keys( $all, $after ) ),
			)
		);
		View::redirect( Notices::url( $back, 'rollback_done' ) );
	}

	/**
	 * Read import JSON from upload or textarea.
	 */
	private function read_import_payload(): ?string {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by caller.
		if ( ! empty( $_FILES['config_file']['tmp_name'] ) && is_string( $_FILES['config_file']['tmp_name'] ) && is_uploaded_file( $_FILES['config_file']['tmp_name'] ) ) {
			$size = (int) ( $_FILES['config_file']['size'] ?? 0 );
			if ( $size <= 0 || $size > self::IMPORT_MAX_BYTES ) {
				return null;
			}
			$json = file_get_contents( $_FILES['config_file']['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			return is_string( $json ) ? $json : null;
		}
		$json = isset( $_POST['config_json'] ) && is_string( $_POST['config_json'] ) ? wp_unslash( $_POST['config_json'] ) : '';
		// phpcs:enable
		$json = trim( $json );
		return '' === $json || strlen( $json ) > self::IMPORT_MAX_BYTES ? null : $json;
	}

	// ---------------------------------------------------------------------------------------------
	// Rendering.

	/**
	 * Page callback.
	 */
	public function render(): void {
		if ( ! current_user_can( Capabilities::MANAGE_SETTINGS ) ) {
			wp_die( esc_html__( 'Dafür fehlt dir die Berechtigung.', 'review-captain' ), '', array( 'response' => 403 ) );
		}
		$tab = self::current_tab();
		if ( 'access' === $tab ) {
			self::migrate_access( $this->settings() );
		}
		$tabs   = SettingsSchema::tabs();
		$schema = SettingsSchema::fields();
		$values = $this->settings()->all();
		$pro    = self::pro_field_gate();

		$this->field_errors = $this->pending_errors( $tab );

		echo '<div class="wrap rc-admin">';
		echo View::header( __( 'Einstellungen', 'review-captain' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( $this->field_errors['errors'] ) {
			$this->render_error_summary( $tab );
		} else {
			echo Notices::render_current(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		echo '<nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'Einstellungsbereiche', 'review-captain' ) . '">';
		foreach ( $tabs as $key => $label ) {
			$active = $key === $tab;
			echo '<a href="' . esc_url( self::tab_url( $key ) ) . '" class="nav-tab' . ( $active ? ' nav-tab-active' : '' ) . '"' . ( $active ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';

		$this->render_tab_notes( $tab );

		$writable = self::can_write_tab( $tab );
		if ( ! $writable ) {
			echo View::notice( 'warning', 'access' === $tab ? __( 'Rollen kann nur ändern, wer selbst Benutzerrollen vergeben darf.', 'review-captain' ) : __( 'Diese Einstellungen darf nur ändern, wer das Recht „Datenschutz verwalten“ hat.', 'review-captain' ), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		$fields = array_filter( $schema[ $tab ] ?? array(), static fn( $f ) => empty( $f['internal'] ) );
		$hints  = SettingsHints::for_tab( $tab, $this->settings() );
		if ( ! $fields ) {
			// A tab whose fields all come from add-ons (requests): no empty form, no save button.
			echo $hints ? SettingsHints::render( $hints ) : '<p class="description rc-admin-empty-tab">' . esc_html__( 'Hier erscheinen Einstellungen von Erweiterungen.', 'review-captain' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '</div>';
			return;
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="rc-admin-settings-form">';
		echo View::form_fields( 'save_settings' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<input type="hidden" name="tab" value="' . esc_attr( $tab ) . '" />';

		$sections = 'access' === $tab ? array() : ( SettingsFields::sections()[ $tab ] ?? array() );
		$placed   = array();
		$advanced = array();
		if ( 'access' === $tab ) {
			$placed = $this->render_access_matrix( $fields, (array) ( $values['access'] ?? array() ), $writable );
		}
		foreach ( $fields as $key => $field ) {
			if ( ! empty( $field['advanced'] ) && ! in_array( $key, $placed, true ) ) {
				$advanced[] = $key;
			}
		}
		foreach ( $sections as $heading => $keys ) {
			$keys   = array_values( array_filter( $keys, static fn( $k ) => isset( $fields[ $k ] ) && empty( $fields[ $k ]['advanced'] ) ) );
			$placed = array_merge( $placed, $keys );
			if ( $keys ) {
				$this->render_section( $heading, $tab, $keys, $fields, $values, $writable, $pro );
			}
		}
		// Add-on fields may name their own section heading ('section').
		$own = array();
		foreach ( array_diff( array_keys( $fields ), $placed, $advanced ) as $key ) {
			$heading = $fields[ $key ]['section'] ?? '';
			if ( is_string( $heading ) && '' !== trim( $heading ) ) {
				$own[ trim( $heading ) ][] = $key;
			}
		}
		foreach ( $own as $heading => $keys ) {
			$placed = array_merge( $placed, $keys );
			$this->render_section( (string) $heading, $tab, $keys, $fields, $values, $writable, $pro );
		}
		$rest = array_values( array_diff( array_keys( $fields ), $placed, $advanced ) );
		if ( $rest ) {
			$this->render_section( __( 'Weitere Optionen', 'review-captain' ), $tab, $rest, $fields, $values, $writable, $pro );
		}
		if ( $advanced ) {
			$open = 'advanced' === $tab;
			foreach ( $advanced as $key ) {
				if ( ( $values[ $tab ][ $key ] ?? null ) !== $fields[ $key ]['default'] || isset( $this->field_errors['errors'][ $key ] ) ) {
					$open = true;
				}
			}
			echo '<details class="rc-admin-advanced"' . ( $open ? ' open' : '' ) . '><summary>' . esc_html__( 'Erweitert anzeigen', 'review-captain' ) . '</summary>';
			$this->render_section( '', $tab, $advanced, $fields, $values, $writable, $pro );
			echo '</details>';
		}

		$any_writable = $writable && array_filter( $fields, static fn( $f, $k ) => 'pro' !== $f['owner'] || $pro( $tab, (string) $k ), ARRAY_FILTER_USE_BOTH );
		if ( $any_writable ) {
			echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Änderungen speichern', 'review-captain' ) . '</button></p>';
		}
		echo '</form>';
		echo SettingsHints::render( $hints ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( 'privacy' === $tab ) {
			$this->render_privacy_actions();
		}
		if ( 'advanced' === $tab ) {
			$this->render_maintenance();
		}
		echo '</div>';
	}

	/**
	 * Field errors stored by the last save of this tab, shown once (SET-RC-19).
	 *
	 * @param string $tab Tab.
	 * @return array{errors: array<string, string>, given: array<string, string>}
	 */
	private function pending_errors( string $tab ): array {
		$none = array(
			'errors' => array(),
			'given'  => array(),
		);
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		if ( 'saved_invalid' !== View::req( $_GET, Notices::PARAM ) ) {
			return $none;
		}
		$key     = self::ERRORS_TRANSIENT . get_current_user_id();
		$pending = get_transient( $key );
		delete_transient( $key );
		if ( ! is_array( $pending ) || ( $pending['tab'] ?? '' ) !== $tab || empty( $pending['errors'] ) || ! is_array( $pending['errors'] ) ) {
			return $none;
		}
		$out = $none;
		foreach ( $pending['errors'] as $id => $message ) {
			$prefix = $tab . '-';
			if ( is_string( $id ) && str_starts_with( $id, $prefix ) ) {
				$out['errors'][ substr( $id, strlen( $prefix ) ) ] = (string) $message;
			}
		}
		foreach ( (array) ( $pending['given'] ?? array() ) as $field => $value ) {
			$out['given'][ (string) $field ] = (string) $value;
		}
		return $out;
	}

	/**
	 * Error list with jump links to the fields (SET-RC-19).
	 *
	 * @param string $tab Tab.
	 */
	private function render_error_summary( string $tab ): void {
		$fields = SettingsSchema::fields()[ $tab ] ?? array();
		echo '<div class="notice notice-error rc-admin-errors" id="rc-settings-errors" role="alert" tabindex="-1" autofocus><p>' . esc_html__( 'Einige Werte wurden nicht gespeichert, weil sie ungültig sind. Alle anderen Änderungen sind gespeichert. Bitte prüfe:', 'review-captain' ) . '</p><ul>';
		foreach ( $this->field_errors['errors'] as $key => $message ) {
			$anchor = 'access' === $tab ? 'rc-access-' . $key . '-col' : 'rc-' . $tab . '-' . $key;
			echo '<li><a href="#' . esc_attr( $anchor ) . '">' . esc_html( SettingsFields::label( $tab, $key, $fields[ $key ] ?? array() ) ) . '</a>: ' . esc_html( $message ) . '</li>';
		}
		echo '</ul></div>';
	}

	/**
	 * Access tab as a role × capability matrix (SET-RC-16). Administrators always keep every
	 * capability; columns of capabilities the acting user does not hold are read-only.
	 *
	 * @param array<string, array<string, mixed>> $fields   Access fields.
	 * @param array<string, mixed>                $access   Access values.
	 * @param bool                                $writable Tab writable.
	 * @return list<string> Keys rendered by the matrix.
	 */
	private function render_access_matrix( array $fields, array $access, bool $writable ): array {
		$keys = array_values( array_filter( array_keys( self::ROLE_CAPS ), static fn( $k ) => isset( $fields[ $k ] ) ) );
		echo '<h2>' . esc_html__( 'Rechte je Rolle', 'review-captain' ) . '</h2>';
		echo '<p>' . esc_html__( 'Hake an, welche Rolle welches Recht bekommt. Administratoren behalten immer alle Rechte. Rechte, die du selbst nicht hast, kannst du weder vergeben noch entziehen; jede Änderung wird protokolliert.', 'review-captain' ) . '</p>';
		echo '<div class="rc-admin-matrix-wrap"><table class="widefat striped rc-admin-matrix"><caption class="screen-reader-text">' . esc_html__( 'Rechte je Rolle', 'review-captain' ) . '</caption><thead><tr><th scope="col">' . esc_html__( 'Rolle', 'review-captain' ) . '</th>';
		foreach ( $keys as $key ) {
			$held = current_user_can( self::ROLE_CAPS[ $key ] );
			echo '<th scope="col" id="' . esc_attr( 'rc-access-' . $key . '-col' ) . '">' . esc_html( SettingsFields::label( 'access', $key, $fields[ $key ] ) );
			if ( $writable && ! $held ) {
				echo '<br /><span class="description">' . esc_html__( '(nur lesen)', 'review-captain' ) . '</span>';
			}
			echo '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( wp_roles()->role_names as $role => $role_label ) {
			$role  = (string) $role;
			$label = function_exists( 'translate_user_role' ) ? translate_user_role( (string) $role_label ) : (string) $role_label;
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th>';
			foreach ( $keys as $key ) {
				$name     = self::INPUT . '[access][' . $key . '][]';
				$id       = 'rc-access-' . $key . '-' . sanitize_html_class( $role );
				$disabled = ! $writable || ! current_user_can( self::ROLE_CAPS[ $key ] );
				$locked   = 'administrator' === $role;
				$on       = $locked || in_array( $role, array_map( 'strval', (array) ( $access[ $key ] ?? array() ) ), true );
				echo '<td><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $role ) . '"' . checked( $on, true, false ) . ( $locked || $disabled ? ' disabled="disabled"' : '' ) . ' />';
				/* translators: 1: role, 2: capability */
				echo '<label for="' . esc_attr( $id ) . '" class="screen-reader-text">' . esc_html( sprintf( __( '%1$s: %2$s', 'review-captain' ), $label, SettingsFields::label( 'access', $key, $fields[ $key ] ) ) ) . '</label>';
				if ( $locked ) {
					echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="administrator"' . ( $disabled ? ' disabled="disabled"' : '' ) . ' /> <span class="description">' . esc_html__( '(immer)', 'review-captain' ) . '</span>';
				}
				echo '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table></div>';
		foreach ( $keys as $key ) {
			// Empty marker so "nothing checked" is submitted as an empty list.
			$disabled = ! $writable || ! current_user_can( self::ROLE_CAPS[ $key ] );
			echo '<input type="hidden" name="' . esc_attr( self::INPUT . '[access][' . $key . '][]' ) . '" value=""' . ( $disabled ? ' disabled="disabled"' : '' ) . ' />';
		}
		echo '<dl class="rc-admin-matrix-help">';
		foreach ( $keys as $key ) {
			echo '<dt>' . esc_html( SettingsFields::label( 'access', $key, $fields[ $key ] ) ) . ' <code>' . esc_html( self::ROLE_CAPS[ $key ] ) . '</code></dt><dd>' . esc_html( SettingsFields::description( 'access', $key, $fields[ $key ] ) ) . '</dd>';
		}
		echo '</dl>';
		return $keys;
	}

	/**
	 * Context notes per tab.
	 *
	 * @param string $tab Tab.
	 */
	private function render_tab_notes( string $tab ): void {
		$notes = array();
		if ( 'requests' === $tab && array_filter( SettingsSchema::fields()['requests'] ?? array(), static fn( $f ) => empty( $f['internal'] ) ) ) {
			$notes[] = array( 'info', __( 'Immer gilt: höchstens eine Einladung je tatsächlichem Erlebnis und Empfänger, kein Versand ohne bekannte Versanderlaubnis, Abmeldungen sperren jede weitere Einladung, keine Filterung nach Zufriedenheit.', 'review-captain' ) );
		}
		if ( 'sources' === $tab ) {
			$notes[] = array( 'info', __( 'Diese Werte wirken erst, wenn eine Quelle automatisch abgeglichen wird (Review Captain Pro oder ein Connector). Profile und Links verwaltest du unter „Quellen & Profile“.', 'review-captain' ) );
		}
		if ( 'display' === $tab ) {
			$notes[] = array( 'info', __( 'Voreinstellung für neue Widgets. Die Quellenangabe externer Bewertungen wird immer angezeigt und lässt sich nicht abschalten.', 'review-captain' ) );
		}
		if ( 'submissions' === $tab && false === $this->settings()->get( 'submissions', 'require_consent' ) ) {
			$notes[] = array( 'warning', __( 'Die Einwilligung zur Veröffentlichung wird nicht abgefragt. Stelle die Rechtsgrundlage anders sicher.', 'review-captain' ) );
		}
		if ( 'advanced' === $tab && defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			$notes[] = array( 'warning', __( 'WP-Cron ist deaktiviert. Richte einen Server-Cron ein, z. B. „wp cron event run --due-now“ alle 5 Minuten.', 'review-captain' ) );
		}
		if ( 'privacy' === $tab ) {
			// SET-RC-08: preview of the next retention runs (two COUNT queries).
			$contact_days  = (int) $this->settings()->get( 'privacy', 'retention_contact_days', 0 );
			$approved_days = (int) $this->settings()->get( 'privacy', 'retention_approved_days', 0 );
			$preview       = \ReviewCaptain\Maintenance\Retention::preview( $contact_days, $approved_days );
			if ( $contact_days > 0 ) {
				/* translators: %s: number of reviews */
				$notes[] = array( 'info', sprintf( _n( 'Kontaktadresse: Die nächste Bereinigung entfernt die E-Mail-Adresse bei %s Bewertung. Die Bewertung bleibt.', 'Kontaktadresse: Die nächste Bereinigung entfernt die E-Mail-Adresse bei %s Bewertungen. Die Bewertungen bleiben.', $preview['contacts'], 'review-captain' ), number_format_i18n( $preview['contacts'] ) ) );
				if ( $preview['questions'] > 0 ) {
					/* translators: %s: number of product questions */
					$notes[] = array( 'info', sprintf( _n( 'Kontaktadresse: Die nächste Bereinigung entfernt außerdem die E-Mail-Adresse bei %s Produktfrage. Die Frage bleibt.', 'Kontaktadresse: Die nächste Bereinigung entfernt außerdem die E-Mail-Adresse bei %s Produktfragen. Die Fragen bleiben.', $preview['questions'], 'review-captain' ), number_format_i18n( $preview['questions'] ) ) );
				}
			} else {
				// S9: remind while no period is set (existing installations keep "unlimited", E5).
				$notes[] = array( 'warning', __( 'Für die E-Mail-Adressen zu Bewertungen und Produktfragen ist keine Aufbewahrungsfrist eingestellt; sie bleiben unbegrenzt gespeichert. Neue Installationen entfernen sie nach 365 Tagen. Welche Frist passt, lass bitte rechtlich prüfen.', 'review-captain' ) );
			}
			if ( $approved_days > 0 ) {
				/* translators: %s: number of reviews */
				$notes[] = array( 'warning', sprintf( _n( 'Freigegebene Bewertungen: Die nächste Bereinigung löscht %s Bewertung endgültig.', 'Freigegebene Bewertungen: Die nächste Bereinigung löscht %s Bewertungen endgültig.', $preview['approved'], 'review-captain' ), number_format_i18n( $preview['approved'] ) ) );
			}
		}
		if ( in_array( $tab, array( 'access', 'privacy' ), true ) && is_multisite() ) {
			$notes[] = array( 'info', __( 'Multisite: Diese Einstellungen gelten nur für diese Website. Jede Website des Netzwerks hat eigene Einstellungen, Rollen und Daten; Netzwerkadministratoren ändern sie je Website.', 'review-captain' ) );
		}
		foreach ( $notes as [ $type, $text ] ) {
			echo View::notice( $type, $text, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Render a section with a form-table.
	 *
	 * @param string                              $heading  Heading (empty = none).
	 * @param string                              $tab      Tab.
	 * @param list<string>                        $keys     Keys.
	 * @param array<string, array<string, mixed>> $fields   Fields.
	 * @param array<string, array<string, mixed>> $values   Values.
	 * @param bool                                $writable Tab writable.
	 * @param \Closure                            $pro      Per-field write check for add-on fields (tab, key) → bool.
	 */
	private function render_section( string $heading, string $tab, array $keys, array $fields, array $values, bool $writable, \Closure $pro ): void {
		if ( '' !== $heading ) {
			echo '<h2>' . esc_html( $heading ) . '</h2>';
		}
		echo '<table class="form-table" role="presentation"><tbody>';
		foreach ( $keys as $key ) {
			$field    = $fields[ $key ];
			$locked   = 'pro' === $field['owner'] && ! $pro( $tab, $key );
			$disabled = ! $writable || $locked;
			$this->render_field( $tab, $key, $field, $values[ $tab ][ $key ] ?? $field['default'], $disabled, $locked );
		}
		echo '</tbody></table>';
	}

	/**
	 * Render one field row (type → control).
	 *
	 * @param string               $tab      Tab.
	 * @param string               $key      Key.
	 * @param array<string, mixed> $field    Field.
	 * @param mixed                $value    Value.
	 * @param bool                 $disabled Disabled.
	 * @param bool                 $pro_lock Add-on field that is not writable right now (neutral lock note).
	 */
	public function render_field( string $tab, string $key, array $field, mixed $value, bool $disabled, bool $pro_lock ): void {
		$id    = 'rc-' . $tab . '-' . $key;
		$name  = self::INPUT . '[' . $tab . '][' . $key . ']';
		$label = SettingsFields::label( $tab, $key, $field );
		$desc  = SettingsFields::description( $tab, $key, $field );
		$dis   = $disabled ? ' disabled="disabled"' : '';
		$error = $this->field_errors['errors'][ $key ] ?? null;
		$ids   = array();
		if ( '' !== $desc || $pro_lock ) {
			$ids[] = $id . '-desc';
		}
		if ( null !== $error ) {
			$ids[] = $id . '-error';
			if ( isset( $this->field_errors['given'][ $key ] ) && 'bool' !== $field['type'] ) {
				$value = $this->field_errors['given'][ $key ]; // Show what was typed, so it can be fixed.
			}
		}
		$descr = ( $ids ? ' aria-describedby="' . esc_attr( implode( ' ', $ids ) ) . '"' : '' ) . ( null !== $error ? ' aria-invalid="true"' : '' );

		echo '<tr><th scope="row">';
		// A checkbox gets the row label as a second <label>, so its accessible name is "<label> Aktiv", not only "Aktiv".
		echo 'array' === $field['type'] ? esc_html( $label ) : '<label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
		echo '</th><td>';

		switch ( $field['type'] ) {
			case 'bool':
				echo '<label for="' . esc_attr( $id ) . '"><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . checked( (bool) $value, true, false ) . $dis . $descr . ' /> ' . esc_html__( 'Aktiv', 'review-captain' ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				break;
			case 'int':
				$min = isset( $field['min'] ) ? ' min="' . esc_attr( (string) $field['min'] ) . '"' : '';
				$max = isset( $field['max'] ) ? ' max="' . esc_attr( (string) $field['max'] ) . '"' : '';
				echo '<input type="number" step="1" class="small-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '"' . $min . $max . $dis . $descr . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				break;
			case 'enum':
				echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $dis . $descr . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				foreach ( (array) ( $field['choices'] ?? array() ) as $choice ) {
					echo '<option value="' . esc_attr( (string) $choice ) . '"' . selected( (string) $choice, (string) $value, false ) . '>' . esc_html( SettingsFields::choice_label( $tab, $key, (string) $choice ) ) . '</option>';
				}
				echo '</select>';
				break;
			case 'time':
				echo '<input type="time" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '"' . $dis . $descr . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				break;
			case 'email':
				echo '<input type="email" class="regular-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '"' . $dis . $descr . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				break;
			case 'email_list':
				echo '<textarea class="regular-text" rows="3" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $dis . $descr . '>' . esc_textarea( implode( "\n", array_map( 'strval', (array) $value ) ) ) . '</textarea>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				break;
			case 'textarea':
				echo '<textarea class="large-text" rows="4" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $dis . $descr . '>' . esc_textarea( (string) $value ) . '</textarea>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				break;
			case 'array':
				$this->render_array_field( $tab, $key, $field, (array) $value, $name, $disabled, $descr );
				break;
			case 'string':
			default:
				echo '<input type="text" class="regular-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( is_scalar( $value ) ? (string) $value : '' ) . '"' . $dis . $descr . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				break;
		}

		if ( null !== $error ) {
			echo '<p class="rc-admin-field-error" id="' . esc_attr( $id . '-error' ) . '"><strong>' . esc_html__( 'Fehler:', 'review-captain' ) . '</strong> ' . esc_html( $error ) . '</p>';
		}
		if ( 'privacy' === $tab && 'delete_data_on_uninstall' === $key ) {
			$this->render_uninstall_confirmation( (bool) $value, $disabled );
		}
		if ( '' !== $desc || $pro_lock ) {
			echo '<p class="description" id="' . esc_attr( $id . '-desc' ) . '">' . esc_html( $desc );
			if ( $pro_lock ) {
				echo ( '' !== $desc ? ' ' : '' ) . View::pro_lock( __( 'Von einer Erweiterung verwaltet; derzeit nicht änderbar.', 'review-captain' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</p>';
		}
		echo '</td></tr>';
	}

	/**
	 * Array field: role checkboxes for *_roles, otherwise checkboxes from 'choices'.
	 *
	 * @param string               $tab      Tab.
	 * @param string               $key      Key.
	 * @param array<string, mixed> $field    Field.
	 * @param array<int, mixed>    $value    Value.
	 * @param string               $name     Input name.
	 * @param bool                 $disabled Disabled.
	 * @param string               $descr    aria-describedby/aria-invalid attributes of the field (help text, error).
	 */
	private function render_array_field( string $tab, string $key, array $field, array $value, string $name, bool $disabled, string $descr = '' ): void {
		$roles   = str_ends_with( $key, '_roles' );
		// Choice labels come from SettingsFields::choice_label() (falls back to the raw value).
		$choices = array();
		if ( $roles ) {
			foreach ( wp_roles()->role_names as $role => $role_label ) {
				$choices[ (string) $role ] = function_exists( 'translate_user_role' ) ? translate_user_role( (string) $role_label ) : (string) $role_label;
			}
		} else {
			foreach ( (array) ( $field['choices'] ?? array() ) as $choice ) {
				$choices[ (string) $choice ] = SettingsFields::choice_label( $tab, $key, (string) $choice );
			}
		}
		$dis = $disabled ? ' disabled="disabled"' : '';
		// The fieldset carries the field id (target of the error list) and the help/error text.
		echo '<fieldset id="' . esc_attr( 'rc-' . $tab . '-' . $key ) . '" tabindex="-1"' . $descr . '><legend class="screen-reader-text">' . esc_html( SettingsFields::label( $tab, $key, $field ) ) . '</legend>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $descr is escaped in render_field().
		// Empty marker so "nothing checked" is submitted as an empty list.
		echo '<input type="hidden" name="' . esc_attr( $name . '[]' ) . '" value=""' . $dis . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		foreach ( $choices as $choice => $label ) {
			$id     = 'rc-' . $tab . '-' . $key . '-' . sanitize_html_class( $choice );
			$locked = $roles && 'administrator' === $choice;
			$on     = $locked || in_array( $choice, array_map( 'strval', $value ), true );
			echo '<label for="' . esc_attr( $id ) . '"><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name . '[]' ) . '" value="' . esc_attr( $choice ) . '"' . checked( $on, true, false ) . ( $locked || $disabled ? ' disabled="disabled"' : '' ) . ' /> ' . esc_html( $label ) . '</label>';
			if ( $locked ) {
				echo '<input type="hidden" name="' . esc_attr( $name . '[]' ) . '" value="administrator"' . $dis . ' /> <span class="description">' . esc_html__( '(immer)', 'review-captain' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '<br />';
		}
		echo '</fieldset>';
	}

	/**
	 * Confirmation for "Beim Löschen des Plugins alle Daten entfernen" (SET-RC-20, S10): a typed word, stored as time.
	 *
	 * @param bool $on       Switch state.
	 * @param bool $disabled Read-only.
	 */
	private function render_uninstall_confirmation( bool $on, bool $disabled ): void {
		$confirmed = (string) $this->settings()->get( 'privacy', 'delete_data_confirmed_at', '' );
		if ( $on && '' !== $confirmed && ! isset( $this->field_errors['errors']['delete_data_on_uninstall'] ) ) {
			/* translators: %s: date and time */
			echo '<p class="rc-admin-confirmed">' . esc_html( sprintf( __( 'Bestätigt am %s. Beim Löschen des Plugins werden alle Review-Captain-Daten dieser Website entfernt.', 'review-captain' ), View::date( $confirmed, true ) ) ) . '</p>';
			return;
		}
		if ( $on ) {
			echo '<p class="rc-admin-field-error">' . esc_html__( 'Eingeschaltet, aber noch nicht bestätigt: Beim Deinstallieren bleiben die Daten erhalten, bis du LÖSCHEN eintippst und speicherst.', 'review-captain' ) . '</p>';
		}
		echo '<p><label for="rc-privacy-uninstall-confirm">' . esc_html__( 'Zur Bestätigung LÖSCHEN eintippen (nur nötig beim Einschalten)', 'review-captain' ) . '</label><br />';
		echo '<input type="text" class="regular-text" id="rc-privacy-uninstall-confirm" name="' . esc_attr( self::INPUT . '[privacy][uninstall_confirm_word]' ) . '" value="" autocomplete="off" spellcheck="false" placeholder="LÖSCHEN"' . ( $disabled ? ' disabled="disabled"' : '' ) . ' /></p>';
	}

	/**
	 * Privacy tab: reset settings vs. full data deletion (separate, clearly labelled).
	 */
	private function render_privacy_actions(): void {
		echo '<hr /><h2>' . esc_html__( 'Einstellungen zurücksetzen', 'review-captain' ) . '</h2>';
		echo '<p>' . esc_html__( 'Setzt Einstellungen auf die Standardwerte zurück. Reviews, Profile, Verlauf und Protokolle bleiben erhalten. Der vorherige Stand wird gesichert und kann unter „Erweitert“ wiederhergestellt werden.', 'review-captain' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo View::form_fields( 'reset_settings' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<input type="hidden" name="tab" value="privacy" />';
		echo '<fieldset><legend class="screen-reader-text">' . esc_html__( 'Umfang', 'review-captain' ) . '</legend>';
		echo '<label><input type="radio" name="scope" value="tab" checked="checked" /> ' . esc_html__( 'Nur „Datenschutz & Aufbewahrung“', 'review-captain' ) . '</label><br />';
		echo '<label><input type="radio" name="scope" value="all" /> ' . esc_html__( 'Alle Einstellungen (Pro-Einstellungen bleiben unverändert)', 'review-captain' ) . '</label></fieldset>';
		echo '<p><label><input type="checkbox" name="confirm" value="1" required /> ' . esc_html__( 'Ich möchte die Einstellungen zurücksetzen.', 'review-captain' ) . '</label></p>';
		echo '<p><button type="submit" class="button">' . esc_html__( 'Einstellungen zurücksetzen', 'review-captain' ) . '</button></p></form>';

		echo '<div class="rc-admin-danger"><h2>' . esc_html__( 'Alle Plugin-Daten löschen', 'review-captain' ) . '</h2>';
		echo '<p>' . esc_html__( 'Reviews, Profile, Verlauf und Einstellungen werden hier nicht gelöscht. Eine vollständige Löschung aller Review-Captain-Daten dieser Website erfolgt nur, wenn „Beim Löschen des Plugins alle Daten entfernen“ aktiviert ist und du das Plugin anschließend unter „Plugins“ deaktivierst und löschst. Exportiere vorher deine Reviews.', 'review-captain' ) . '</p>';
		echo '<p>' . esc_html__( 'Einzelne Personen betreffende Löschanfragen bearbeitest du unter', 'review-captain' ) . ' <a href="' . esc_url( admin_url( 'erase-personal-data.php' ) ) . '">' . esc_html__( 'Werkzeuge › Personenbezogene Daten löschen', 'review-captain' ) . '</a>.</p></div>';
	}

	/**
	 * Advanced tab: config export/import with preview and rollback.
	 */
	private function render_maintenance(): void {
		echo '<hr /><h2>' . esc_html__( 'Konfiguration exportieren', 'review-captain' ) . '</h2>';
		echo '<p>' . esc_html__( 'Lädt die Einstellungen als JSON-Datei herunter – ohne Zugangsdaten und ohne personenbezogene Angaben. Reviews sind nicht enthalten.', 'review-captain' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo View::form_fields( 'export_settings' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<p><label><input type="checkbox" name="with_contacts" value="1" aria-describedby="rc-export-contacts-help" /> ' . esc_html__( 'Mit Kontaktangaben exportieren', 'review-captain' ) . '</label></p>';
		echo '<p class="description" id="rc-export-contacts-help">' . esc_html__( 'Nimmt Empfängeradressen der Benachrichtigungen, den Markennamen und die Absenderangaben mit in die Datei. Ohne Häkchen fehlen diese Felder; ein Import lässt sie dann unverändert. Zugangsdaten werden nie exportiert.', 'review-captain' ) . '</p>';
		echo '<p><button type="submit" class="button">' . esc_html__( 'Konfiguration herunterladen', 'review-captain' ) . '</button></p></form>';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$preview = 'preview' === View::req( $_GET, 'import' ) ? get_transient( self::IMPORT_TRANSIENT . get_current_user_id() ) : false;
		echo '<h2>' . esc_html__( 'Konfiguration importieren', 'review-captain' ) . '</h2>';
		if ( is_array( $preview ) && ! empty( $preview['diff'] ) ) {
			echo '<p>' . esc_html__( 'Folgende Werte werden geändert. Alle anderen Einstellungen bleiben unverändert.', 'review-captain' ) . '</p>';
			foreach ( (array) ( $preview['kept'] ?? array() ) as $kept ) {
				/* translators: 1: fields, 2: reason */
				echo View::notice( 'warning', sprintf( __( 'Nicht übernommen, bisherige Werte bleiben: %1$s. %2$s', 'review-captain' ), implode( ', ', array_map( static fn( $key ): string => SettingsFields::label( (string) $kept['tab'], (string) $key ), (array) $kept['fields'] ) ), (string) $kept['message'] ), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '<table class="widefat striped rc-admin-diff"><thead><tr><th scope="col">' . esc_html__( 'Bereich', 'review-captain' ) . '</th><th scope="col">' . esc_html__( 'Einstellung', 'review-captain' ) . '</th><th scope="col">' . esc_html__( 'Bisher', 'review-captain' ) . '</th><th scope="col">' . esc_html__( 'Neu', 'review-captain' ) . '</th></tr></thead><tbody>';
			$tabs = SettingsSchema::tabs();
			foreach ( (array) $preview['diff'] as $row ) {
				echo '<tr><td>' . esc_html( $tabs[ $row['tab'] ] ?? (string) $row['tab'] ) . '</td><td>' . esc_html( SettingsFields::label( (string) $row['tab'], (string) $row['key'] ) ) . '</td><td>' . esc_html( ConfigTransfer::display_value( $row['old'] ) ) . '</td><td><strong>' . esc_html( ConfigTransfer::display_value( $row['new'] ) ) . '</strong></td></tr>';
			}
			echo '</tbody></table>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			echo View::form_fields( 'import_apply' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<p><button type="submit" name="decision" value="apply" class="button button-primary">' . esc_html__( 'Import übernehmen', 'review-captain' ) . '</button> ';
			echo '<button type="submit" name="decision" value="discard" class="button">' . esc_html__( 'Verwerfen', 'review-captain' ) . '</button></p></form>';
		} else {
			echo '<p>' . esc_html__( 'Lade einen Export hoch oder füge den Inhalt ein. Vor dem Übernehmen siehst du eine Vorschau aller Änderungen; ungültige Werte werden wie beim Speichern korrigiert.', 'review-captain' ) . '</p>';
			echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			echo View::form_fields( 'import_settings' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<p><label for="rc-config-file">' . esc_html__( 'Datei (JSON)', 'review-captain' ) . '</label><br /><input type="file" id="rc-config-file" name="config_file" accept="application/json,.json" /></p>';
			echo '<p><label for="rc-config-json">' . esc_html__( 'oder Inhalt einfügen', 'review-captain' ) . '</label><br /><textarea id="rc-config-json" name="config_json" rows="6" class="large-text code"></textarea></p>';
			echo '<p><button type="submit" class="button">' . esc_html__( 'Vorschau anzeigen', 'review-captain' ) . '</button></p></form>';
		}

		$backup = $this->transfer()->get_backup();
		echo '<h2>' . esc_html__( 'Sicherung wiederherstellen', 'review-captain' ) . '</h2>';
		if ( null === $backup ) {
			echo '<p>' . esc_html__( 'Es ist noch keine Sicherung vorhanden. Sie wird automatisch vor jedem Import und jedem Zurücksetzen angelegt.', 'review-captain' ) . '</p>';
			return;
		}
		/* translators: %s: date */
		echo '<p>' . esc_html( sprintf( __( 'Sicherung vom %s. Beim Wiederherstellen wird der aktuelle Stand seinerseits gesichert.', 'review-captain' ), View::date( (string) $backup['created_at'], true ) ) ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo View::form_fields( 'rollback_settings' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<p><button type="submit" class="button">' . esc_html__( 'Sicherung wiederherstellen', 'review-captain' ) . '</button></p></form>';
	}
}
