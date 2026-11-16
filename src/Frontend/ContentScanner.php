<?php
/**
 * Finds Review Captain widgets in post content (asset loading, SEO priming).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Frontend;

use ReviewCaptain\Widgets\WidgetDefinition;

/**
 * Parses blocks and shortcodes without rendering them.
 */
final class ContentScanner {

	/**
	 * Quick check whether content contains any Review Captain block/shortcode.
	 *
	 * @param string $content Post content.
	 */
	public static function contains_widget( string $content ): bool {
		return str_contains( $content, '<!-- wp:review-captain/' ) || (bool) preg_match( '/\[review_captain(?:_form|_cta|_qa)?[\s\]]/', $content );
	}

	/**
	 * Reviews widget definitions found in the content, in document order.
	 *
	 * @param string $content Post content.
	 * @return list<WidgetDefinition>
	 */
	public static function review_definitions( string $content ): array {
		$defs = array();
		if ( str_contains( $content, '<!-- wp:review-captain/reviews' ) ) {
			if ( function_exists( 'parse_blocks' ) ) {
				foreach ( self::flatten( parse_blocks( $content ) ) as $block ) {
					if ( 'review-captain/reviews' === ( $block['blockName'] ?? '' ) ) {
						$defs[] = WidgetDefinition::from_block_attributes( is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array(), 'reviews' );
					}
				}
			} elseif ( preg_match_all( '/<!--\s+wp:review-captain\/reviews(\s+(\{.*?\}))?\s+\/?-->/s', $content, $m, PREG_SET_ORDER ) ) {
				foreach ( $m as $match ) {
					$attrs  = isset( $match[2] ) ? json_decode( $match[2], true ) : array();
					$defs[] = WidgetDefinition::from_block_attributes( is_array( $attrs ) ? $attrs : array(), 'reviews' );
				}
			}
		}
		if ( preg_match_all( '/\[review_captain(\s[^\]]*)?\]/', $content, $m ) ) {
			foreach ( $m[1] as $att_string ) {
				$atts = self::parse_atts( (string) $att_string );
				$def  = WidgetDefinition::from_atts( $atts, 'reviews' );
				if ( 'reviews' === $def->type ) {
					$defs[] = $def;
				}
			}
		}
		return $defs;
	}

	/**
	 * Parse shortcode attributes.
	 *
	 * @param string $text Attribute string.
	 * @return array<string, string>
	 */
	private static function parse_atts( string $text ): array {
		if ( function_exists( 'shortcode_parse_atts' ) ) {
			$atts = shortcode_parse_atts( $text );
			return is_array( $atts ) ? array_filter( $atts, 'is_string', ARRAY_FILTER_USE_KEY ) : array();
		}
		$atts = array();
		if ( preg_match_all( '/([\w-]+)\s*=\s*"([^"]*)"|([\w-]+)\s*=\s*\'([^\']*)\'/', $text, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $match ) {
				if ( '' !== $match[1] ) {
					$atts[ strtolower( $match[1] ) ] = $match[2];
				} else {
					$atts[ strtolower( $match[3] ) ] = $match[4];
				}
			}
		}
		return $atts;
	}

	/**
	 * Flatten nested blocks.
	 *
	 * @param array<int, mixed> $blocks Blocks.
	 * @return list<array<string, mixed>>
	 */
	private static function flatten( array $blocks ): array {
		$out = array();
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			$out[] = $block;
			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$out = array_merge( $out, self::flatten( $block['innerBlocks'] ) );
			}
		}
		return $out;
	}
}
