<?php
/**
 * Yoast SEO graph piece (only loaded when Yoast's schema API exists).
 *
 * IMPORTANT: Reference this class only after checking
 * class_exists( '\Yoast\WP\SEO\Generators\Schema\Abstract_Schema_Piece' ); otherwise
 * loading this file would fatal. SeoModule takes care of this.
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Seo;

if ( ! class_exists( '\Yoast\WP\SEO\Generators\Schema\Abstract_Schema_Piece' ) ) {
	return;
}

/**
 * Adds Review nodes for eligible, visible local reviews to Yoast's graph.
 */
final class YoastReviewPiece extends \Yoast\WP\SEO\Generators\Schema\Abstract_Schema_Piece {

	/**
	 * Node provider: fn( ?string $canonical, ?string $webpage_id ): list<array>.
	 *
	 * @var callable
	 */
	private $provider;

	/**
	 * Cached nodes.
	 *
	 * @var list<array<string, mixed>>|null
	 */
	private ?array $nodes = null;

	/**
	 * Constructor.
	 *
	 * @param callable $provider Provider.
	 */
	public function __construct( callable $provider ) {
		$this->provider = $provider;
	}

	/**
	 * Whether the piece should be added.
	 *
	 * @return bool
	 */
	public function is_needed() {
		return array() !== $this->nodes();
	}

	/**
	 * Generate nodes.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function generate() {
		return $this->nodes();
	}

	/**
	 * Resolve nodes once.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function nodes(): array {
		if ( null === $this->nodes ) {
			$canonical  = isset( $this->context->canonical ) && is_string( $this->context->canonical ) ? $this->context->canonical : null;
			$webpage_id = isset( $this->context->main_schema_id ) && is_string( $this->context->main_schema_id ) ? $this->context->main_schema_id : null;
			$nodes      = ( $this->provider )( $canonical, $webpage_id );
			$this->nodes = is_array( $nodes ) ? array_values( $nodes ) : array();
		}
		return $this->nodes;
	}
}
