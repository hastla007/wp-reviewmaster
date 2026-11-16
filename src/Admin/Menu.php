<?php
/**
 * Admin menu (UX-003). Each page checks exactly one capability.
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Admin;

use ReviewCaptain\Install\Activator;
use ReviewCaptain\Reviews\ReviewRepository;

defined( 'ABSPATH' ) || exit;

final class Menu {

	public const SLUG = 'review-captain';

	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_init', array( self::class, 'setup_redirect' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
		ReviewsPage::register_actions();
		ImportExportPage::register_actions();
		WidgetsPage::register_actions();
		TestimonialsPage::register_actions();
		SourcesPage::register_actions();
		SettingsPage::register_actions();
		SetupWizard::register_actions();
		DiagnosticsPage::register_actions();
	}

	public static function menu(): void {
		$pending = ReviewRepository::count( array( 'status' => 'pending' ) );
		$bubble  = $pending ? ' <span class="awaiting-mod count-' . (int) $pending . '"><span class="pending-count">' . number_format_i18n( $pending ) . '</span></span>' : '';

		add_menu_page( 'Review Captain', 'Review Captain' . $bubble, 'rc_view_reviews', self::SLUG, array( DashboardPage::class, 'render' ), 'dashicons-star-filled', 26 );
		add_submenu_page( self::SLUG, __( 'Dashboard', 'review-captain' ), __( 'Dashboard', 'review-captain' ), 'rc_view_reviews', self::SLUG, array( DashboardPage::class, 'render' ) );
		$reviews_hook = add_submenu_page( self::SLUG, __( 'Reviews', 'review-captain' ), __( 'Reviews', 'review-captain' ) . $bubble, 'rc_view_reviews', 'review-captain-reviews', array( ReviewsPage::class, 'render' ) );
		if ( $reviews_hook ) {
			add_action( 'load-' . $reviews_hook, array( ReviewsPage::class, 'handle_bulk' ) );
		}
		add_submenu_page( self::SLUG, __( 'Import & Export', 'review-captain' ), __( 'Import & Export', 'review-captain' ), 'rc_manage_reviews', 'review-captain-import', array( ImportExportPage::class, 'render' ) );
		add_submenu_page( self::SLUG, __( 'Widgets', 'review-captain' ), __( 'Widgets', 'review-captain' ), 'rc_manage_widgets', 'review-captain-widgets', array( WidgetsPage::class, 'render' ) );
		add_submenu_page( self::SLUG, __( 'Testimonials', 'review-captain' ), __( 'Testimonials', 'review-captain' ), 'rc_manage_testimonials', 'review-captain-testimonials', array( TestimonialsPage::class, 'render' ) );
		add_submenu_page( self::SLUG, __( 'Sources', 'review-captain' ), __( 'Sources', 'review-captain' ), 'rc_manage_reviews', 'review-captain-sources', array( SourcesPage::class, 'render' ) );
		add_submenu_page( self::SLUG, __( 'Integrations', 'review-captain' ), __( 'Integrations', 'review-captain' ), 'rc_manage_settings', 'review-captain-integrations', array( IntegrationsPage::class, 'render' ) );
		add_submenu_page( self::SLUG, __( 'Settings', 'review-captain' ), __( 'Settings', 'review-captain' ), 'rc_manage_settings', 'review-captain-settings', array( SettingsPage::class, 'render' ) );
		add_submenu_page( self::SLUG, __( 'Diagnostics', 'review-captain' ), __( 'Diagnostics', 'review-captain' ), 'rc_view_reports', 'review-captain-diagnostics', array( DiagnosticsPage::class, 'render' ) );
		// Setup wizard without menu entry.
		add_submenu_page( '', __( 'Setup', 'review-captain' ), __( 'Setup', 'review-captain' ), 'rc_manage_settings', 'review-captain-setup', array( SetupWizard::class, 'render' ) );
	}

	public static function setup_redirect(): void {
		if ( ! get_option( Activator::SETUP_REDIRECT_OPTION ) || wp_doing_ajax() || is_network_admin() ) {
			return;
		}
		delete_option( Activator::SETUP_REDIRECT_OPTION );
		if ( current_user_can( 'rc_manage_settings' ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=review-captain-setup' ) );
			exit;
		}
	}

	public static function assets( string $hook ): void {
		if ( false === strpos( $hook, 'review-captain' ) ) {
			return;
		}
		wp_enqueue_style( 'review-captain-admin', REVIEW_CAPTAIN_URL . 'assets/css/admin.css', array(), REVIEW_CAPTAIN_VERSION );
	}

	/**
	 * Shared admin notice from redirect query args.
	 */
	public static function notices(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$msg = isset( $_GET['rc_msg'] ) ? sanitize_key( wp_unslash( $_GET['rc_msg'] ) ) : '';
		$err = isset( $_GET['rc_err'] ) ? sanitize_text_field( wp_unslash( $_GET['rc_err'] ) ) : '';
		// phpcs:enable
		$messages = array(
			'saved'       => __( 'Saved.', 'review-captain' ),
			'deleted'     => __( 'Deleted.', 'review-captain' ),
			'moderated'   => __( 'Status updated.', 'review-captain' ),
			'imported'    => __( 'Import finished.', 'review-captain' ),
			'rolled_back' => __( 'Import rolled back.', 'review-captain' ),
			'reset'       => __( 'Settings were reset to their defaults. Reviews and other content were not changed.', 'review-captain' ),
			'erased'      => __( 'All Review Captain data of this site was deleted.', 'review-captain' ),
			'queued'      => __( 'Task queued.', 'review-captain' ),
		);
		if ( isset( $messages[ $msg ] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $messages[ $msg ] ) . '</p></div>';
		}
		if ( '' !== $err ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $err ) . '</p></div>';
		}
	}

	/**
	 * Redirect back with a message.
	 *
	 * @param array<string,string> $args Extra query args.
	 */
	public static function back( string $page, string $msg = '', string $error = '', array $args = array() ): void {
		$args['page'] = $page;
		if ( $msg ) {
			$args['rc_msg'] = $msg;
		}
		if ( $error ) {
			$args['rc_err'] = rawurlencode( $error );
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function post_url(): string {
		return admin_url( 'admin-post.php' );
	}
}
