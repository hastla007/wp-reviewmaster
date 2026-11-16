<?php
/**
 * Request-local collector of reviews rendered visibly in the current response (SEO-007, E5).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Seo;

use ReviewCaptain\Domain\Review;
use ReviewCaptain\Plugin;

/**
 * The renderer reports every review it prints into the page HTML. Schema output is built
 * exclusively from this list, so markup always matches the visible content.
 *
 * Entries are deduplicated per UUID (A5). `predicted` entries come from scanning the main
 * post content before rendering (needed for Yoast, whose graph is generated in wp_head);
 * they use the same query as the later render.
 */
final class VisibleReviews {

	public const SERVICE = 'visible_reviews';

	/**
	 * Entries keyed by UUID.
	 *
	 * @var array<string, array{review: Review, widget: string, predicted: bool}>
	 */
	private array $entries = array();

	/**
	 * Get (and lazily register) the shared request-local instance.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public static function from_plugin( Plugin $plugin ): self {
		if ( ! $plugin->has( self::SERVICE ) ) {
			$plugin->factory( self::SERVICE, static fn(): self => new self() );
		}
		/** @var self */
		return $plugin->get( self::SERVICE );
	}

	/**
	 * Record a rendered review.
	 *
	 * @param Review $review    Review.
	 * @param string $widget_id Widget instance ID.
	 * @param bool   $predicted Whether this is a pre-render prediction from post content.
	 */
	public function add( Review $review, string $widget_id = '', bool $predicted = false ): void {
		$key = '' !== $review->uuid ? $review->uuid : 'id-' . (string) $review->id;
		if ( isset( $this->entries[ $key ] ) ) {
			// A real render upgrades a prediction.
			if ( ! $predicted ) {
				$this->entries[ $key ]['predicted'] = false;
			}
			return;
		}
		$this->entries[ $key ] = array(
			'review'    => $review,
			'widget'    => $widget_id,
			'predicted' => $predicted,
		);
	}

	/**
	 * Whether the review was rendered (or is predicted to be rendered) in this request.
	 *
	 * @param Review $review Review.
	 */
	public function has( Review $review ): bool {
		$key = '' !== $review->uuid ? $review->uuid : 'id-' . (string) $review->id;
		return isset( $this->entries[ $key ] );
	}

	/**
	 * All visible reviews, in render order.
	 *
	 * @return list<Review>
	 */
	public function reviews(): array {
		return array_values( array_map( static fn( array $e ): Review => $e['review'], $this->entries ) );
	}

	/**
	 * Number of entries.
	 */
	public function count(): int {
		return count( $this->entries );
	}

	/**
	 * Clear (tests / new request simulation).
	 */
	public function reset(): void {
		$this->entries = array();
	}
}
