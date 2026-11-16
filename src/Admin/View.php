<?php
/**
 * Small, escaped rendering helpers for admin screens.
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Admin;

use ReviewCaptain\Domain\Review;

/**
 * Stateless helpers. Every helper returns escaped HTML or plain strings that callers escape.
 */
final class View {

	/**
	 * Admin URL of one of our pages.
	 *
	 * @param string               $page Page slug.
	 * @param array<string, mixed> $args Extra query args.
	 */
	public static function page_url( string $page, array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * URL of admin-post.php with action and nonce.
	 *
	 * @param string               $action Short action (without review_captain_ prefix).
	 * @param array<string, mixed> $args   Extra args.
	 */
	public static function action_url( string $action, array $args = array() ): string {
		$url = add_query_arg( array_merge( array( 'action' => 'review_captain_' . $action ), $args ), admin_url( 'admin-post.php' ) );
		return wp_nonce_url( $url, 'review_captain_' . $action, '_rc_nonce' );
	}

	/**
	 * Hidden fields for an admin-post form (action + nonce).
	 *
	 * @param string $action Short action.
	 */
	public static function form_fields( string $action ): string {
		return '<input type="hidden" name="action" value="' . esc_attr( 'review_captain_' . $action ) . '" />'
			. wp_nonce_field( 'review_captain_' . $action, '_rc_nonce', true, false );
	}

	/**
	 * Rating on its original scale, e.g. "4/5" or "8,5/10". Never converted to another scale.
	 *
	 * @param Review $review Review.
	 */
	public static function rating( Review $review ): string {
		if ( null === $review->rating_value ) {
			return '–';
		}
		return self::number( $review->rating_value ) . '/' . self::number( $review->rating_scale_max );
	}

	/**
	 * Locale formatted number without useless decimals.
	 *
	 * @param float $value Value.
	 */
	public static function number( float $value ): string {
		$decimals = abs( $value - round( $value ) ) < 0.001 ? 0 : ( abs( $value * 10 - round( $value * 10 ) ) < 0.001 ? 1 : 2 );
		return number_format_i18n( $value, $decimals );
	}

	/**
	 * Format a UTC DB datetime in site time.
	 *
	 * @param string|null $utc   UTC datetime.
	 * @param bool        $time  Include time.
	 */
	public static function date( ?string $utc, bool $time = false ): string {
		if ( null === $utc || '' === $utc ) {
			return '–';
		}
		$ts = strtotime( $utc . ' UTC' );
		if ( false === $ts ) {
			return '–';
		}
		$format = (string) get_option( 'date_format', 'd.m.Y' );
		if ( $time ) {
			$format .= ' ' . (string) get_option( 'time_format', 'H:i' );
		}
		return (string) wp_date( '' !== trim( $format ) ? $format : 'd.m.Y', $ts );
	}

	/**
	 * Shorten text to N characters.
	 *
	 * @param string $text Text.
	 * @param int    $len  Max length.
	 */
	public static function excerpt( string $text, int $len = 80 ): string {
		$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $text ) ) ?? '' );
		return mb_strlen( $text ) > $len ? rtrim( mb_substr( $text, 0, $len - 1 ) ) . '…' : $text;
	}

	/**
	 * A status pill (text + modifier class, never colour only).
	 *
	 * @param string $label    Label.
	 * @param string $modifier ok|warning|error|neutral.
	 */
	public static function pill( string $label, string $modifier = 'neutral' ): string {
		return '<span class="rc-admin-status rc-admin-status--' . esc_attr( sanitize_html_class( $modifier, 'neutral' ) ) . '">' . esc_html( $label ) . '</span>';
	}

	/**
	 * Notice box.
	 *
	 * @param string $type    success|info|warning|error.
	 * @param string $message Plain message (escaped here).
	 * @param bool   $inline  Inline notice (not moved by WP).
	 */
	public static function notice( string $type, string $message, bool $inline = false ): string {
		return '<div class="notice notice-' . esc_attr( sanitize_html_class( $type, 'info' ) ) . ( $inline ? ' inline' : '' ) . '"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Locked Pro hint (visible, not clickable, no upsell wall).
	 *
	 * @param string $text Text.
	 */
	public static function pro_lock( string $text ): string {
		return '<span class="rc-admin-pro-lock" aria-disabled="true"><span class="dashicons dashicons-lock" aria-hidden="true"></span> ' . esc_html( $text ) . '</span>';
	}

	/**
	 * Standard page header.
	 *
	 * @param string      $title        Title.
	 * @param string|null $action_url   Optional page-title action URL.
	 * @param string      $action_label Action label.
	 */
	public static function header( string $title, ?string $action_url = null, string $action_label = '' ): string {
		$html = '<h1 class="wp-heading-inline">' . esc_html( $title ) . '</h1>';
		if ( null !== $action_url ) {
			$html .= ' <a href="' . esc_url( $action_url ) . '" class="page-title-action">' . esc_html( $action_label ) . '</a>';
		}
		return $html . '<hr class="wp-header-end" />';
	}

	/**
	 * Read a request string (unslashed, sanitized).
	 *
	 * @param array<string, mixed> $source Source array ($_GET/$_POST).
	 * @param string               $key    Key.
	 * @param string               $fallback Fallback.
	 */
	public static function req( array $source, string $key, string $fallback = '' ): string {
		if ( ! isset( $source[ $key ] ) || ! is_scalar( $source[ $key ] ) ) {
			return $fallback;
		}
		return sanitize_text_field( wp_unslash( (string) $source[ $key ] ) );
	}

	/**
	 * Redirect safely and stop.
	 *
	 * @param string $url URL.
	 */
	public static function redirect( string $url ): void {
		wp_safe_redirect( $url );
		exit;
	}
}
