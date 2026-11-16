<?php
/**
 * Settings page with tabs (SET-007, SET-009..SET-019).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Admin;

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
	 * Role keys of the access tab and the capability each one grants.
	 */
	private const ROLE_CAPS = array(
		'moderate_roles' => Capabilities::MODERATE,
		'settings_roles' => Capabilities::MANAGE_SETTINGS,
	);

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( private Plugin $plugin ) {}

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
	 * Whether Pro-owned fields may be written (Pro add-on opts in; the Core never does).
	 */
	public static function pro_writable(): bool {
		/**
		 * Filter whether Pro-owned settings fields are editable on the settings page.
		 * The Pro add-on returns true while its license/entitlement allows it.
		 *
		 * @param bool $writable Default false.
		 */
		return (bool) apply_filters( 'review_captain_pro_settings_writable', false );
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
	 * Save one tab. Only the given tab is processed; Pro fields stay untouched unless Pro opts in.
	 *
	 * @param string               $tab   Tab key.
	 * @param array<string, mixed> $input Raw (unslashed) input keyed by tab.
	 * @return array{changed: list<string>, invalid: list<string>}
	 */
	public function save_tab( string $tab, array $input ): array {
		$fields  = SettingsSchema::fields()[ $tab ] ?? array();
		$raw     = isset( $input[ $tab ] ) && is_array( $input[ $tab ] ) ? $input[ $tab ] : array();
		$pro     = self::pro_writable();
		$values  = $this->settings()->sanitize( $input, array( $tab ), $pro );
		$invalid = array();

		foreach ( $fields as $key => $field ) {
			if ( ( 'pro' === $field['owner'] && ! $pro ) || ! array_key_exists( $key, $raw ) || ! is_scalar( $raw[ $key ] ) ) {
				continue;
			}
			if ( in_array( $field['type'], array( 'int', 'enum', 'time', 'email' ), true ) ) {
				$given = trim( (string) $raw[ $key ] );
				if ( 'email' === $field['type'] && '' === $given ) {
					continue;
				}
				if ( (string) $values[ $tab ][ $key ] !== $given ) {
					$invalid[] = $tab . '-' . $key;
				}
			}
		}

		if ( 'access' === $tab ) {
			$existing = array_keys( wp_roles()->role_objects );
			foreach ( array( 'moderate_roles', 'settings_roles' ) as $key ) {
				$roles                   = array_values( array_intersect( (array) ( $values['access'][ $key ] ?? array() ), $existing ) );
				$values['access'][ $key ] = array_values( array_unique( array_merge( array( 'administrator' ), $roles ) ) );
			}
			$values['access'] = self::keep_unheld_role_keys( $values['access'], $this->settings()->all()['access'] ?? array() );
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
					'tab'    => $tab,
					'fields' => $changed,
				)
			);
		}
		return array(
			'changed' => $changed,
			'invalid' => $invalid,
		);
	}

	/**
	 * Apply role selections to capabilities (administrator always keeps everything).
	 *
	 * @param array<string, mixed> $access Access tab values.
	 */
	private function sync_roles( array $access ): void {
		$map = array(
			Capabilities::MODERATE        => (array) ( $access['moderate_roles'] ?? array() ),
			Capabilities::MANAGE_SETTINGS => (array) ( $access['settings_roles'] ?? array() ),
		);
		foreach ( $map as $cap => $roles ) {
			if ( ! current_user_can( $cap ) ) {
				// Only capabilities the acting user holds may be (re)assigned (capabilities.md §3).
				continue;
			}
			$roles = array_values( array_map( 'strval', $roles ) );
			Capabilities::sync_roles( $cap, $roles );
			$this->plugin->audit()->record(
				'roles.capabilities_changed',
				'capability',
				$cap,
				array(
					'capability' => $cap,
					'roles'      => array_values( array_unique( array_merge( $roles, array( 'administrator' ) ) ) ),
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
		$tabs = array_values( array_intersect( $tabs, self::writable_tabs() ) );
		if ( ! $tabs ) {
			return array();
		}
		$this->transfer()->backup();
		$before = (array) ( $this->settings()->all()['access'] ?? array() );
		$this->settings()->reset( $tabs );
		if ( in_array( 'access', $tabs, true ) ) {
			$this->restore_unheld_role_keys( $before );
			$this->sync_roles( $this->settings()->all()['access'] ?? array() );
		}
		$this->plugin->audit()->record(
			'settings.reset',
			'settings',
			implode( ',', $tabs ),
			array( 'fields' => $tabs )
		);
		return $tabs;
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
		$doc = $this->transfer()->export();
		$this->plugin->audit()->record( 'settings.exported', 'settings', '', array( 'version' => SettingsSchema::VERSION ) );
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
		$preview = $this->transfer()->preview( $json, self::writable_tabs() );
		if ( null === $preview ) {
			View::redirect( Notices::url( $back, 'import_invalid' ) );
			return;
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
		$current = $this->settings()->all();
		foreach ( $tabs as $tab ) {
			if ( isset( $values[ $tab ] ) && is_array( $values[ $tab ] ) ) {
				$current[ $tab ] = $values[ $tab ];
			}
		}
		if ( in_array( 'access', $tabs, true ) ) {
			foreach ( array( 'moderate_roles', 'settings_roles' ) as $role_key ) {
				$current['access'][ $role_key ] = array_values( array_unique( array_merge( array( 'administrator' ), (array) ( $current['access'][ $role_key ] ?? array() ) ) ) );
			}
			$current['access'] = self::keep_unheld_role_keys( $current['access'], $this->settings()->all()['access'] ?? array() );
		}
		$changed = $this->transfer()->apply( $current );
		if ( in_array( 'access', $tabs, true ) ) {
			$this->sync_roles( $current['access'] );
		}
		$this->plugin->audit()->record( 'settings.imported', 'settings', '', array( 'fields' => $changed ) );
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
		$before  = (array) ( $this->settings()->all()['access'] ?? array() );
		$changed = $this->transfer()->rollback();
		if ( null === $changed ) {
			View::redirect( Notices::url( $back, 'rollback_missing' ) );
			return;
		}
		$this->settings()->flush();
		$this->restore_unheld_role_keys( $before );
		$this->sync_roles( $this->settings()->all()['access'] ?? array() );
		$this->plugin->audit()->record( 'settings.restored', 'settings', '', array( 'fields' => $changed ) );
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
		$tab    = self::current_tab();
		$tabs   = SettingsSchema::tabs();
		$schema = SettingsSchema::fields();
		$values = $this->settings()->all();
		$pro    = self::pro_writable();

		echo '<div class="wrap rc-admin">';
		echo View::header( __( 'Einstellungen', 'review-captain' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo Notices::render_current(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		echo '<nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'Einstellungsbereiche', 'review-captain' ) . '">';
		foreach ( $tabs as $key => $label ) {
			$has_pro_only = $this->tab_is_pro_only( $schema[ $key ] ?? array() );
			$active       = $key === $tab;
			echo '<a href="' . esc_url( self::tab_url( $key ) ) . '" class="nav-tab' . ( $active ? ' nav-tab-active' : '' ) . '"' . ( $active ? ' aria-current="page"' : '' ) . '>' . esc_html( $label );
			if ( $has_pro_only && ! $pro ) {
				echo ' <span class="dashicons dashicons-lock" aria-hidden="true"></span><span class="screen-reader-text"> ' . esc_html__( '(Review Captain Pro)', 'review-captain' ) . '</span>';
			}
			echo '</a>';
		}
		echo '</nav>';

		$this->render_tab_notes( $tab );

		$writable = self::can_write_tab( $tab );
		if ( ! $writable ) {
			echo View::notice( 'warning', 'access' === $tab ? __( 'Rollen kann nur ändern, wer selbst Benutzerrollen vergeben darf.', 'review-captain' ) : __( 'Diese Einstellungen darf nur ändern, wer das Recht „Datenschutz verwalten“ hat.', 'review-captain' ), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="rc-admin-settings-form">';
		echo View::form_fields( 'save_settings' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<input type="hidden" name="tab" value="' . esc_attr( $tab ) . '" />';

		$fields   = $schema[ $tab ] ?? array();
		$sections = SettingsFields::sections()[ $tab ] ?? array();
		$placed   = array();
		$advanced = array();
		foreach ( $fields as $key => $field ) {
			if ( ! empty( $field['advanced'] ) ) {
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
		$rest = array_values( array_diff( array_keys( $fields ), $placed, $advanced ) );
		if ( $rest ) {
			$this->render_section( __( 'Weitere Optionen', 'review-captain' ), $tab, $rest, $fields, $values, $writable, $pro );
		}
		if ( $advanced ) {
			$open = 'advanced' === $tab;
			foreach ( $advanced as $key ) {
				if ( ( $values[ $tab ][ $key ] ?? null ) !== $fields[ $key ]['default'] ) {
					$open = true;
				}
			}
			echo '<details class="rc-admin-advanced"' . ( $open ? ' open' : '' ) . '><summary>' . esc_html__( 'Erweitert anzeigen', 'review-captain' ) . '</summary>';
			$this->render_section( '', $tab, $advanced, $fields, $values, $writable, $pro );
			echo '</details>';
		}

		$any_writable = $writable && array_filter( $fields, static fn( $f ) => 'pro' !== $f['owner'] || $pro );
		if ( $any_writable ) {
			echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Änderungen speichern', 'review-captain' ) . '</button></p>';
		}
		echo '</form>';

		if ( 'privacy' === $tab ) {
			$this->render_privacy_actions();
		}
		if ( 'advanced' === $tab ) {
			$this->render_maintenance();
		}
		echo '</div>';
	}

	/**
	 * Whether every field of a tab is Pro-owned.
	 *
	 * @param array<string, array<string, mixed>> $fields Fields.
	 */
	private function tab_is_pro_only( array $fields ): bool {
		if ( ! $fields ) {
			return false;
		}
		foreach ( $fields as $field ) {
			if ( 'pro' !== ( $field['owner'] ?? 'core' ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Context notes per tab.
	 *
	 * @param string $tab Tab.
	 */
	private function render_tab_notes( string $tab ): void {
		$fields = SettingsSchema::fields()[ $tab ] ?? array();
		$notes  = array();
		if ( $this->tab_is_pro_only( $fields ) && ! self::pro_writable() ) {
			$notes[] = array( 'info', __( 'Diese Einstellungen benötigen Review Captain Pro. Sie sind sichtbar, aber gesperrt; gespeicherte Werte bleiben unverändert erhalten.', 'review-captain' ) );
		}
		if ( 'requests' === $tab ) {
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
	 * @param bool                                $pro      Pro fields writable.
	 */
	private function render_section( string $heading, string $tab, array $keys, array $fields, array $values, bool $writable, bool $pro ): void {
		if ( '' !== $heading ) {
			echo '<h2>' . esc_html( $heading ) . '</h2>';
		}
		echo '<table class="form-table" role="presentation"><tbody>';
		foreach ( $keys as $key ) {
			$field    = $fields[ $key ];
			$disabled = ! $writable || ( 'pro' === $field['owner'] && ! $pro );
			$this->render_field( $tab, $key, $field, $values[ $tab ][ $key ] ?? $field['default'], $disabled, 'pro' === $field['owner'] && ! $pro );
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
	 * @param bool                 $pro_lock Show Pro lock.
	 */
	public function render_field( string $tab, string $key, array $field, mixed $value, bool $disabled, bool $pro_lock ): void {
		$id    = 'rc-' . $tab . '-' . $key;
		$name  = self::INPUT . '[' . $tab . '][' . $key . ']';
		$label = SettingsFields::label( $tab, $key, $field );
		$desc  = SettingsFields::description( $tab, $key, $field );
		$dis   = $disabled ? ' disabled="disabled"' : '';
		$descr = '' !== $desc || $pro_lock ? ' aria-describedby="' . esc_attr( $id . '-desc' ) . '"' : '';

		echo '<tr><th scope="row">';
		echo in_array( $field['type'], array( 'bool', 'array' ), true ) ? esc_html( $label ) : '<label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
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
			case 'textarea':
				echo '<textarea class="large-text" rows="4" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $dis . $descr . '>' . esc_textarea( (string) $value ) . '</textarea>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				break;
			case 'array':
				$this->render_array_field( $tab, $key, $field, (array) $value, $name, $disabled );
				break;
			case 'string':
			default:
				echo '<input type="text" class="regular-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( is_scalar( $value ) ? (string) $value : '' ) . '"' . $dis . $descr . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				break;
		}

		if ( '' !== $desc || $pro_lock ) {
			echo '<p class="description" id="' . esc_attr( $id . '-desc' ) . '">' . esc_html( $desc );
			if ( $pro_lock ) {
				echo ( '' !== $desc ? ' ' : '' ) . View::pro_lock( __( 'Benötigt Review Captain Pro', 'review-captain' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
	 */
	private function render_array_field( string $tab, string $key, array $field, array $value, string $name, bool $disabled ): void {
		$roles   = str_ends_with( $key, '_roles' );
		$choices = array();
		if ( $roles ) {
			foreach ( wp_roles()->role_names as $role => $role_label ) {
				$choices[ (string) $role ] = function_exists( 'translate_user_role' ) ? translate_user_role( (string) $role_label ) : (string) $role_label;
			}
		} else {
			foreach ( (array) ( $field['choices'] ?? array() ) as $choice ) {
				$choices[ (string) $choice ] = (string) $choice;
			}
		}
		$dis = $disabled ? ' disabled="disabled"' : '';
		echo '<fieldset><legend class="screen-reader-text">' . esc_html( SettingsFields::label( $tab, $key, $field ) ) . '</legend>';
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
		if ( $roles && 'access' === $tab && 'settings_roles' === $key ) {
			$this->render_capability_table();
		}
	}

	/**
	 * Read-only table of all plugin capabilities and the roles holding them.
	 */
	private function render_capability_table(): void {
		$labels = array(
			Capabilities::MODERATE        => __( 'Reviews moderieren', 'review-captain' ),
			Capabilities::MANAGE_SOURCES  => __( 'Quellen verwalten', 'review-captain' ),
			Capabilities::MANAGE_REQUESTS => __( 'Anfragen verwalten (Pro)', 'review-captain' ),
			Capabilities::VIEW_REPORTS    => __( 'Berichte & Diagnose ansehen', 'review-captain' ),
			Capabilities::MANAGE_TRUST    => __( 'Vertrauenssignale verwalten (Pro)', 'review-captain' ),
			Capabilities::MANAGE_PRIVACY  => __( 'Datenschutz verwalten', 'review-captain' ),
			Capabilities::MANAGE_SETTINGS => __( 'Einstellungen ändern', 'review-captain' ),
		);
		echo '<details class="rc-admin-advanced"><summary>' . esc_html__( 'Alle Rechte und Rollen anzeigen', 'review-captain' ) . '</summary>';
		echo '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Recht', 'review-captain' ) . '</th><th scope="col">' . esc_html__( 'Rollen', 'review-captain' ) . '</th></tr></thead><tbody>';
		$roles = wp_roles()->role_objects;
		foreach ( Capabilities::all() as $cap ) {
			$holders = array();
			foreach ( $roles as $name => $role ) {
				if ( method_exists( $role, 'has_cap' ) && $role->has_cap( $cap ) ) {
					$holders[] = (string) $name;
				}
			}
			echo '<tr><td>' . esc_html( $labels[ $cap ] ?? $cap ) . '<br /><code>' . esc_html( $cap ) . '</code></td><td>' . esc_html( $holders ? implode( ', ', $holders ) : '–' ) . '</td></tr>';
		}
		echo '</tbody></table></details>';
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
		echo '<p>' . esc_html__( 'Reviews, Profile, Verlauf und Einstellungen werden hier nicht gelöscht. Eine vollständige Löschung aller Review-Captain-Daten dieser Website erfolgt nur, wenn „Daten beim Deinstallieren löschen“ aktiviert ist und du das Plugin anschließend unter „Plugins“ deaktivierst und löschst. Exportiere vorher deine Reviews.', 'review-captain' ) . '</p>';
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
		echo '<p><button type="submit" class="button">' . esc_html__( 'Konfiguration herunterladen', 'review-captain' ) . '</button></p></form>';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$preview = 'preview' === View::req( $_GET, 'import' ) ? get_transient( self::IMPORT_TRANSIENT . get_current_user_id() ) : false;
		echo '<h2>' . esc_html__( 'Konfiguration importieren', 'review-captain' ) . '</h2>';
		if ( is_array( $preview ) && ! empty( $preview['diff'] ) ) {
			echo '<p>' . esc_html__( 'Folgende Werte werden geändert. Alle anderen Einstellungen bleiben unverändert.', 'review-captain' ) . '</p>';
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
