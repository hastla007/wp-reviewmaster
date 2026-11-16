<?php
/**
 * Builds Review graph nodes for eligible reviews (A1–A6).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Seo;

use ReviewCaptain\Domain\Review;

/**
 * Creates schema.org nodes. Never creates Organization, LocalBusiness, WebSite, WebPage,
 * BreadcrumbList or a second Product owned by another plugin (A4).
 *
 * AggregateRating (A2): **not emitted**. Offene Entscheidung SEO-OPEN-8 – bis zur
 * rechtlichen/SEO-Prüfung gibt der Core ausschließlich Einzel-Review-Knoten aus.
 */
final class SchemaBuilder {

	/**
	 * Canonical URL of the current page (base for @id).
	 */
	private string $canonical;

	/**
	 * Existing WebPage @id to attach items to (Yoast), or null.
	 */
	private ?string $webpage_id;

	/**
	 * Constructor.
	 *
	 * @param string      $canonical  Canonical URL.
	 * @param string|null $webpage_id WebPage @id from the schema owner.
	 */
	public function __construct( string $canonical, ?string $webpage_id = null ) {
		$this->canonical  = '' !== $canonical ? strtok( $canonical, '#' ) : home_url( '/' );
		$this->webpage_id = $webpage_id;
	}

	/**
	 * Build nodes for a list of eligible reviews (dedupes by uuid, A5).
	 *
	 * @param list<Review> $reviews Eligible reviews.
	 * @return list<array<string, mixed>>
	 */
	public function nodes( array $reviews ): array {
		$nodes = array();
		$items = array();
		$seen  = array();
		foreach ( $reviews as $r ) {
			if ( isset( $seen[ $r->uuid ] ) ) {
				continue;
			}
			$seen[ $r->uuid ] = true;
			$item_id          = $this->item_id( $r );
			if ( ! isset( $items[ $item_id ] ) ) {
				$items[ $item_id ] = $this->item_node( $r, $item_id );
			}
			$nodes[] = $this->review_node( $r, $item_id );
		}
		return array_merge( array_values( $items ), $nodes );
	}

	/**
	 * Review node (A1, A6).
	 *
	 * @param Review $r       Review.
	 * @param string $item_id Item @id.
	 * @return array<string, mixed>
	 */
	public function review_node( Review $r, string $item_id ): array {
		$node = array(
			'@type'        => 'Review',
			'@id'          => $this->canonical . '#/schema/review-captain/review/' . $r->uuid,
			'itemReviewed' => array( '@id' => $item_id ),
			'reviewBody'   => wp_strip_all_tags( $r->body ),
		);
		if ( '' !== trim( $r->author_name ) ) {
			$node['author'] = array(
				'@type' => 'Person',
				'name'  => wp_strip_all_tags( $r->author_name ),
			);
		}
		$date = $r->published_at ?? ( '' !== $r->created_at ? $r->created_at : null );
		if ( null !== $date ) {
			$node['datePublished'] = gmdate( 'c', (int) strtotime( $date . ' UTC' ) );
		}
		if ( '' !== trim( $r->title ) ) {
			$node['name'] = wp_strip_all_tags( $r->title );
		}
		if ( null !== $r->rating_value ) {
			$node['reviewRating'] = array(
				'@type'       => 'Rating',
				'ratingValue' => self::num( $r->rating_value ),
				'bestRating'  => self::num( $r->rating_scale_max ),
				'worstRating' => self::num( $r->rating_scale_min ),
			);
		}
		return $node;
	}

	/**
	 * Minimal item node (A3). Typ für `post` laut SEO-OPEN-6 vorerst CreativeWork.
	 *
	 * @param Review $r       Review.
	 * @param string $item_id Item @id.
	 * @return array<string, mixed>
	 */
	public function item_node( Review $r, string $item_id ): array {
		$type = match ( $r->context_type ) {
			'product' => 'Product',
			'event'   => 'Event',
			default   => 'CreativeWork',
		};
		/**
		 * Filter the schema type of the reviewed item for `post` contexts (SEO-OPEN-6).
		 *
		 * @param string $type   Schema type.
		 * @param Review $review Review.
		 */
		if ( 'post' === $r->context_type ) {
			$filtered = apply_filters( 'review_captain_schema_post_item_type', $type, $r );
			$type     = is_string( $filtered ) && preg_match( '/^[A-Za-z]{2,40}$/', $filtered ) ? $filtered : $type;
		}
		$name = '';
		if ( null !== $r->context_id && ctype_digit( $r->context_id ) ) {
			$name = wp_strip_all_tags( (string) get_the_title( (int) $r->context_id ) );
		}
		$node = array(
			'@type' => $type,
			'@id'   => $item_id,
			'name'  => '' !== $name ? $name : wp_strip_all_tags( (string) get_bloginfo( 'name' ) ),
		);
		if ( null !== $this->webpage_id ) {
			$node['mainEntityOfPage'] = array( '@id' => $this->webpage_id );
		}
		return $node;
	}

	/**
	 * Item @id.
	 *
	 * @param Review $r Review.
	 */
	public function item_id( Review $r ): string {
		return $this->canonical . '#/schema/review-captain/item/' . sanitize_key( (string) $r->context_type ) . '-' . sanitize_key( (string) $r->context_id );
	}

	/**
	 * Full standalone JSON-LD document (fallback output).
	 *
	 * @param list<Review> $reviews Eligible reviews.
	 */
	public function json_ld( array $reviews ): string {
		$nodes = $this->nodes( $reviews );
		if ( ! $nodes ) {
			return '';
		}
		$json = wp_json_encode(
			array(
				'@context' => 'https://schema.org',
				'@graph'   => $nodes,
			),
			JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
		);
		return is_string( $json ) ? $json : '';
	}

	/**
	 * Number formatting for JSON (int when whole).
	 *
	 * @param float $v Value.
	 */
	private static function num( float $v ): int|float {
		return floor( $v ) === $v ? (int) $v : round( $v, 2 );
	}
}
