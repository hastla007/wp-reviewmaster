<?php
/**
 * Theme-overridable templates.
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Frontend;

/**
 * Locates templates in `{theme}/review-captain/{name}` first, then in the plugin's `templates/`.
 * Templates receive a single `$rc` array with prepared (raw) values and must escape on output.
 */
final class TemplateLoader {

	/**
	 * Known templates (no arbitrary paths).
	 */
	public const TEMPLATES = array( 'review-card.php', 'reviews-grid.php', 'review-form.php', 'cta.php' );

	/**
	 * Resolve a template path.
	 *
	 * @param string $name Template file name.
	 */
	public static function locate( string $name ): string {
		if ( ! in_array( $name, self::TEMPLATES, true ) ) {
			return '';
		}
		$file = '';
		if ( function_exists( 'locate_template' ) ) {
			$file = (string) locate_template( array( 'review-captain/' . $name ), false, false );
		}
		if ( '' === $file ) {
			$file = REVIEW_CAPTAIN_DIR . 'templates/' . $name;
		}
		/**
		 * Filter the template path.
		 *
		 * @param string $file Absolute path.
		 * @param string $name Template name.
		 */
		$file = (string) apply_filters( 'review_captain_template', $file, $name );
		return is_readable( $file ) ? $file : '';
	}

	/**
	 * Render a template to a string.
	 *
	 * @param string               $name Template file name.
	 * @param array<string, mixed> $rc   Template data.
	 */
	public static function render( string $name, array $rc ): string {
		$file = self::locate( $name );
		if ( '' === $file ) {
			return '';
		}
		ob_start();
		( static function ( string $__file, array $rc ): void {
			include $__file;
		} )( $file, $rc );
		return trim( (string) ob_get_clean() );
	}
}
