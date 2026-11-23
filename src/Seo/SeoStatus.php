<?php
/**
 * SEO diagnostics for "Einstellungen → SEO & Yoast".
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Seo;

use ReviewCaptain\Plugin;

/**
 * Read-only status report. Called by the admin module; does not change anything.
 */
final class SeoStatus {

	/**
	 * Build the report.
	 *
	 * @param SeoEnvironment|null $env Environment (null = detect).
	 * @return list<array{label: string, status: string, message: string}>
	 */
	public static function report( ?SeoEnvironment $env = null ): array {
		$env    = $env ?? SeoEnvironment::current();
		$items  = array();
		$mode   = $env->schema_mode();
		$plugin = Plugin::instance();

		if ( $env->yoast_active() ) {
			$items[] = self::item(
				__( 'Yoast SEO', 'review-captain' ),
				$env->yoast_compatible() ? 'ok' : 'warn',
				$env->yoast_compatible()
					/* translators: 1: Yoast version, 2: edition */
					? sprintf( __( 'Detected: version %1$s%2$s – compatible.', 'review-captain' ), (string) $env->yoast_version(), $env->yoast_premium() ? ' (Premium)' : '' )
					/* translators: 1: Yoast version, 2: minimum version */
					: sprintf( __( 'Detected: version %1$s – not compatible (minimum %2$s). Reviews remain visible, markup is blocked.', 'review-captain' ), (string) $env->yoast_version(), SeoEnvironment::YOAST_MIN_VERSION )
			);
		} else {
			$items[] = self::item( __( 'Yoast SEO', 'review-captain' ), 'info', __( 'Not detected.', 'review-captain' ) );
		}

		$items[] = match ( $mode ) {
			'yoast'    => self::item( __( 'Schema output', 'review-captain' ), 'ok', __( 'Yoast graph integration active: review nodes are attached to Yoast\'s graph, no separate JSON-LD.', 'review-captain' ) ),
			'fallback' => self::item( __( 'Schema output', 'review-captain' ), 'info', __( 'No SEO plugin detected: fallback JSON-LD for eligible, visible reviews is active.', 'review-captain' ) ),
			default    => self::item( __( 'Schema output', 'review-captain' ), 'warn', self::blocked_reason( $env ) ),
		};

		foreach ( $env->foreign_schema_owners() as $owner ) {
			$items[] = self::item(
				__( 'Conflict', 'review-captain' ),
				'warn',
				/* translators: %s: plugin name */
				sprintf( __( '%s is responsible for structured data. Review Captain therefore does not output review markup; other plugins are not modified.', 'review-captain' ), $owner )
			);
		}

		if ( $env->woo_schema_active() ) {
			$items[] = self::item(
				__( 'WooCommerce', 'review-captain' ),
				'info',
				$env->yoast_woo()
					? __( 'Product schema by Yoast WooCommerce SEO – reviews of WooCommerce products receive no additional markup.', 'review-captain' )
					: __( 'WooCommerce owns the product schema – reviews of WooCommerce products receive no additional markup.', 'review-captain' )
			);
		}

		if ( false === $plugin->settings()->get( 'display', 'schema_enabled', true ) ) {
			$items[] = self::item( __( 'Setting', 'review-captain' ), 'warn', __( '“Output review markup” is disabled.', 'review-captain' ) );
		}

		// SET-RC-23: narrowed contexts and minimum number – say why nothing may be output.
		$contexts = SchemaEligibility::enabled_contexts( $plugin->settings()->get( 'display', 'schema_contexts', SchemaEligibility::ELIGIBLE_CONTEXTS ) );
		if ( ! $contexts ) {
			$items[] = self::item( __( 'Content types', 'review-captain' ), 'warn', __( 'Markup is switched on for no content type – no structured data is output.', 'review-captain' ) );
		} elseif ( count( $contexts ) < count( SchemaEligibility::ELIGIBLE_CONTEXTS ) ) {
			$labels  = array(
				'product' => __( 'Products', 'review-captain' ),
				'post'    => __( 'Posts', 'review-captain' ),
				'event'   => __( 'Events', 'review-captain' ),
			);
			$items[] = self::item(
				__( 'Content types', 'review-captain' ),
				'info',
				/* translators: %s: list of content types */
				sprintf( __( 'Markup only for: %s. Reviews of other content get no markup.', 'review-captain' ), implode( ', ', array_map( static fn( string $c ): string => $labels[ $c ] ?? $c, $contexts ) ) )
			);
		}
		$min = SeoModule::min_reviews( $plugin->settings() );
		if ( $min > 1 ) {
			$items[] = self::item(
				__( 'Minimum number', 'review-captain' ),
				'info',
				/* translators: %d: minimum number of reviews */
				sprintf( _n( 'Markup only from %d eligible review on the page.', 'Markup only from %d eligible reviews on the page; below that nothing is output.', $min, 'review-captain' ), $min )
			);
		}
		$items[] = self::item( __( 'Testimonials', 'review-captain' ), 'info', __( 'Testimonials of the site never get review markup.', 'review-captain' ) );

		$items[] = self::item( __( 'External reviews', 'review-captain' ), 'info', __( 'Reviews from Google, Trustpilot, Trusted Shops, App Store, Google Play, and other platforms are displayed but never marked up.', 'review-captain' ) );
		$items[] = self::item( __( 'Business reviews', 'review-captain' ), 'info', __( 'Reviews not related to a product, post, or event are considered self-serving and receive no markup.', 'review-captain' ) );
		$items[] = self::item( __( 'Overall rating', 'review-captain' ), 'info', __( 'No AggregateRating is output (decision pending).', 'review-captain' ) );
		$items[] = self::item( __( 'Indexing', 'review-captain' ), 'info', __( 'Pages with Review Captain parameters (e.g. rc_page) receive noindex, follow. Title, description, canonical, and sitemaps remain unchanged.', 'review-captain' ) );
		$items[] = self::item( __( 'Note', 'review-captain' ), 'info', __( 'Structured data enables rich results but does not guarantee them.', 'review-captain' ) );

		return $items;
	}

	/**
	 * Why markup is blocked.
	 *
	 * @param SeoEnvironment $env Environment.
	 */
	private static function blocked_reason( SeoEnvironment $env ): string {
		if ( $env->has_foreign_schema_owner() ) {
			return SchemaEligibility::reason_label( 'foreign_schema_owner' );
		}
		return SchemaEligibility::reason_label( 'yoast_incompatible' );
	}

	/**
	 * Item helper.
	 *
	 * @param string $label   Label.
	 * @param string $status  ok|warn|info.
	 * @param string $message Message.
	 * @return array{label: string, status: string, message: string}
	 */
	private static function item( string $label, string $status, string $message ): array {
		return array(
			'label'   => $label,
			'status'  => $status,
			'message' => $message,
		);
	}
}
