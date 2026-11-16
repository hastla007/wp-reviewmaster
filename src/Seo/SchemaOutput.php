<?php
/**
 * Structured data for eligible own reviews only (RC-055, SEO-005 – SEO-011, docs/seo/seo-regeln.md).
 *
 * - Collects reviews that were actually rendered on the page (SEO-007).
 * - Excludes external/imported platform reviews, organization/local-business self reviews and cross-platform scores (SEO-009).
 * - With Yoast: adds a graph piece linked to the existing Product/WebPage node (SEO-006/008).
 * - Without any SEO/schema plugin: prints its own JSON-LD in the footer (SEO-010).
 * - Never outputs title, description, canonical, robots, Open Graph or sitemap data (SEO-011).
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Seo;

defined( 'ABSPATH' ) || exit;

final class SchemaOutput {

	/** Context types for which own review markup may be output (⚠ re-check Google guidelines before release). */
	public const ELIGIBLE_CONTEXTS = array( 'product', 'course', 'event', 'app' );

	/** @var array<int,array<string,mixed>> */
	private static array $collected = array();

	public static function register(): void {
		add_filter( 'wpseo_schema_graph_pieces', array( self::class, 'yoast_pieces' ), 20, 2 );
		add_action( 'wp_footer', array( self::class, 'print_fallback' ), 99 );
		add_filter( 'wpseo_robots', array( self::class, 'yoast_robots' ) );
		add_filter( 'wp_robots', array( self::class, 'wp_robots' ) );
	}

	/**
	 * Detect SEO/schema plugins without class or table access (SEO-005).
	 *
	 * @return array{yoast: string|null, competing: string[]}
	 */
	public static function environment(): array {
		$competing = array();
		$map       = array(
			'RANK_MATH_VERSION'         => 'Rank Math',
			'SEOPRESS_VERSION'          => 'SEOPress',
			'AIOSEO_VERSION'            => 'All in One SEO',
			'THE_SEO_FRAMEWORK_VERSION' => 'The SEO Framework',
			'BSF_AIOSRS_PRO_VER'        => 'Schema Pro',
		);
		foreach ( $map as $const => $label ) {
			if ( defined( $const ) ) {
				$competing[] = $label;
			}
		}
		return array(
			'yoast'     => defined( 'WPSEO_VERSION' ) ? (string) WPSEO_VERSION : null,
			'competing' => (array) apply_filters( 'review_captain_competing_schema_plugins', $competing ),
		);
	}

	public static function yoast_supported(): bool {
		$env = self::environment();
		return null !== $env['yoast'] && version_compare( $env['yoast'], '20.0', '>=' );
	}

	/**
	 * Filter rendered reviews down to those eligible for markup (SEO-009).
	 *
	 * @param array<int,array<string,mixed>> $reviews Review rows.
	 * @return array<int,array<string,mixed>>
	 */
	public static function eligible_reviews( array $reviews ): array {
		$out = array();
		foreach ( $reviews as $r ) {
			if ( self::is_eligible( $r ) ) {
				$out[] = array(
					'uuid'         => $r['uuid'],
					'rating_value' => (float) $r['rating_value'],
					'rating_min'   => (int) $r['rating_min'],
					'rating_max'   => (int) $r['rating_max'],
					'author'       => (string) $r['author_name'],
					'date'         => (string) ( $r['published_at'] ?? $r['created_at'] ),
					'body'         => (string) $r['content'],
					'context_type' => (string) $r['context_type'],
					'context_id'   => (string) $r['context_id'],
				);
			}
		}
		return $out;
	}

	/**
	 * @param array<string,mixed> $r Review row.
	 */
	public static function is_eligible( array $r ): bool {
		if ( 'site' !== ( $r['provider'] ?? '' ) ) {
			return false; // External platform reviews: never.
		}
		if ( ! in_array( $r['origin'] ?? '', array( 'local', 'connector' ), true ) ) {
			return false; // Imported/manual entries were not submitted on this site.
		}
		if ( 'approved' !== ( $r['status'] ?? '' ) || null === ( $r['rating_value'] ?? null ) || '' === trim( (string) ( $r['author_name'] ?? '' ) ) ) {
			return false;
		}
		// Reviews about the own organization/local business are not marked up (self-serving reviews).
		return in_array( (string) ( $r['context_type'] ?? '' ), self::ELIGIBLE_CONTEXTS, true );
	}

	/**
	 * @param array<int,array<string,mixed>> $items Eligible review data.
	 */
	public static function collect( array $items ): void {
		foreach ( $items as $item ) {
			self::$collected[ $item['uuid'] ] = $item;
		}
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function collected(): array {
		return array_values( self::$collected );
	}

	public static function reset(): void {
		self::$collected = array();
	}

	/**
	 * The page must be about the reviewed item: we only mark up reviews whose context matches the queried product.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function reviews_for_current_page(): array {
		if ( ! is_singular() ) {
			return array();
		}
		$post_id   = (string) get_queried_object_id();
		$post_type = get_post_type( (int) $post_id );
		$type      = 'product' === $post_type ? 'product' : (string) apply_filters( 'review_captain_schema_context_type', '', $post_type );
		if ( '' === $type ) {
			return array();
		}
		return array_values(
			array_filter(
				self::collected(),
				static function ( $r ) use ( $type, $post_id ) {
					return $r['context_type'] === $type && $r['context_id'] === $post_id;
				}
			)
		);
	}

	/**
	 * WooCommerce (or Yoast WooCommerce SEO) already outputs AggregateRating for this product (SEO-008).
	 */
	public static function woo_has_native_rating(): bool {
		if ( ! function_exists( 'wc_get_product' ) || ! is_singular( 'product' ) ) {
			return false;
		}
		$product = wc_get_product( get_queried_object_id() );
		return $product && method_exists( $product, 'get_review_count' ) && $product->get_review_count() > 0 && 'yes' === get_option( 'woocommerce_enable_reviews', 'yes' );
	}

	/**
	 * Build schema review nodes.
	 *
	 * @param array<int,array<string,mixed>> $reviews Eligible reviews.
	 * @return array<int,array<string,mixed>>
	 */
	public static function review_nodes( array $reviews, string $item_id ): array {
		$nodes = array();
		foreach ( $reviews as $r ) {
			$nodes[] = array(
				'@type'         => 'Review',
				'@id'           => home_url( '/#/schema/rc-review/' . $r['uuid'] ),
				'itemReviewed'  => array( '@id' => $item_id ),
				'reviewRating'  => array(
					'@type'       => 'Rating',
					'ratingValue' => $r['rating_value'],
					'bestRating'  => $r['rating_max'],
					'worstRating' => $r['rating_min'],
				),
				'author'        => array(
					'@type' => 'Person',
					'name'  => $r['author'],
				),
				'datePublished' => gmdate( 'Y-m-d', strtotime( $r['date'] . ' UTC' ) ),
				'reviewBody'    => wp_strip_all_tags( $r['body'] ),
			);
		}
		return $nodes;
	}

	/**
	 * Yoast graph integration (SEO-006): add a piece that references Yoast's product/webpage node.
	 *
	 * @param array<int,object> $pieces  Graph pieces.
	 * @param object            $context Yoast meta tags context.
	 * @return array<int,object>
	 */
	public static function yoast_pieces( $pieces, $context ) {
		if ( ! self::yoast_supported() ) {
			return $pieces;
		}
		$pieces[] = new YoastReviewsPiece( $context );
		return $pieces;
	}

	/**
	 * Own JSON-LD only when no SEO/schema owner is active and the page is indexable (SEO-010).
	 */
	public static function print_fallback(): void {
		$env = self::environment();
		if ( null !== $env['yoast'] || $env['competing'] ) {
			return;
		}
		if ( self::woo_has_native_rating() ) {
			return;
		}
		$reviews = self::reviews_for_current_page();
		if ( ! $reviews || self::page_is_noindex() ) {
			return;
		}
		$item_id = get_permalink() . '#rc-item';
		$nodes   = self::review_nodes( $reviews, $item_id );
		$sum     = array_sum( array_map( static fn( $r ) => ( $r['rating_value'] - $r['rating_min'] ) / max( 1, $r['rating_max'] - $r['rating_min'] ) * 4 + 1, $reviews ) );
		$graph   = array(
			'@context' => 'https://schema.org',
			'@graph'   => array_merge(
				array(
					array(
						'@type'           => 'Product',
						'@id'             => $item_id,
						'name'            => wp_strip_all_tags( get_the_title() ),
						'url'             => get_permalink(),
						'aggregateRating' => array(
							'@type'       => 'AggregateRating',
							'ratingValue' => round( $sum / count( $reviews ), 1 ),
							'bestRating'  => 5,
							'worstRating' => 1,
							'reviewCount' => count( $reviews ),
						),
					),
				),
				$nodes
			),
		);
		echo "\n" . '<script type="application/ld+json" class="review-captain-schema">' . wp_json_encode( $graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ) . '</script>' . "\n";
	}

	private static function page_is_noindex(): bool {
		$robots = function_exists( 'wp_robots' ) ? apply_filters( 'wp_robots', array() ) : array();
		return ! empty( $robots['noindex'] ) || ! get_option( 'blog_public' );
	}

	/**
	 * Widget pagination parameters must not be indexed; Yoast remains owner (SEO-004/014).
	 *
	 * @param string $robots Yoast robots string.
	 */
	public static function yoast_robots( $robots ) {
		return self::is_rc_paged_request() ? 'noindex, follow' : $robots;
	}

	/**
	 * @param array<string,bool> $robots WP robots directives.
	 * @return array<string,bool>
	 */
	public static function wp_robots( array $robots ): array {
		if ( null === self::environment()['yoast'] && self::is_rc_paged_request() ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	}

	private static function is_rc_paged_request(): bool {
		foreach ( array_keys( $_GET ) as $key ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( 0 === strpos( (string) $key, 'rc_page_' ) ) {
				return true;
			}
		}
		return false;
	}
}
