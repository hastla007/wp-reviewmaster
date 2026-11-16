<?php
/**
 * Admin module: menu, pages, assets and admin-post handlers.
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Admin;

use ReviewCaptain\Module;
use ReviewCaptain\Plugin;
use ReviewCaptain\Security\Capabilities;

/**
 * Only active in wp-admin (including admin-post.php). Registers no hooks on the frontend.
 */
final class AdminModule implements Module {

	public const MENU_SLUG = DashboardPage::SLUG;

	/**
	 * Hook suffixes of our pages (for asset loading).
	 *
	 * @var list<string>
	 */
	private array $hooks = array();

	private Plugin $plugin;

	/**
	 * Register hooks.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function register( Plugin $plugin ): void {
		if ( ! is_admin() ) {
			return;
		}
		$this->plugin = $plugin;
		add_action( 'admin_menu', array( $this, 'menu' ), 9 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );

		$reviews   = new ReviewActions( $plugin );
		$sources   = new SourcesPage( $plugin );
		$settings  = new SettingsPage( $plugin );
		$diag      = new DiagnosticsPage( $plugin );
		$checklist = new SetupChecklist( $plugin );

		$handlers = array(
			'save_review'        => array( $reviews, 'handle_save' ),
			'review_status'      => array( $reviews, 'handle_status' ),
			'save_profile'       => array( $sources, 'handle_save' ),
			'delete_profile'     => array( $sources, 'handle_delete' ),
			'save_settings'      => array( $settings, 'handle_save' ),
			'reset_settings'     => array( $settings, 'handle_reset' ),
			'export_settings'    => array( $settings, 'handle_export' ),
			'import_settings'    => array( $settings, 'handle_import_preview' ),
			'import_apply'       => array( $settings, 'handle_import_apply' ),
			'rollback_settings'  => array( $settings, 'handle_rollback' ),
			'diagnostics_export' => array( $diag, 'handle_export' ),
			'checklist'          => array( $checklist, 'handle' ),
		);
		foreach ( $handlers as $action => $callback ) {
			add_action( 'admin_post_review_captain_' . $action, $callback );
		}
	}

	/**
	 * Admin menu (Free part). Pro areas are not registered as empty pages.
	 */
	public function menu(): void {
		$plugin      = $this->plugin;
		$dashboard   = new DashboardPage( $plugin );
		$reviews     = new ReviewsPage( $plugin );
		$widgets     = new WidgetsPage( $plugin );
		$sources     = new SourcesPage( $plugin );
		$settings    = new SettingsPage( $plugin );
		$diagnostics = new DiagnosticsPage( $plugin );

		$this->hooks[] = (string) add_menu_page(
			__( 'Review Captain', 'review-captain' ),
			__( 'Review Captain', 'review-captain' ),
			Capabilities::MODERATE,
			self::MENU_SLUG,
			array( $dashboard, 'render' ),
			'dashicons-star-filled',
			26
		);
		$pages = array(
			array( DashboardPage::SLUG, __( 'Dashboard', 'review-captain' ), Capabilities::MODERATE, array( $dashboard, 'render' ) ),
			array( ReviewsPage::SLUG, __( 'Reviews', 'review-captain' ), Capabilities::MODERATE, array( $reviews, 'render' ) ),
			array( WidgetsPage::SLUG, __( 'Widgets', 'review-captain' ), Capabilities::MODERATE, array( $widgets, 'render' ) ),
			array( SourcesPage::SLUG, __( 'Quellen & Profile', 'review-captain' ), Capabilities::MANAGE_SOURCES, array( $sources, 'render' ) ),
			array( SettingsPage::SLUG, __( 'Einstellungen', 'review-captain' ), Capabilities::MANAGE_SETTINGS, array( $settings, 'render' ) ),
			array( DiagnosticsPage::SLUG, __( 'Diagnose', 'review-captain' ), Capabilities::VIEW_REPORTS, array( $diagnostics, 'render' ) ),
		);
		foreach ( $pages as [ $slug, $title, $cap, $callback ] ) {
			$hook = add_submenu_page( self::MENU_SLUG, $title . ' ‹ Review Captain', $title, $cap, $slug, $callback );
			if ( is_string( $hook ) && '' !== $hook ) {
				$this->hooks[] = $hook;
				if ( ReviewsPage::SLUG === $slug ) {
					// Bulk actions are processed before output so the redirect works.
					add_action( 'load-' . $hook, array( new ReviewActions( $plugin ), 'maybe_handle_bulk' ) );
				}
			}
		}
	}

	/**
	 * Hook suffixes of registered pages.
	 *
	 * @return list<string>
	 */
	public function page_hooks(): array {
		return array_values( array_filter( $this->hooks ) );
	}

	/**
	 * Load admin assets on our own pages only.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, $this->page_hooks(), true ) ) {
			return;
		}
		wp_enqueue_style( 'review-captain-admin', REVIEW_CAPTAIN_URL . 'assets/admin/admin.css', array(), REVIEW_CAPTAIN_VERSION );
		wp_enqueue_script( 'review-captain-admin', REVIEW_CAPTAIN_URL . 'assets/admin/admin.js', array(), REVIEW_CAPTAIN_VERSION, true );
	}
}
