<?php
/**
 * Yoast schema graph piece (SEO-006/008). Defined without extending Yoast classes so the file loads
 * even when Yoast is not active; Yoast only requires is_needed() and generate().
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Seo;

defined( 'ABSPATH' ) || exit;

final class YoastReviewsPiece {

	/** @var object Yoast Meta_Tags_Context. */
	public $context;

	/** @var object|null Yoast helpers (set by Yoast). */
	public $helpers;

	/**
	 * @param object $context Yoast context.
	 */
	public function __construct( $context ) {
		$this->context = $context;
	}

	public function is_needed(): bool {
		return (bool) SchemaOutput::reviews_for_current_page() && ! SchemaOutput::woo_has_native_rating();
	}

	/**
	 * @return array<int,array<string,mixed>>|false
	 */
	public function generate() {
		$reviews = SchemaOutput::reviews_for_current_page();
		if ( ! $reviews ) {
			return false;
		}
		// Link to the product node Yoast/WooCommerce SEO created; fall back to the WebPage node.
		$canonical = isset( $this->context->canonical ) ? (string) $this->context->canonical : (string) get_permalink();
		$item_id   = is_singular( 'product' ) ? $canonical . '#product' : ( isset( $this->context->main_schema_id ) ? (string) $this->context->main_schema_id : $canonical );
		return SchemaOutput::review_nodes( $reviews, $item_id );
	}
}
