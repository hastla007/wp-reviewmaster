<?php
/**
 * Settings page with tabs, progressive disclosure, role matrix, danger zone and config import/export (SET-007 – SET-019).
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Admin;

use ReviewCaptain\Audit\AuditLog;
use ReviewCaptain\Database\Schema as DbSchema;
use ReviewCaptain\Security\Capabilities;
use ReviewCaptain\Settings\Schema;
use ReviewCaptain\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class SettingsPage {

	private const PAGE = 'review-captain-settings';

	/** Changes to these keys are audited (SET-019). */
	private const AUDITED = array( 'sender_name', 'sender_email', 'cooldown_days', 'daily_send_limit', 'existing_customer_basis', 'default_status', 'retention_requests_days', 'retention_logs_days', 'delete_data_on_uninstall', 'external_services_consent_mode' );

	public static function register_actions(): void {
		add_action( 'admin_post_review_captain_save_settings', array( self::class, 'handle_save' ) );
		add_action( 'admin_post_review_captain_reset_settings', array( self::class, 'handle_reset' ) );
		add_action( 'admin_post_review_captain_save_roles', array( self::class, 'handle_roles' ) );
		add_action( 'admin_post_review_captain_erase_all', array( self::class, 'handle_erase_all' ) );
		add_action( 'admin_post_review_captain_export_config', array( self::class, 'handle_export_config' ) );
		add_action( 'admin_post_review_captain_import_config', array( self::class, 'handle_import_config' ) );
	}

	/**
	 * @return array<string,array{label:string, help:string}>
	 */
	private static function labels(): array {
		return array(
			'brand_name'                            => array( __( 'Brand name', 'review-captain' ), __( 'Used in templates and widgets.', 'review-captain' ) ),
			'default_locale'                        => array( __( 'Default language', 'review-captain' ), __( 'Language for new templates, e.g. de_AT.', 'review-captain' ) ),
			'date_format'                           => array( __( 'Date format', 'review-captain' ), __( 'PHP date format for widgets.', 'review-captain' ) ),
			'new_review_type_default'               => array( __( 'Default type for new reviews', 'review-captain' ), '' ),
			'sync_interval_hours'                   => array( __( 'Sync frequency (hours)', 'review-captain' ), __( 'How often new reviews are fetched. More frequent checks use more of the platform limit; more than 24 hours is not possible because platforms require fresh data.', 'review-captain' ) ),
			'sync_lookback_days'                    => array( __( 'First import: days back', 'review-captain' ), '' ),
			'sync_max_items_per_run'                => array( __( 'Maximum reviews per run', 'review-captain' ), '' ),
			'sync_quota_warn_percent'               => array( __( 'Warn at platform limit usage (%)', 'review-captain' ), '' ),
			'sender_name'                           => array( __( 'Sender name', 'review-captain' ), '' ),
			'sender_email'                          => array( __( 'Sender e-mail', 'review-captain' ), __( 'Invitations are only sent after this address is set and tested.', 'review-captain' ) ),
			'reply_to'                              => array( __( 'Reply-to address', 'review-captain' ), '' ),
			'test_recipient'                        => array( __( 'Test recipient', 'review-captain' ), '' ),
			'send_window_start'                     => array( __( 'Send window from', 'review-captain' ), __( 'In the time zone of this website.', 'review-captain' ) ),
			'send_window_end'                       => array( __( 'Send window until', 'review-captain' ), '' ),
			'daily_send_limit'                      => array( __( 'Maximum invitations per day', 'review-captain' ), '' ),
			'cooldown_days'                         => array( __( 'Minimum days between invitations to the same person', 'review-captain' ), __( 'Applies across all campaigns.', 'review-captain' ) ),
			'reminders_default'                     => array( __( 'Enable reminders for new campaigns', 'review-captain' ), '' ),
			'existing_customer_basis'               => array( __( 'Allow existing-customer rule', 'review-captain' ), __( 'Allows invitations without explicit consent when the legal requirements are met (e.g. customer was informed about the right to object at purchase). Have this checked legally – if in doubt, leave it off.', 'review-captain' ) ),
			'bounce_behavior'                       => array( __( 'Undeliverable addresses', 'review-captain' ), '' ),
			'default_status'                        => array( __( 'New reviews', 'review-captain' ), __( 'Recommended: publish only after moderation. Immediate publication can make spam or unlawful content public.', 'review-captain' ) ),
			'honeypot_enabled'                      => array( __( 'Use spam trap', 'review-captain' ), '' ),
			'rate_limit_per_hour'                   => array( __( 'Submissions per hour and visitor', 'review-captain' ), '' ),
			'min_text_length'                       => array( __( 'Minimum text length', 'review-captain' ), '' ),
			'max_text_length'                       => array( __( 'Maximum text length', 'review-captain' ), '' ),
			'require_email'                         => array( __( 'Require e-mail address', 'review-captain' ), __( 'The address is never published.', 'review-captain' ) ),
			'consent_text'                          => array( __( 'Consent text', 'review-captain' ), __( 'If set, visitors must tick this text before submitting.', 'review-captain' ) ),
			'notify_moderators'                     => array( __( 'Notify about new reviews', 'review-captain' ), '' ),
			'show_date'                             => array( __( 'Show date', 'review-captain' ), '' ),
			'author_mode'                           => array( __( 'Show names as', 'review-captain' ), '' ),
			'show_avatar'                           => array( __( 'Show profile pictures', 'review-captain' ), __( 'Pictures from other platforms are loaded from their servers.', 'review-captain' ) ),
			'empty_state'                           => array( __( 'Widgets without reviews', 'review-captain' ), '' ),
			'load_css'                              => array( __( 'Load plugin styles', 'review-captain' ), '' ),
			'source_attribution'                    => array( __( 'Show source of external reviews', 'review-captain' ), __( 'Always on. External reviews must show where they come from.', 'review-captain' ) ),
			'retention_requests_days'               => array( __( 'Keep invitation records (days)', 'review-captain' ), __( 'Afterwards records are anonymized.', 'review-captain' ) ),
			'retention_pending_requests_days'       => array( __( 'Unsent invitations expire after (days)', 'review-captain' ), '' ),
			'retention_logs_days'                   => array( __( 'Keep technical logs (days)', 'review-captain' ), '' ),
			'retention_rejected_reviews_days'       => array( __( 'Delete rejected reviews after (days)', 'review-captain' ), '' ),
			'retention_media_after_revocation_days' => array( __( 'Delete media after withdrawal (days)', 'review-captain' ), '' ),
			'erase_review_text_on_request'          => array( __( 'Delete review text on erasure requests', 'review-captain' ), __( 'If off, the review stays anonymized.', 'review-captain' ) ),
			'external_services_consent_mode'        => array( __( 'External content', 'review-captain' ), __( 'Videos and official platform widgets are loaded only after a click, or when your consent tool allows it.', 'review-captain' ) ),
			'delete_data_on_uninstall'              => array( __( 'Delete all data when the plugin is deleted', 'review-captain' ), __( 'Off by default: reviews stay in the database when the plugin is removed.', 'review-captain' ) ),
			'job_runner'                            => array( __( 'Background tasks run via', 'review-captain' ), __( '“Automatic” uses Action Scheduler if available, otherwise WP-Cron. Choose “System cron” only if a server cron job runs wp review-captain queue run every minute.', 'review-captain' ) ),
			'job_time_budget_seconds'               => array( __( 'Time budget per run (seconds)', 'review-captain' ), '' ),
		);
	}

	public static function render(): void {
		$tab    = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$groups = Schema::group_labels();
		if ( ! isset( $groups[ $tab ] ) ) {
			$tab = 'general';
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Settings', 'review-captain' ) . '</h1>';
		Menu::notices();
		echo '<nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'Settings sections', 'review-captain' ) . '">';
		foreach ( $groups as $key => $label ) {
			printf( '<a href="%s" class="nav-tab%s"%s>%s</a>', esc_url( admin_url( 'admin.php?page=' . self::PAGE . '&tab=' . $key ) ), $key === $tab ? ' nav-tab-active' : '', $key === $tab ? ' aria-current="page"' : '', esc_html( $label ) );
		}
		echo '</nav>';

		if ( 'access' === $tab ) {
			self::render_roles();
			echo '</div>';
			return;
		}

		$entitlement = Schema::GROUP_ENTITLEMENTS[ $tab ] ?? null;
		$active      = null === $entitlement || (bool) apply_filters( 'review_captain_has_entitlement', false, $entitlement );
		if ( ! $active ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'These settings take effect with Review Captain Pro. Values you save are kept and are not changed if a license expires.', 'review-captain' ) . '</p></div>';
		}

		$labels = self::labels();
		$fields = array_filter( Schema::fields_for_group( $tab ), static fn( $f ) => empty( $f['internal'] ) );
		echo '<form method="post" action="' . esc_url( Menu::post_url() ) . '"><input type="hidden" name="action" value="review_captain_save_settings"><input type="hidden" name="group" value="' . esc_attr( $tab ) . '">';
		wp_nonce_field( 'rc_save_settings_' . $tab );
		foreach ( array( 'basic', 'advanced' ) as $level ) {
			$level_fields = array_filter( $fields, static fn( $f ) => $f['level'] === $level );
			if ( ! $level_fields ) {
				continue;
			}
			if ( 'advanced' === $level ) {
				echo '<details class="rc-advanced"><summary>' . esc_html__( 'Advanced options', 'review-captain' ) . '</summary>';
			}
			echo '<table class="form-table" role="presentation"><tbody>';
			foreach ( $level_fields as $key => $field ) {
				self::render_field( $key, $field, $labels[ $key ] ?? array( $key, '' ), ! $active );
			}
			echo '</tbody></table>';
			if ( 'advanced' === $level ) {
				echo '</details>';
			}
		}
		submit_button();
		echo '</form>';

		echo '<form method="post" action="' . esc_url( Menu::post_url() ) . '" class="rc-inline" onsubmit="return confirm(\'' . esc_js( __( 'Reset this section to its defaults?', 'review-captain' ) ) . '\');"><input type="hidden" name="action" value="review_captain_reset_settings"><input type="hidden" name="group" value="' . esc_attr( $tab ) . '">';
		wp_nonce_field( 'rc_reset_settings' );
		submit_button( __( 'Reset this section', 'review-captain' ), 'secondary', 'submit', false );
		echo '</form>';

		if ( 'privacy' === $tab ) {
			self::render_danger_zone();
		}
		if ( 'advanced' === $tab ) {
			self::render_config_io();
		}
		echo '</div>';
	}

	/**
	 * @param array<string,mixed> $field Field definition.
	 * @param array<int,string>   $label [label, help].
	 */
	private static function render_field( string $key, array $field, array $label, bool $disabled ): void {
		$value = Settings::get( $key );
		$name  = 'settings[' . $key . ']';
		$id    = 'rc-set-' . $key;
		$dis   = ( $disabled || ! empty( $field['locked'] ) ) ? ' disabled' : '';
		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label[0] ) . '</label></th><td>';
		switch ( $field['type'] ) {
			case 'bool':
				printf( '<input type="checkbox" id="%s" name="%s" value="1"%s%s>', esc_attr( $id ), esc_attr( $name ), checked( (bool) $value, true, false ), esc_attr( $dis ) );
				break;
			case 'int':
				printf( '<input type="number" id="%s" name="%s" value="%d" min="%d" max="%d" class="small-text"%s>', esc_attr( $id ), esc_attr( $name ), (int) $value, (int) $field['min'], (int) min( $field['max'], 999999 ), esc_attr( $dis ) );
				break;
			case 'enum':
				printf( '<select id="%s" name="%s"%s>', esc_attr( $id ), esc_attr( $name ), esc_attr( $dis ) );
				foreach ( $field['options'] as $opt ) {
					printf( '<option value="%s"%s>%s</option>', esc_attr( $opt ), selected( $value, $opt, false ), esc_html( self::option_label( $opt ) ) );
				}
				echo '</select>';
				break;
			case 'textarea':
				printf( '<textarea id="%s" name="%s" rows="3" class="large-text"%s>%s</textarea>', esc_attr( $id ), esc_attr( $name ), esc_attr( $dis ), esc_textarea( (string) $value ) );
				break;
			case 'time':
				printf( '<input type="time" id="%s" name="%s" value="%s"%s>', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ), esc_attr( $dis ) );
				break;
			default:
				printf( '<input type="%s" id="%s" name="%s" value="%s" class="regular-text"%s>', 'email' === $field['type'] ? 'email' : 'text', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ), esc_attr( $dis ) );
		}
		if ( '' !== $label[1] ) {
			echo '<p class="description">' . esc_html( $label[1] ) . '</p>';
		}
		echo '</td></tr>';
	}

	private static function option_label( string $opt ): string {
		$labels = array(
			'service'            => __( 'Service', 'review-captain' ),
			'product'            => __( 'Product', 'review-captain' ),
			'app'                => __( 'App', 'review-captain' ),
			'event'              => __( 'Event', 'review-captain' ),
			'voucher'            => __( 'Voucher', 'review-captain' ),
			'general'            => __( 'General', 'review-captain' ),
			'suppress'           => __( 'Block address for future invitations', 'review-captain' ),
			'log_only'           => __( 'Only log', 'review-captain' ),
			'pending'            => __( 'Publish after moderation (recommended)', 'review-captain' ),
			'approved'           => __( 'Publish immediately', 'review-captain' ),
			'full'               => __( 'Full name', 'review-captain' ),
			'first_name_initial' => __( 'First name and initial', 'review-captain' ),
			'initials'           => __( 'Initials', 'review-captain' ),
			'anonymous'          => __( 'No name', 'review-captain' ),
			'hide'               => __( 'Show nothing', 'review-captain' ),
			'message'            => __( 'Show “No reviews yet”', 'review-captain' ),
			'click_to_load'      => __( 'Load after click', 'review-captain' ),
			'consent_hook'       => __( 'Let my consent tool decide', 'review-captain' ),
			'auto'               => __( 'Automatic', 'review-captain' ),
			'wp_cron'            => 'WP-Cron',
			'system_cron'        => __( 'System cron (WP-CLI)', 'review-captain' ),
		);
		return $labels[ $opt ] ?? $opt;
	}

	private static function render_roles(): void {
		$map    = Capabilities::current_map();
		$roles  = wp_roles()->get_names();
		$labels = array(
			'rc_view_reviews'        => __( 'See reviews', 'review-captain' ),
			'rc_moderate_reviews'    => __( 'Moderate', 'review-captain' ),
			'rc_manage_reviews'      => __( 'Add/import/export', 'review-captain' ),
			'rc_manage_widgets'      => __( 'Widgets', 'review-captain' ),
			'rc_manage_testimonials' => __( 'Testimonials', 'review-captain' ),
			'rc_manage_trust'        => __( 'Trust badges', 'review-captain' ),
			'rc_connect_sources'     => __( 'Connect sources', 'review-captain' ),
			'rc_manage_requests'     => __( 'Campaigns', 'review-captain' ),
			'rc_send_requests'       => __( 'Send invitations', 'review-captain' ),
			'rc_view_reports'        => __( 'Reports & diagnostics', 'review-captain' ),
			'rc_manage_privacy'      => __( 'Privacy & deletion', 'review-captain' ),
			'rc_manage_settings'     => __( 'Settings', 'review-captain' ),
		);
		echo '<p>' . esc_html__( 'Choose which roles may do what. Administrators always keep all rights.', 'review-captain' ) . '</p>';
		if ( is_multisite() ) {
			echo '<p class="description">' . esc_html__( 'Multisite: these rights and all Review Captain data apply to this site only.', 'review-captain' ) . '</p>';
		}
		echo '<form method="post" action="' . esc_url( Menu::post_url() ) . '"><input type="hidden" name="action" value="review_captain_save_roles">';
		wp_nonce_field( 'rc_save_roles' );
		echo '<div class="rc-table-scroll"><table class="widefat striped rc-roles"><thead><tr><th>' . esc_html__( 'Role', 'review-captain' ) . '</th>';
		foreach ( $labels as $l ) {
			echo '<th scope="col">' . esc_html( $l ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $roles as $role => $name ) {
			$admin = 'administrator' === $role;
			echo '<tr><th scope="row">' . esc_html( translate_user_role( $name ) ) . '</th>';
			foreach ( array_keys( $labels ) as $cap ) {
				printf(
					'<td><input type="checkbox" name="roles[%s][]" value="%s" aria-label="%s"%s%s></td>',
					esc_attr( $role ),
					esc_attr( $cap ),
					esc_attr( translate_user_role( $name ) . ': ' . $labels[ $cap ] ),
					checked( in_array( $cap, $map[ $role ] ?? array(), true ), true, false ),
					$admin ? ' disabled' : ''
				);
			}
			echo '</tr>';
		}
		echo '</tbody></table></div>';
		submit_button( __( 'Save roles', 'review-captain' ) );
		echo '</form>';
	}

	private static function render_danger_zone(): void {
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'rc_manage_privacy' ) ) {
			return;
		}
		$domain = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		echo '<div class="rc-danger postbox"><div class="inside"><h2>' . esc_html__( 'Danger zone: delete all Review Captain data', 'review-captain' ) . '</h2>';
		echo '<p>' . esc_html__( 'Deletes all reviews, widgets, testimonials, logs and settings of this site immediately. This cannot be undone. Resetting settings above does not delete any content.', 'review-captain' ) . '</p>';
		echo '<form method="post" action="' . esc_url( Menu::post_url() ) . '"><input type="hidden" name="action" value="review_captain_erase_all">';
		wp_nonce_field( 'rc_erase_all' );
		/* translators: %s: domain */
		echo '<p><label for="rc-erase-confirm">' . esc_html( sprintf( __( 'Type %s to confirm', 'review-captain' ), $domain ) ) . '</label><br><input type="text" id="rc-erase-confirm" name="confirm" autocomplete="off"></p>';
		submit_button( __( 'Delete all data now', 'review-captain' ), 'delete', 'submit', false );
		echo '</form></div></div>';
	}

	private static function render_config_io(): void {
		echo '<div class="postbox"><div class="inside"><h2>' . esc_html__( 'Export / import configuration', 'review-captain' ) . '</h2>';
		echo '<p>' . esc_html__( 'The export contains settings only – no reviews, no personal data and no access credentials. After importing, platforms must be connected again.', 'review-captain' ) . '</p>';
		echo '<form method="post" action="' . esc_url( Menu::post_url() ) . '" class="rc-inline"><input type="hidden" name="action" value="review_captain_export_config">';
		wp_nonce_field( 'rc_export_config' );
		submit_button( __( 'Download configuration', 'review-captain' ), 'secondary', 'submit', false );
		echo '</form>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( Menu::post_url() ) . '"><input type="hidden" name="action" value="review_captain_import_config">';
		wp_nonce_field( 'rc_import_config' );
		echo '<p><input type="file" name="config" accept=".json,application/json" required> ';
		submit_button( __( 'Import configuration', 'review-captain' ), 'secondary', 'submit', false );
		echo '</p></form></div></div>';
	}

	public static function handle_save(): void {
		$group = isset( $_POST['group'] ) ? sanitize_key( wp_unslash( $_POST['group'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked below.
		Capabilities::check( 'rc_manage_settings', 'rc_save_settings_' . $group );
		if ( ! in_array( $group, Schema::GROUPS, true ) || 'access' === $group ) {
			Menu::back( self::PAGE );
		}
		if ( 'privacy' === $group && ! current_user_can( 'rc_manage_privacy' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'review-captain' ), '', array( 'response' => 403 ) );
		}
		$input  = isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field.
		$result = Settings::sanitize_group( $group, $input );
		$entitlement = Schema::GROUP_ENTITLEMENTS[ $group ] ?? null;
		if ( null !== $entitlement && ! apply_filters( 'review_captain_has_entitlement', false, $entitlement ) ) {
			Menu::back( self::PAGE, '', '', array( 'tab' => $group ) ); // Disabled fields: never overwrite stored values.
		}
		$changed = Settings::save_group( $group, $result['values'] );
		if ( 'advanced' === $group && in_array( 'job_runner', $changed, true ) ) {
			\ReviewCaptain\Jobs\Runner::unschedule();
			\ReviewCaptain\Jobs\Runner::schedule();
		}
		$audited = array_values( array_intersect( $changed, self::AUDITED ) );
		if ( $audited ) {
			AuditLog::log( 'settings_changed', 'settings', $group, array( 'changed_keys' => $audited, 'group' => $group ) );
		}
		Menu::back( self::PAGE, $result['errors'] ? '' : 'saved', $result['errors'] ? implode( ' ', $result['errors'] ) : '', array( 'tab' => $group ) );
	}

	public static function handle_reset(): void {
		Capabilities::check( 'rc_manage_settings', 'rc_reset_settings' );
		$group = isset( $_POST['group'] ) ? sanitize_key( wp_unslash( $_POST['group'] ) ) : '';
		if ( in_array( $group, Schema::GROUPS, true ) ) {
			Settings::reset_group( $group );
			AuditLog::log( 'settings_reset', 'settings', $group, array( 'group' => $group ) );
		}
		Menu::back( self::PAGE, 'reset', '', array( 'tab' => $group ) );
	}

	public static function handle_roles(): void {
		Capabilities::check( 'manage_options', 'rc_save_roles' );
		$map = array();
		foreach ( (array) ( $_POST['roles'] ?? array() ) as $role => $caps ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$map[ sanitize_key( $role ) ] = array_map( 'sanitize_key', (array) $caps );
		}
		Capabilities::apply_map( $map );
		AuditLog::log( 'roles_changed', 'settings', 'access' );
		Menu::back( self::PAGE, 'saved', '', array( 'tab' => 'access' ) );
	}

	public static function handle_erase_all(): void {
		Capabilities::check( 'rc_manage_privacy', 'rc_erase_all' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'review-captain' ), '', array( 'response' => 403 ) );
		}
		$domain  = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$confirm = isset( $_POST['confirm'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['confirm'] ) ) ) : '';
		if ( ! hash_equals( $domain, $confirm ) ) {
			Menu::back( self::PAGE, '', __( 'Confirmation did not match. Nothing was deleted.', 'review-captain' ), array( 'tab' => 'privacy' ) );
		}
		\ReviewCaptain\Install\Uninstaller::erase_site_data();
		\ReviewCaptain\Install\Activator::install_site(); // Fresh empty tables so the plugin keeps working.
		Menu::back( self::PAGE, 'erased', '', array( 'tab' => 'privacy' ) );
	}

	/**
	 * Configuration export without secrets or personal data (SET-018).
	 *
	 * @return array<string,mixed>
	 */
	public static function config_export(): array {
		$out = array(
			'plugin'         => 'review-captain',
			'schema_version' => Settings::VERSION,
			'exported_at'    => gmdate( 'c' ),
			'settings'       => array(),
		);
		foreach ( Schema::GROUPS as $group ) {
			foreach ( Schema::fields_for_group( $group ) as $key => $field ) {
				if ( ! empty( $field['internal'] ) || 1 === ( $field['privacy'] ?? 0 ) || 'S' === ( $field['privacy'] ?? 0 ) ) {
					continue; // Exclude operator contact data and secrets.
				}
				$out['settings'][ $group ][ $key ] = Settings::get( $key );
			}
		}
		return $out;
	}

	public static function handle_export_config(): void {
		Capabilities::check( 'rc_manage_settings', 'rc_export_config' );
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=review-captain-config-' . gmdate( 'Y-m-d' ) . '.json' );
		echo wp_json_encode( self::config_export(), JSON_PRETTY_PRINT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * @param array<string,mixed> $data Decoded config.
	 * @return array{applied:int, errors:string[]}
	 */
	public static function config_import( array $data ): array {
		$errors = array();
		if ( 'review-captain' !== ( $data['plugin'] ?? '' ) || (int) ( $data['schema_version'] ?? 0 ) > Settings::VERSION || ! is_array( $data['settings'] ?? null ) ) {
			return array(
				'applied' => 0,
				'errors'  => array( __( 'This file is not a compatible Review Captain configuration.', 'review-captain' ) ),
			);
		}
		$applied = 0;
		foreach ( $data['settings'] as $group => $values ) {
			if ( ! in_array( $group, Schema::GROUPS, true ) || ! is_array( $values ) ) {
				continue;
			}
			// Keep current values for keys not in the file (e.g. excluded contact data).
			$merged = array_merge( Settings::group( $group ), $values );
			$result = Settings::sanitize_group( $group, $merged );
			$errors = array_merge( $errors, array_values( $result['errors'] ) );
			Settings::save_group( $group, $result['values'] );
			++$applied;
		}
		AuditLog::log( 'settings_imported', 'settings', null, array( 'count' => $applied ) );
		return array(
			'applied' => $applied,
			'errors'  => $errors,
		);
	}

	public static function handle_import_config(): void {
		Capabilities::check( 'rc_manage_settings', 'rc_import_config' );
		$tmp = isset( $_FILES['config']['tmp_name'] ) ? (string) $_FILES['config']['tmp_name'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( '' === $tmp || ! is_uploaded_file( $tmp ) || filesize( $tmp ) > 256 * KB_IN_BYTES ) {
			Menu::back( self::PAGE, '', __( 'Please choose a configuration file.', 'review-captain' ), array( 'tab' => 'advanced' ) );
		}
		$data   = json_decode( (string) file_get_contents( $tmp ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$result = self::config_import( is_array( $data ) ? $data : array() );
		Menu::back( self::PAGE, $result['errors'] ? '' : 'saved', implode( ' ', $result['errors'] ), array( 'tab' => 'advanced' ) );
	}
}
