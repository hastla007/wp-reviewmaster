<?php
/**
 * Diagnostics page – read-only (SET-017, SEO-012, TEC-002).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Admin;

use ReviewCaptain\Audit\AuditLog;
use ReviewCaptain\Domain\ReviewStatus;
use ReviewCaptain\Install\Schema;
use ReviewCaptain\Plugin;
use ReviewCaptain\Security\Capabilities;
use ReviewCaptain\Settings\Settings;
use ReviewCaptain\Settings\SettingsSchema;
use ReviewCaptain\Support\Requirements;

/**
 * Renders own sections plus everything registered via `review_captain_diagnostics_sections`
 * (key => ['title' => string, 'render' => callable, 'export' => ?callable]). The export is a
 * JSON download without secrets or personal data.
 */
final class DiagnosticsPage {

	public const SLUG = 'review-captain-diagnostics';

	/**
	 * Keys removed from every exported structure.
	 */
	private const REDACT_PATTERN = '/(^|_)(secret|secrets|token|tokens|password|passwd|key|apikey|cookie|email|mail|phone|ip|author|body|user_id|user_login|credentials?)(_|$)/i';

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( private Plugin $plugin ) {}

	/**
	 * Remove secret/PII-looking keys recursively and cap string length.
	 *
	 * @param mixed $data  Data.
	 * @param int   $depth Depth guard.
	 */
	public static function redact( mixed $data, int $depth = 0 ): mixed {
		if ( $depth > 6 ) {
			return null;
		}
		if ( is_array( $data ) ) {
			$out = array();
			foreach ( $data as $key => $value ) {
				if ( is_string( $key ) && preg_match( self::REDACT_PATTERN, $key ) ) {
					continue;
				}
				$out[ $key ] = self::redact( $value, $depth + 1 );
			}
			return $out;
		}
		if ( is_object( $data ) ) {
			return self::redact( get_object_vars( $data ), $depth + 1 );
		}
		if ( is_string( $data ) ) {
			// Mask anything that looks like an e-mail address.
			$data = (string) preg_replace( '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[redacted]', $data );
			return mb_substr( $data, 0, 500 );
		}
		return is_scalar( $data ) || null === $data ? $data : null;
	}

	/**
	 * System info.
	 *
	 * @return array<string, mixed>
	 */
	public function system_info(): array {
		$db_version = get_option( Schema::OPTION_VERSION, '' );
		$db_server  = Requirements::current_db();
		return array(
			'plugin_version'        => defined( 'REVIEW_CAPTAIN_VERSION' ) ? REVIEW_CAPTAIN_VERSION : '',
			'php_version'           => PHP_VERSION,
			'php_minimum'           => Requirements::MIN_PHP,
			'wp_version'            => (string) get_bloginfo( 'version' ),
			'db_server'             => trim( $db_server['vendor'] . ' ' . $db_server['version'] ),
			'db_server_minimum'     => $db_server['minimum'],
			'db_server_ok'          => $db_server['ok'],
			'db_schema_installed'   => '' === $db_version ? null : (int) $db_version,
			'db_schema_expected'    => Schema::VERSION,
			'db_schema_ok'          => (int) $db_version === Schema::VERSION,
			'settings_version'      => (int) get_option( Settings::OPTION_VERSION, 0 ),
			'settings_expected'     => SettingsSchema::VERSION,
			'multisite'             => is_multisite(),
			'wp_cron_disabled'      => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
			'action_scheduler'      => function_exists( 'as_schedule_single_action' ),
			'locale'                => function_exists( 'determine_locale' ) ? determine_locale() : get_locale(),
			'timezone'              => function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : '',
		);
	}

	/**
	 * Counts (no content).
	 *
	 * @return array<string, int>
	 */
	public function counts(): array {
		$repo = $this->plugin->reviews();
		$out  = array();
		foreach ( ReviewStatus::values() as $status ) {
			$out[ 'reviews_' . $status ] = $repo->count( array( 'status' => $status ) );
		}
		$out['profiles'] = ( new Profiles() )->count();
		return $out;
	}

	/**
	 * Jobs info from the optional jobs service.
	 *
	 * @return array{stats: array<string, mixed>, recent: list<array<string, mixed>>}|null
	 */
	public function jobs(): ?array {
		if ( ! $this->plugin->has( 'jobs' ) ) {
			return null;
		}
		try {
			$jobs = $this->plugin->get( 'jobs' );
		} catch ( \Throwable $e ) {
			return null;
		}
		$stats  = method_exists( $jobs, 'stats' ) ? $jobs->stats() : array();
		$recent = method_exists( $jobs, 'recent' ) ? $jobs->recent() : array();
		$rows   = array();
		foreach ( is_array( $recent ) ? array_slice( $recent, 0, 20 ) : array() as $job ) {
			$job    = is_object( $job ) ? get_object_vars( $job ) : (array) $job;
			$rows[] = array(
				'id'              => $job['id'] ?? '',
				'type'            => $job['type'] ?? '',
				'status'          => $job['status'] ?? '',
				'attempts'        => isset( $job['attempts'] ) ? (string) $job['attempts'] . ( isset( $job['max_attempts'] ) ? '/' . $job['max_attempts'] : '' ) : '',
				'last_error_code' => $job['last_error_code'] ?? '',
				'updated_at'      => $job['updated_at'] ?? ( $job['created_at'] ?? '' ),
			);
		}
		return array(
			'stats'  => is_array( $stats ) ? $stats : array(),
			'recent' => $rows,
		);
	}

	/**
	 * SEO status from the optional SEO module.
	 *
	 * @return list<array{label: string, status: string, message: string}>|null
	 */
	public function seo(): ?array {
		$class = '\ReviewCaptain\Seo\SeoStatus';
		if ( ! class_exists( $class ) || ! method_exists( $class, 'report' ) ) {
			return null;
		}
		try {
			$report = $class::report();
		} catch ( \Throwable $e ) {
			return null;
		}
		$out = array();
		foreach ( is_array( $report ) ? $report : array() as $row ) {
			if ( is_array( $row ) ) {
				$out[] = array(
					'label'   => (string) ( $row['label'] ?? '' ),
					'status'  => (string) ( $row['status'] ?? '' ),
					'message' => (string) ( $row['message'] ?? '' ),
				);
			}
		}
		return $out;
	}

	/**
	 * Recent audit entries without personal data (no user IDs, only allowlisted context).
	 *
	 * @param int  $limit         Limit.
	 * @param bool $settings_only Only settings and role changes (SET-RC-17).
	 * @return list<array<string, mixed>>
	 */
	public function audit( int $limit = 20, bool $settings_only = false ): array {
		$out = array();
		foreach ( $this->plugin->audit()->recent( $limit, 0, $settings_only ? AuditLog::SETTINGS_OBJECT_TYPES : null ) as $row ) {
			$context = json_decode( (string) ( $row['context'] ?? '' ), true );
			$out[]   = array(
				'event'       => (string) $row['event'],
				'object_type' => (string) $row['object_type'],
				'object_id'   => (string) $row['object_id'],
				'fields'      => is_array( $context ) ? array_keys( $context ) : array(),
				'created_at'  => (string) $row['created_at'],
			);
		}
		return $out;
	}

	/**
	 * Readable changes of a settings audit entry ("Feld: alt → neu"), already masked when written.
	 *
	 * @param int $limit Limit.
	 * @return list<array{event: string, object_id: string, created_at: string, changes: list<string>}>
	 */
	public function settings_audit( int $limit = 20 ): array {
		$out = array();
		foreach ( $this->plugin->audit()->recent( $limit, 0, AuditLog::SETTINGS_OBJECT_TYPES ) as $row ) {
			$context = json_decode( (string) ( $row['context'] ?? '' ), true );
			$context = is_array( $context ) ? $context : array();
			$changes = array();
			foreach ( (array) ( $context['changes'] ?? array() ) as $change ) {
				if ( ! is_array( $change ) || ! isset( $change['field'] ) ) {
					continue;
				}
				[ $tab, $key ] = array_pad( explode( '.', (string) $change['field'], 2 ), 2, '' );
				$changes[]     = SettingsFields::label( $tab, $key ) . ': ' . ConfigTransfer::display_value( $change['from'] ?? null ) . ' → ' . ConfigTransfer::display_value( $change['to'] ?? null );
			}
			if ( ! $changes && isset( $context['capability'] ) ) {
				$changes[] = (string) $context['capability'] . ': ' . ConfigTransfer::display_value( $context['from'] ?? null ) . ' → ' . ConfigTransfer::display_value( $context['roles'] ?? null );
			}
			$out[] = array(
				'event'      => (string) $row['event'],
				'object_id'  => (string) $row['object_id'],
				'created_at' => (string) $row['created_at'],
				'changes'    => $changes,
			);
		}
		return $out;
	}

	/**
	 * Third-party sections.
	 *
	 * @return array<string, array{title: string, render: callable, export: callable|null}>
	 */
	public function extra_sections(): array {
		$sections = apply_filters( 'review_captain_diagnostics_sections', array() );
		$out      = array();
		foreach ( is_array( $sections ) ? $sections : array() as $key => $section ) {
			if ( ! is_array( $section ) || ! isset( $section['title'], $section['render'] ) || ! is_callable( $section['render'] ) ) {
				continue;
			}
			$out[ sanitize_key( (string) $key ) ] = array(
				'title'  => (string) $section['title'],
				'render' => $section['render'],
				'export' => isset( $section['export'] ) && is_callable( $section['export'] ) ? $section['export'] : null,
			);
		}
		return $out;
	}

	/**
	 * Build the downloadable report (no secrets, no PII, no review content).
	 *
	 * @return array<string, mixed>
	 */
	public function build_report(): array {
		$report = array(
			'schema'       => 'review_captain.diagnostics.v1',
			'generated_at' => gmdate( 'c' ),
			'system'       => $this->system_info(),
			'counts'       => $this->counts(),
			'settings'     => ( new ConfigTransfer( $this->plugin->settings() ) )->export()['settings'],
			'jobs'         => $this->jobs(),
			'seo'          => $this->seo(),
			'audit'        => $this->audit( 50 ),
			'sections'     => array(),
		);
		foreach ( $this->extra_sections() as $key => $section ) {
			if ( null !== $section['export'] ) {
				try {
					$report['sections'][ $key ] = call_user_func( $section['export'] );
				} catch ( \Throwable $e ) {
					$report['sections'][ $key ] = array( 'error' => 'export_failed' );
				}
			}
		}
		// Free-text settings may contain personal data – keep only whether they are set.
		foreach ( array( 'general' => array( 'brand_name' ), 'display' => array( 'empty_text' ), 'privacy' => array( 'privacy_notice' ), 'requests' => array( 'sender_name', 'sender_email' ) ) as $tab => $keys ) {
			foreach ( $keys as $key ) {
				if ( isset( $report['settings'][ $tab ][ $key ] ) ) {
					$report['settings'][ $tab ][ $key ] = '' !== (string) $report['settings'][ $tab ][ $key ] ? '[set]' : '';
				}
			}
		}
		return (array) self::redact( $report );
	}

	/**
	 * Handler: JSON download.
	 */
	public function handle_export(): void {
		Capabilities::verify_request( Capabilities::VIEW_REPORTS, 'diagnostics_export' );
		$report = $this->build_report();
		$this->plugin->audit()->record( 'diagnostics.exported', 'diagnostics', '' );
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="review-captain-diagnose-' . gmdate( 'Y-m-d' ) . '.json"' );
		header( 'X-Content-Type-Options: nosniff' );
		echo wp_json_encode( $report, JSON_PRETTY_PRINT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download.
		exit;
	}

	/**
	 * Page callback.
	 */
	public function render(): void {
		if ( ! current_user_can( Capabilities::VIEW_REPORTS ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'review-captain' ), '', array( 'response' => 403 ) );
		}
		echo '<div class="wrap rc-admin">';
		echo View::header( __( 'Diagnostics', 'review-captain' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<p>' . esc_html__( 'Read-only. Nothing is changed here. The diagnostics report contains no credentials, email addresses, or review texts.', 'review-captain' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo View::form_fields( 'diagnostics_export' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<p><button type="submit" class="button">' . esc_html__( 'Download diagnostics report (JSON)', 'review-captain' ) . '</button></p></form>';

		$extra = $this->extra_sections();
		$this->render_system();
		if ( ! isset( $extra['jobs'] ) ) {
			$this->render_jobs();
		}
		$this->render_seo();
		foreach ( $extra as $key => $section ) {
			echo '<div class="postbox rc-admin-diag-section" id="' . esc_attr( 'rc-diag-' . $key ) . '"><div class="postbox-header"><h2>' . esc_html( $section['title'] ) . '</h2></div><div class="inside">';
			try {
				call_user_func( $section['render'] );
			} catch ( \Throwable $e ) {
				echo '<p>' . esc_html__( 'This section could not be loaded.', 'review-captain' ) . '</p>';
			}
			echo '</div></div>';
		}
		$this->render_audit();
		echo '</div>';
	}

	/**
	 * System section.
	 */
	private function render_system(): void {
		$info = $this->system_info();
		$yes  = __( 'yes', 'review-captain' );
		$no   = __( 'no', 'review-captain' );
		$rows = array(
			__( 'Review Captain', 'review-captain' )           => (string) $info['plugin_version'],
			__( 'PHP', 'review-captain' )                      => (string) $info['php_version'],
			__( 'WordPress', 'review-captain' )                => (string) $info['wp_version'],
			__( 'Database schema', 'review-captain' )         => ( null === $info['db_schema_installed'] ? __( 'not installed', 'review-captain' ) : (string) $info['db_schema_installed'] ) . ' / ' . __( 'expected', 'review-captain' ) . ' ' . $info['db_schema_expected'],
			__( 'Settings version', 'review-captain' )    => $info['settings_version'] . ' / ' . __( 'expected', 'review-captain' ) . ' ' . $info['settings_expected'],
			__( 'Multisite', 'review-captain' )                => $info['multisite'] ? $yes : $no,
			__( 'WP-Cron disabled', 'review-captain' )      => $info['wp_cron_disabled'] ? $yes : $no,
			__( 'Action Scheduler available', 'review-captain' ) => $info['action_scheduler'] ? $yes : $no,
			__( 'Language / time zone', 'review-captain' )       => $info['locale'] . ' / ' . $info['timezone'],
		);
		echo '<div class="postbox"><div class="postbox-header"><h2>' . esc_html__( 'Environment', 'review-captain' ) . '</h2></div><div class="inside">';
		if ( ! $info['db_schema_ok'] ) {
			echo View::notice( 'warning', __( 'The database schema does not match the expected version. It will be updated automatically the next time an admin page loads; if the message persists, deactivate and reactivate the plugin.', 'review-captain' ), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '<table class="widefat striped"><tbody>';
		foreach ( $rows as $label => $value ) {
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
		}
		echo '</tbody></table>';
		$counts = $this->counts();
		echo '<p class="description">';
		$parts = array();
		foreach ( ReviewStatus::cases() as $status ) {
			$parts[] = $status->label() . ': ' . number_format_i18n( $counts[ 'reviews_' . $status->value ] );
		}
		$parts[] = __( 'Profiles', 'review-captain' ) . ': ' . number_format_i18n( $counts['profiles'] );
		echo esc_html( implode( ' · ', $parts ) ) . '</p></div></div>';
	}

	/**
	 * Jobs section (optional).
	 */
	private function render_jobs(): void {
		$jobs = $this->jobs();
		echo '<div class="postbox"><div class="postbox-header"><h2>' . esc_html__( 'Background tasks', 'review-captain' ) . '</h2></div><div class="inside">';
		if ( null === $jobs ) {
			echo '<p>' . esc_html__( 'The task manager is not loaded.', 'review-captain' ) . '</p></div></div>';
			return;
		}
		if ( $jobs['stats'] ) {
			$parts = array();
			foreach ( $jobs['stats'] as $key => $value ) {
				if ( is_scalar( $value ) ) {
					$parts[] = $key . ': ' . $value;
				}
			}
			echo '<p>' . esc_html( implode( ' · ', $parts ) ) . '</p>';
		}
		if ( ! $jobs['recent'] ) {
			echo '<p>' . esc_html__( 'No background tasks have run yet.', 'review-captain' ) . '</p></div></div>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array( __( 'ID', 'review-captain' ), __( 'Type', 'review-captain' ), __( 'Status', 'review-captain' ), __( 'Attempts', 'review-captain' ), __( 'Error code', 'review-captain' ), __( 'Time', 'review-captain' ) ) as $head ) {
			echo '<th scope="col">' . esc_html( $head ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $jobs['recent'] as $job ) {
			echo '<tr><td>' . esc_html( (string) $job['id'] ) . '</td><td>' . esc_html( (string) $job['type'] ) . '</td><td>' . esc_html( (string) $job['status'] ) . '</td><td>' . esc_html( (string) $job['attempts'] ) . '</td><td><code>' . esc_html( (string) $job['last_error_code'] ) . '</code></td><td>' . esc_html( View::date( (string) $job['updated_at'], true ) ) . '</td></tr>';
		}
		echo '</tbody></table></div></div>';
	}

	/**
	 * SEO section (optional).
	 */
	private function render_seo(): void {
		$seo = $this->seo();
		echo '<div class="postbox"><div class="postbox-header"><h2>' . esc_html__( 'SEO & structured data', 'review-captain' ) . '</h2></div><div class="inside">';
		if ( null === $seo ) {
			echo '<p>' . esc_html__( 'SEO status is not available.', 'review-captain' ) . '</p></div></div>';
			return;
		}
		echo '<table class="widefat striped"><tbody>';
		foreach ( $seo as $row ) {
			$mod = match ( $row['status'] ) {
				'ok', 'good', 'pass' => 'ok',
				'warning', 'warn', 'recommended' => 'warning',
				'error', 'fail', 'critical' => 'error',
				default => 'neutral',
			};
			echo '<tr><th scope="row">' . esc_html( $row['label'] ) . '</th><td>' . View::pill( $row['status'], $mod ) . '</td><td>' . esc_html( $row['message'] ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</tbody></table></div></div>';
	}

	/**
	 * Settings and role changes with old and new value (SET-RC-17). Rendered inside the audit box.
	 */
	private function render_settings_audit(): void {
		$rows = $this->settings_audit( 50 );
		if ( ! $rows ) {
			echo '<p>' . esc_html__( 'No changes to settings or roles logged yet.', 'review-captain' ) . '</p></div></div>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Time', 'review-captain' ) . '</th><th scope="col">' . esc_html_x( 'Event', 'log entry', 'review-captain' ) . '</th><th scope="col">' . esc_html__( 'Section', 'review-captain' ) . '</th><th scope="col">' . esc_html__( 'Changes (old → new)', 'review-captain' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			echo '<tr><td>' . esc_html( View::date( $row['created_at'], true ) ) . '</td><td><code>' . esc_html( $row['event'] ) . '</code></td><td>' . esc_html( $row['object_id'] ) . '</td><td>';
			echo $row['changes'] ? '<ul class="rc-admin-changes"><li>' . implode( '</li><li>', array_map( 'esc_html', $row['changes'] ) ) . '</li></ul>' : '–'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped per item.
			echo '</td></tr>';
		}
		echo '</tbody></table></div></div>';
	}

	/**
	 * Audit section.
	 */
	private function render_audit(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display filter only.
		$settings_only = 'settings' === View::req( $_GET, 'audit' );
		echo '<div class="postbox" id="rc-audit"><div class="postbox-header"><h2>' . esc_html__( 'Recent log entries', 'review-captain' ) . '</h2></div><div class="inside">';
		echo '<p class="rc-admin-filter">' . esc_html__( 'Show:', 'review-captain' ) . ' ';
		echo $settings_only ? '<a href="' . esc_url( View::page_url( self::SLUG ) . '#rc-audit' ) . '">' . esc_html__( 'All', 'review-captain' ) . '</a>' : '<strong aria-current="true">' . esc_html__( 'All', 'review-captain' ) . '</strong>';
		echo ' | ';
		echo $settings_only ? '<strong aria-current="true">' . esc_html__( 'Settings', 'review-captain' ) . '</strong>' : '<a href="' . esc_url( View::page_url( self::SLUG, array( 'audit' => 'settings' ) ) . '#rc-audit' ) . '">' . esc_html__( 'Settings', 'review-captain' ) . '</a>';
		echo '</p>';
		if ( $settings_only ) {
			$this->render_settings_audit();
			return;
		}
		$rows = $this->audit( 20 );
		if ( ! $rows ) {
			echo '<p>' . esc_html__( 'No entries yet.', 'review-captain' ) . '</p></div></div>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Time', 'review-captain' ) . '</th><th scope="col">' . esc_html_x( 'Event', 'log entry', 'review-captain' ) . '</th><th scope="col">' . esc_html__( 'Object', 'review-captain' ) . '</th><th scope="col">' . esc_html__( 'Details', 'review-captain' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			echo '<tr><td>' . esc_html( View::date( $row['created_at'], true ) ) . '</td><td><code>' . esc_html( $row['event'] ) . '</code></td><td>' . esc_html( trim( $row['object_type'] . ' ' . $row['object_id'] ) ) . '</td><td>' . esc_html( implode( ', ', $row['fields'] ) ) . '</td></tr>';
		}
		echo '</tbody></table></div></div>';
	}
}
