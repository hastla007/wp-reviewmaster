<?php
/**
 * Bootstrap: wires all services into WordPress hooks.
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain;

use ReviewCaptain\Database\Schema;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	private static bool $booted = false;

	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		add_action(
			'init',
			static function () {
				load_plugin_textdomain( 'review-captain', false, dirname( plugin_basename( REVIEW_CAPTAIN_FILE ) ) . '/languages' );
			},
			1
		);

		// Schema upgrades only in admin/CLI, never on frontend requests (RC-028).
		add_action( 'admin_init', array( self::class, 'maybe_upgrade' ) );

		Jobs\Runner::register();
		Maintenance\Retention::register();
		Privacy\PrivacyIntegration::register();
		Connectors\Registry::instance()->register_hooks();
		Frontend\Assets::register();
		Frontend\Shortcodes::register();
		Frontend\Blocks::register();
		Frontend\SubmissionController::register();
		Seo\SchemaOutput::register();
		Api\PublicApi::register();

		add_action( 'wp_initialize_site', array( Install\Activator::class, 'on_new_site' ), 20 );
		add_filter( 'wpmu_drop_tables', array( Install\Activator::class, 'drop_tables_for_site' ) );

		if ( is_admin() ) {
			Admin\Menu::register();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'review-captain', Cli\Command::class );
		}

		/**
		 * Fires after Review Captain Core is fully loaded. Add-ons (e.g. Pro) hook in here.
		 *
		 * @param string $version Core version.
		 */
		do_action( 'review_captain_loaded', REVIEW_CAPTAIN_VERSION );
	}

	public static function maybe_upgrade(): void {
		if ( ! Schema::needs_upgrade() ) {
			return;
		}
		if ( ! current_user_can( 'activate_plugins' ) && ! current_user_can( 'rc_manage_settings' ) ) {
			return;
		}
		Install\Activator::install_site();
	}
}
