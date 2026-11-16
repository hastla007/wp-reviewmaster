<?php
/**
 * Frontend/editor assets. CSS is loaded only when a widget is rendered (no global assets).
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Frontend;

use ReviewCaptain\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class Assets {

	public static function register(): void {
		add_action( 'init', array( self::class, 'register_assets' ) );
	}

	public static function register_assets(): void {
		wp_register_style( 'review-captain', REVIEW_CAPTAIN_URL . 'assets/css/frontend.css', array(), REVIEW_CAPTAIN_VERSION );
		wp_register_script( 'review-captain-form', REVIEW_CAPTAIN_URL . 'assets/js/form.js', array(), REVIEW_CAPTAIN_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		wp_register_script(
			'review-captain-blocks',
			REVIEW_CAPTAIN_URL . 'assets/js/blocks.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render', 'wp-i18n' ),
			REVIEW_CAPTAIN_VERSION,
			true
		);
		wp_set_script_translations( 'review-captain-blocks', 'review-captain', REVIEW_CAPTAIN_DIR . 'languages' );
	}

	public static function enqueue_frontend(): void {
		if ( Settings::get( 'load_css' ) ) {
			wp_enqueue_style( 'review-captain' );
		}
	}
}
