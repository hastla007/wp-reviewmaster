<?php
/**
 * Detection of SEO/schema plugins and WooCommerce (SEO-005, SEO-008).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Seo;

/**
 * Detects the environment without hard class dependencies (constants, function_exists,
 * class_exists without autoload). Never queries foreign tables.
 *
 * Tests (and diagnostics) can construct a fixed state via from_state().
 */
final class SeoEnvironment {

	/**
	 * Minimum supported Yoast SEO version for the graph integration.
	 *
	 * Entscheidung der Inhaberin 16.11.2026 (SEO-001/SEO-OPEN-1): aktuelle Yoast-Major minus 2.
	 * Stand 16.11.2026: aktuell 27.x → Minimum 25.0. Beim Release-Freeze neu setzen (RC-141).
	 */
	public const YOAST_MIN_VERSION = '25.0';

	/**
	 * Detected state.
	 *
	 * @var array{yoast_version: ?string, yoast_premium: bool, yoast_woo: bool, woocommerce: bool, foreign: list<string>, woo_product_ids: ?list<string>}
	 */
	private array $state;

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed>|null $state Fixed state (null = detect).
	 */
	private function __construct( ?array $state = null ) {
		$this->state = null === $state ? self::detect() : self::normalize_state( $state );
	}

	/**
	 * Detect the live environment.
	 */
	public static function current(): self {
		return new self();
	}

	/**
	 * Fixed environment (tests, diagnostics simulation).
	 *
	 * Keys: yoast_version (?string), yoast_premium (bool), yoast_woo (bool), woocommerce (bool),
	 * foreign (list of plugin labels owning schema output), woo_product_ids (optional fixed list of
	 * product post IDs instead of get_post_type()).
	 *
	 * @param array<string, mixed> $state State.
	 */
	public static function from_state( array $state ): self {
		return new self( $state );
	}

	/**
	 * Yoast SEO version or null.
	 */
	public function yoast_version(): ?string {
		return $this->state['yoast_version'];
	}

	/**
	 * Whether Yoast SEO is active.
	 */
	public function yoast_active(): bool {
		return null !== $this->state['yoast_version'];
	}

	/**
	 * Whether the active Yoast version is compatible. Unknown/unparsable version → false.
	 */
	public function yoast_compatible(): bool {
		$v = $this->state['yoast_version'];
		if ( null === $v || ! preg_match( '/^\d+(\.\d+)*/', $v ) ) {
			return false;
		}
		return version_compare( $v, self::YOAST_MIN_VERSION, '>=' );
	}

	/**
	 * Whether Yoast SEO Premium is active.
	 */
	public function yoast_premium(): bool {
		return $this->state['yoast_premium'];
	}

	/**
	 * Whether Yoast WooCommerce SEO is active.
	 */
	public function yoast_woo(): bool {
		return $this->state['yoast_woo'];
	}

	/**
	 * Whether WooCommerce is active.
	 */
	public function woocommerce_active(): bool {
		return $this->state['woocommerce'];
	}

	/**
	 * Whether WooCommerce (or Yoast WooCommerce SEO) owns Product schema.
	 *
	 * Conservative (SEO-OPEN-9): an active WooCommerce is assumed to output Product schema.
	 */
	public function woo_schema_active(): bool {
		return $this->state['woocommerce'] || $this->state['yoast_woo'];
	}

	/**
	 * Whether a post ID is a WooCommerce product.
	 *
	 * @param string|null $context_id Context ID.
	 */
	public function is_woo_product( ?string $context_id ): bool {
		if ( ! $this->state['woocommerce'] || null === $context_id || ! ctype_digit( $context_id ) ) {
			return false;
		}
		if ( null !== $this->state['woo_product_ids'] ) {
			return in_array( $context_id, $this->state['woo_product_ids'], true );
		}
		return 'product' === get_post_type( (int) $context_id );
	}

	/**
	 * Foreign schema owners (labels). Includes "multiple SEO plugins".
	 *
	 * @return list<string>
	 */
	public function foreign_schema_owners(): array {
		$owners = $this->state['foreign'];
		$seo    = count( array_intersect( $owners, array( 'Rank Math', 'All in One SEO', 'SEOPress' ) ) ) + ( $this->yoast_active() ? 1 : 0 );
		if ( $seo > 1 ) {
			$owners[] = __( 'Mehrere SEO-Plugins', 'review-captain' );
		}
		return $owners;
	}

	/**
	 * Whether another plugin owns the schema output.
	 */
	public function has_foreign_schema_owner(): bool {
		return array() !== $this->state['foreign'];
	}

	/**
	 * Output path for review schema: yoast | fallback | blocked.
	 */
	public function schema_mode(): string {
		if ( $this->has_foreign_schema_owner() ) {
			return 'blocked';
		}
		if ( $this->yoast_active() ) {
			return $this->yoast_compatible() ? 'yoast' : 'blocked';
		}
		return 'fallback';
	}

	/**
	 * Live detection.
	 *
	 * @return array{yoast_version: ?string, yoast_premium: bool, yoast_woo: bool, woocommerce: bool, foreign: list<string>, woo_product_ids: ?list<string>}
	 */
	private static function detect(): array {
		$foreign = array();
		if ( defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath', false ) ) {
			$foreign[] = 'Rank Math';
		}
		if ( defined( 'AIOSEO_VERSION' ) || function_exists( 'aioseo' ) ) {
			$foreign[] = 'All in One SEO';
		}
		if ( defined( 'SEOPRESS_VERSION' ) ) {
			$foreign[] = 'SEOPress';
		}
		// Schema Pro also counts next to Yoast (SEO-OPEN-2, conservative). Markers to verify (SEO-OPEN-4).
		if ( defined( 'BSF_SP_VER' ) || class_exists( 'BSF_AIOSRS_Pro', false ) ) {
			$foreign[] = 'Schema Pro';
		}
		return array(
			'yoast_version'   => defined( 'WPSEO_VERSION' ) ? (string) constant( 'WPSEO_VERSION' ) : null,
			'yoast_premium'   => defined( 'WPSEO_PREMIUM_VERSION' ),
			'yoast_woo'       => defined( 'WPSEO_WOO_VERSION' ) || class_exists( 'Yoast_WooCommerce_SEO', false ),
			'woocommerce'     => defined( 'WC_VERSION' ) || function_exists( 'WC' ),
			'foreign'         => $foreign,
			'woo_product_ids' => null,
		);
	}

	/**
	 * Normalise a fixed state.
	 *
	 * @param array<string, mixed> $s State.
	 * @return array{yoast_version: ?string, yoast_premium: bool, yoast_woo: bool, woocommerce: bool, foreign: list<string>, woo_product_ids: ?list<string>}
	 */
	private static function normalize_state( array $s ): array {
		return array(
			'yoast_version'   => isset( $s['yoast_version'] ) && '' !== $s['yoast_version'] ? (string) $s['yoast_version'] : null,
			'yoast_premium'   => ! empty( $s['yoast_premium'] ),
			'yoast_woo'       => ! empty( $s['yoast_woo'] ),
			'woocommerce'     => ! empty( $s['woocommerce'] ),
			'foreign'         => array_values( array_map( 'strval', (array) ( $s['foreign'] ?? array() ) ) ),
			'woo_product_ids' => isset( $s['woo_product_ids'] ) ? array_values( array_map( 'strval', (array) $s['woo_product_ids'] ) ) : null,
		);
	}
}
