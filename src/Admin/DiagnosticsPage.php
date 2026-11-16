<?php
/**
 * Read-only diagnostics (SET-017, TEC-002, SEO-012) and a downloadable report without secrets or personal data.
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Admin;

use ReviewCaptain\Audit\AuditLog;
use ReviewCaptain\Connectors\Registry;
use ReviewCaptain\Database\Schema;
use ReviewCaptain\Jobs\Queue;
use ReviewCaptain\Jobs\Runner;
use ReviewCaptain\Security\Capabilities;
use ReviewCaptain\Security\Crypto;
use ReviewCaptain\Seo\SchemaOutput;

defined( 'ABSPATH' ) || exit;

final class DiagnosticsPage {

	public static function register_actions(): void {
		add_action( 'admin_post_review_captain_diagnostics_report', array( self::class, 'handle_report' ) );
		add_action( 'admin_post_review_captain_run_queue', array( self::class, 'handle_run_queue' ) );
		add_action( 'admin_post_review_captain_retry_job', array( self::class, 'handle_retry_job' ) );
	}

	/**
	 * Collect diagnostic data. Allowlist only: no secrets, no personal data.
	 *
	 * @return array<string,mixed>
	 */
	public static function data( bool $include_milestones = false ): array {
		global $wpdb;
		$jobs = Queue::stats();
		$seo  = SchemaOutput::environment();
		$data = array(
			'environment' => array(
				'review_captain' => REVIEW_CAPTAIN_VERSION,
				'db_version'     => Schema::installed_version(),
				'wordpress'      => get_bloginfo( 'version' ),
				'php'            => PHP_VERSION,
				'mysql'          => $wpdb->db_version(),
				'multisite'      => is_multisite(),
				'woocommerce'    => defined( 'WC_VERSION' ) ? WC_VERSION : null,
				'locale'         => get_locale(),
			),
			'jobs'        => array(
				'runner'          => Runner::mode(),
				'wp_cron_off'     => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
				'last_worker_run' => (int) get_option( Runner::LAST_RUN, 0 ) ? gmdate( 'c', (int) get_option( Runner::LAST_RUN ) ) : null,
				'counts'          => $jobs['counts'],
				'due'             => $jobs['due'],
				'overdue_minutes' => $jobs['overdue_minutes'],
				'delayed'         => $jobs['delayed'],
			),
			'security'    => array(
				'sodium'             => Crypto::is_available(),
				'explicit_crypt_key' => Crypto::has_explicit_key(),
			),
			'seo'         => array(
				'yoast'           => $seo['yoast'],
				'yoast_supported' => SchemaOutput::yoast_supported(),
				'competing'       => $seo['competing'],
				'schema_mode'     => null !== $seo['yoast'] ? 'yoast_graph' : ( $seo['competing'] ? 'disabled_competing_plugin' : 'own_json_ld' ),
			),
			'connectors'  => array_map(
				static function ( $e ) {
					return array(
						'version'      => $e['metadata']['version'],
						'contract'     => $e['metadata']['contract'],
						'status'       => $e['status'],
						'code'         => $e['code'],
						'capabilities' => $e['capabilities'],
					);
				},
				Registry::instance()->all()
			),
			'events'      => (array) get_option( 'review_captain_event_stats', array() ),
			'spam_blocked'=> (int) get_option( 'review_captain_spam_blocked', 0 ),
		);
		if ( $include_milestones ) {
			$data['milestones'] = (array) get_option( 'review_captain_milestones', array() );
		}
		return (array) apply_filters( 'review_captain_diagnostics', $data );
	}

	public static function render(): void {
		$d = self::data( true );
		echo '<div class="wrap"><h1>' . esc_html__( 'Diagnostics', 'review-captain' ) . '</h1>';
		Menu::notices();
		if ( $d['jobs']['delayed'] ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Background tasks are running late. WordPress runs scheduled tasks only when the site is visited. For reliable timing set up a server cron job:', 'review-captain' ) . '<br><code>* * * * * cd ' . esc_html( ABSPATH ) . ' &amp;&amp; wp review-captain queue run --time=50 --quiet</code></p></div>';
		}
		if ( ! $d['security']['explicit_crypt_key'] ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'Recommended: define REVIEW_CAPTAIN_ENCRYPTION_KEY in wp-config.php. Without it, stored access credentials become unreadable if the WordPress security keys change.', 'review-captain' ) . '</p></div>';
		}
		if ( 'disabled_competing_plugin' === $d['seo']['schema_mode'] ) {
			/* translators: %s: plugin names */
			echo '<div class="notice notice-info inline"><p>' . esc_html( sprintf( __( 'Structured data: %s is active, so Review Captain does not output its own review markup to avoid duplicates.', 'review-captain' ), implode( ', ', $d['seo']['competing'] ) ) ) . '</p></div>';
		}
		foreach ( array( 'environment', 'jobs', 'security', 'seo' ) as $section ) {
			echo '<h2>' . esc_html( ucfirst( $section ) ) . '</h2><table class="widefat striped rc-diag"><tbody>';
			foreach ( $d[ $section ] as $k => $v ) {
				echo '<tr><th scope="row">' . esc_html( $k ) . '</th><td>' . esc_html( is_scalar( $v ) || null === $v ? var_export( $v, true ) : wp_json_encode( $v ) ) . '</td></tr>'; // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
			}
			echo '</tbody></table>';
		}

		// Failed/dead jobs with retry.
		global $wpdb;
		$table = Schema::table( 'rc_jobs' );
		$dead  = (array) $wpdb->get_results( "SELECT id, type, attempts, last_error_code, last_error_message, updated_at FROM {$table} WHERE status IN ('dead','paused') ORDER BY id DESC LIMIT 20", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		if ( $dead ) {
			echo '<h2>' . esc_html__( 'Failed tasks', 'review-captain' ) . '</h2><table class="widefat striped"><thead><tr><th>ID</th><th>' . esc_html__( 'Type', 'review-captain' ) . '</th><th>' . esc_html__( 'Error', 'review-captain' ) . '</th><th></th></tr></thead><tbody>';
			foreach ( $dead as $j ) {
				echo '<tr><td>' . (int) $j['id'] . '</td><td>' . esc_html( $j['type'] ) . '</td><td><code>' . esc_html( (string) $j['last_error_code'] ) . '</code> ' . esc_html( (string) $j['last_error_message'] ) . '</td><td>';
				if ( current_user_can( 'rc_manage_settings' ) ) {
					echo '<form method="post" action="' . esc_url( Menu::post_url() ) . '"><input type="hidden" name="action" value="review_captain_retry_job"><input type="hidden" name="id" value="' . (int) $j['id'] . '">';
					wp_nonce_field( 'rc_retry_job_' . (int) $j['id'] );
					submit_button( __( 'Retry', 'review-captain' ), 'small', 'submit', false );
					echo '</form>';
				}
				echo '</td></tr>';
			}
			echo '</tbody></table>';
		}

		echo '<h2>' . esc_html__( 'Recent audit log', 'review-captain' ) . '</h2><table class="widefat striped"><tbody>';
		foreach ( AuditLog::latest( 20 ) as $a ) {
			$user = $a['user_id'] ? get_userdata( (int) $a['user_id'] ) : null;
			echo '<tr><td>' . esc_html( $a['created_at'] ) . ' UTC</td><td>' . esc_html( $a['event'] ) . '</td><td>' . esc_html( trim( $a['object_type'] . ' ' . $a['object_id'] ) ) . '</td><td>' . esc_html( $user ? $user->user_login : '' ) . '</td><td><code>' . esc_html( (string) $a['context'] ) . '</code></td></tr>';
		}
		echo '</tbody></table>';

		echo '<p>';
		echo '<form method="post" action="' . esc_url( Menu::post_url() ) . '" class="rc-inline"><input type="hidden" name="action" value="review_captain_diagnostics_report">';
		wp_nonce_field( 'rc_diagnostics_report' );
		echo '<label><input type="checkbox" name="milestones" value="1"> ' . esc_html__( 'Include setup timestamps', 'review-captain' ) . '</label> ';
		submit_button( __( 'Download diagnostic report', 'review-captain' ), 'secondary', 'submit', false );
		echo '</form> ';
		if ( current_user_can( 'rc_manage_settings' ) ) {
			echo '<form method="post" action="' . esc_url( Menu::post_url() ) . '" class="rc-inline"><input type="hidden" name="action" value="review_captain_run_queue">';
			wp_nonce_field( 'rc_run_queue' );
			submit_button( __( 'Run background tasks now', 'review-captain' ), 'secondary', 'submit', false );
			echo '</form>';
		}
		echo '</p></div>';
	}

	public static function handle_report(): void {
		Capabilities::check( 'rc_view_reports', 'rc_diagnostics_report' );
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=review-captain-diagnostics-' . gmdate( 'Y-m-d' ) . '.json' );
		echo wp_json_encode( self::data( ! empty( $_POST['milestones'] ) ), JSON_PRETTY_PRINT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	public static function handle_run_queue(): void {
		Capabilities::check( 'rc_manage_settings', 'rc_run_queue' );
		Runner::run( 20 );
		Menu::back( 'review-captain-diagnostics', 'queued' );
	}

	public static function handle_retry_job(): void {
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked below.
		Capabilities::check( 'rc_manage_settings', 'rc_retry_job_' . $id );
		Queue::retry( $id );
		Menu::back( 'review-captain-diagnostics', 'queued' );
	}
}
