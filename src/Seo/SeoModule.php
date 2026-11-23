<?php
/**
 * SEO module: schema output (Yoast graph or fallback JSON-LD) and robots rule.
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Seo;

use ReviewCaptain\Domain\Review;
use ReviewCaptain\Frontend\ContentScanner;
use ReviewCaptain\Frontend\Renderer;
use ReviewCaptain\Module;
use ReviewCaptain\Plugin;

/**
 * Never outputs title, description, canonical, Open Graph or robots meta tags itself
 * (SEO-011); only adds noindex via the robots filters and Review nodes for eligible reviews.
 */
final class SeoModule implements Module {

	/**
	 * Yoast's abstract schema piece class.
	 */
	public const YOAST_PIECE_CLASS = '\Yoast\WP\SEO\Generators\Schema\Abstract_Schema_Piece';

	/**
	 * Plugin.
	 */
	private Plugin $plugin;

	/**
	 * Visible reviews collector.
	 */
	private VisibleReviews $visible;

	/**
	 * Environment (lazy).
	 */
	private ?SeoEnvironment $environment = null;

	/**
	 * Whether priming from post content ran.
	 */
	private bool $primed = false;

	/**
	 * Outcome of the last eligible_reviews() call (diagnostics).
	 */
	private string $last_reason = '';

	/**
	 * Register hooks.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function register( Plugin $plugin ): void {
		$this->plugin  = $plugin;
		$this->visible = VisibleReviews::from_plugin( $plugin );

		add_filter( 'wp_robots', array( Robots::class, 'filter_wp_robots' ), 20 );
		add_filter( 'wpseo_robots_array', array( Robots::class, 'filter_yoast_robots' ), 20 );
		add_action( 'wp_footer', array( $this, 'print_fallback_schema' ), 20 );
		add_filter( 'wpseo_schema_graph_pieces', array( $this, 'add_yoast_piece' ), 11, 2 );
		add_filter( 'wpseo_schema_graph', array( $this, 'filter_yoast_graph' ), 11, 2 );
	}

	/**
	 * Detected environment.
	 */
	public function environment(): SeoEnvironment {
		if ( null === $this->environment ) {
			$this->environment = SeoEnvironment::current();
		}
		return $this->environment;
	}

	/**
	 * Override environment (tests/diagnostics).
	 *
	 * @param SeoEnvironment $env Environment.
	 */
	public function set_environment( SeoEnvironment $env ): void {
		$this->environment = $env;
	}

	/**
	 * Admin switch "Review-Markup ausgeben" (default on). Can only disable.
	 */
	public function markup_enabled(): bool {
		return false !== $this->plugin->settings()->get( 'display', 'schema_enabled', true );
	}

	/**
	 * Eligible visible reviews of this request.
	 *
	 * @return list<Review>
	 */
	public function eligible_reviews(): array {
		$settings = $this->plugin->settings();
		$ctx      = array(
			'visible'     => $this->visible,
			'environment' => $this->environment(),
			'enabled'     => $this->markup_enabled(),
			// Only the item of a single page may receive review markup (QA F-09).
			'main_object_id' => function_exists( 'is_singular' ) && is_singular() ? (string) get_queried_object_id() : '',
			// SET-RC-23: context types switched on in the settings.
			'contexts'    => SchemaEligibility::enabled_contexts( $settings->get( 'display', 'schema_contexts', SchemaEligibility::ELIGIBLE_CONTEXTS ) ),
		);
		$eligible = array_values( array_filter( $this->visible->reviews(), static fn( Review $r ): bool => SchemaEligibility::is_eligible( $r, $ctx ) ) );
		// SET-RC-23: no markup below the minimum number of eligible reviews on the page.
		if ( $eligible && count( $eligible ) < self::min_reviews( $settings ) ) {
			$this->last_reason = 'below_minimum';
			return array();
		}
		$this->last_reason = $eligible ? 'eligible' : '';
		return $eligible;
	}

	/**
	 * Reason key of the last eligible_reviews() call when the minimum blocked the output
	 * ('below_minimum'), 'eligible', or '' (nothing eligible; see SchemaEligibility per review).
	 */
	public function last_reason(): string {
		return $this->last_reason;
	}

	/**
	 * Minimum number of eligible reviews before markup is output (display.schema_min_reviews,
	 * 1–50; the filter `review_captain_schema_min_reviews` wins).
	 *
	 * @param \ReviewCaptain\Settings\Settings $settings Settings.
	 */
	public static function min_reviews( $settings ): int {
		$min = (int) $settings->get( 'display', 'schema_min_reviews', 1 );
		/**
		 * Minimum number of eligible reviews on a page before review markup is output.
		 *
		 * @param int $min Minimum from the setting.
		 */
		return max( 1, min( 50, (int) apply_filters( 'review_captain_schema_min_reviews', $min ) ) );
	}

	/**
	 * Fallback JSON-LD in wp_footer – only without any SEO/schema plugin (SEO-010).
	 */
	public function print_fallback_schema(): void {
		echo $this->fallback_schema(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded with JSON_HEX_TAG.
	}

	/**
	 * Fallback schema markup (empty string when not applicable).
	 */
	public function fallback_schema(): string {
		if ( 'fallback' !== $this->environment()->schema_mode() ) {
			return '';
		}
		$reviews = $this->eligible_reviews();
		if ( ! $reviews ) {
			return '';
		}
		$json = ( new SchemaBuilder( $this->canonical() ) )->json_ld( $reviews );
		return '' === $json ? '' : '<script type="application/ld+json" data-rc-schema>' . $json . '</script>' . "\n";
	}

	/**
	 * `wpseo_schema_graph_pieces`: add our piece (Yoast compatible and API available).
	 *
	 * @param array<int, mixed> $pieces  Pieces.
	 * @param mixed             $context Yoast Meta_Tags_Context.
	 * @return array<int, mixed>
	 */
	public function add_yoast_piece( $pieces, $context = null ): array {
		$pieces = is_array( $pieces ) ? $pieces : array();
		if ( 'yoast' !== $this->environment()->schema_mode() || ! class_exists( self::YOAST_PIECE_CLASS ) ) {
			return $pieces;
		}
		$this->prime_from_main_content();
		$pieces[] = new YoastReviewPiece(
			fn( ?string $canonical, ?string $webpage_id ): array => $this->nodes( $canonical, $webpage_id )
		);
		return $pieces;
	}

	/**
	 * `wpseo_schema_graph` (array API) – used only when the piece class is unavailable.
	 *
	 * @param array<int, mixed> $graph   Graph nodes.
	 * @param mixed             $context Yoast context.
	 * @return array<int, mixed>
	 */
	public function filter_yoast_graph( $graph, $context = null ): array {
		$graph = is_array( $graph ) ? $graph : array();
		if ( 'yoast' !== $this->environment()->schema_mode() || class_exists( self::YOAST_PIECE_CLASS ) ) {
			return $graph;
		}
		$this->prime_from_main_content();
		$webpage_id = null;
		foreach ( $graph as $node ) {
			if ( ! is_array( $node ) || ! isset( $node['@id'], $node['@type'] ) ) {
				continue;
			}
			if ( in_array( 'WebPage', (array) $node['@type'], true ) ) {
				$webpage_id = (string) $node['@id'];
				break;
			}
		}
		$canonical = is_object( $context ) && isset( $context->canonical ) && is_string( $context->canonical ) ? $context->canonical : null;
		return array_merge( $graph, $this->nodes( $canonical, $webpage_id ) );
	}

	/**
	 * Build nodes for eligible reviews.
	 *
	 * @param string|null $canonical  Canonical from the schema owner.
	 * @param string|null $webpage_id WebPage @id.
	 * @return list<array<string, mixed>>
	 */
	public function nodes( ?string $canonical = null, ?string $webpage_id = null ): array {
		$reviews = $this->eligible_reviews();
		if ( ! $reviews ) {
			return array();
		}
		return ( new SchemaBuilder( $canonical ?? $this->canonical(), $webpage_id ) )->nodes( $reviews );
	}

	/**
	 * Yoast builds its graph in wp_head, before content is rendered. To keep E5 we predict the
	 * reviews that the main post content will render, using the exact same query as the
	 * renderer. Widgets outside the main content therefore never get markup via Yoast.
	 *
	 * @param string|null $content Content to scan (null = queried singular post).
	 */
	public function prime_from_main_content( ?string $content = null ): void {
		if ( $this->primed ) {
			return;
		}
		$this->primed = true;
		if ( null === $content ) {
			if ( ! is_singular() ) {
				return;
			}
			$post = get_post();
			if ( ! $post || ! isset( $post->post_content ) || ( function_exists( 'post_password_required' ) && post_password_required( $post ) ) ) {
				return;
			}
			$content = (string) $post->post_content;
		}
		if ( ! $this->plugin->has( 'renderer' ) ) {
			return;
		}
		/** @var Renderer $renderer */
		$renderer = $this->plugin->get( 'renderer' );
		if ( $renderer->is_preview() ) {
			return;
		}
		foreach ( ContentScanner::review_definitions( $content ) as $def ) {
			if ( ! $def->compatible ) {
				continue;
			}
			foreach ( $renderer->select_reviews( $def, $def->paginate ? Renderer::current_page() : 1 ) as $review ) {
				$this->visible->add( $review, 'predicted', true );
			}
		}
	}

	/**
	 * Canonical URL of the current page (read only; never output as tag).
	 */
	private function canonical(): string {
		if ( function_exists( 'wp_get_canonical_url' ) && is_singular() ) {
			$url = wp_get_canonical_url();
			if ( is_string( $url ) && '' !== $url ) {
				return $url;
			}
		}
		$url = strtok( Renderer::current_url(), '?' );
		return is_string( $url ) && '' !== $url ? $url : home_url( '/' );
	}
}
