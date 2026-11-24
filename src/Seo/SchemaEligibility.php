<?php
/**
 * Schema eligibility – 1:1 implementation of docs/seo/schema-eligibility.md (E1–E8).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Seo;

use ReviewCaptain\Domain\Kind;
use ReviewCaptain\Domain\Platform;
use ReviewCaptain\Domain\Review;
use ReviewCaptain\Domain\ReviewStatus;

/**
 * Decides per review whether Review markup may be emitted. The first failing rule wins.
 */
final class SchemaEligibility {

	public const FILTER = 'review_captain_schema_eligible';

	/**
	 * Context types eligible for markup (E4). NULL/empty = review about the business → self-serving.
	 */
	public const ELIGIBLE_CONTEXTS = array( 'product', 'post', 'event' );

	/**
	 * Reason keys (diagnostics).
	 */
	public const REASONS = array(
		'eligible',
		'external_platform',
		'not_review',
		'not_approved',
		'incentivised',
		'self_serving',
		'context_not_supported',
		'context_disabled',
		'not_rendered',
		'woo_product_schema',
		'foreign_schema_owner',
		'yoast_incompatible',
		'filtered',
		'below_minimum',
	);

	/**
	 * Context types enabled by `display.schema_contexts` (SET-RC-23). Only narrows ELIGIBLE_CONTEXTS.
	 *
	 * @param mixed $setting Stored setting value.
	 * @return list<string>
	 */
	public static function enabled_contexts( mixed $setting ): array {
		return array_values( array_intersect( self::ELIGIBLE_CONTEXTS, array_map( 'strval', (array) $setting ) ) );
	}

	/**
	 * Evaluate one review.
	 *
	 * Context keys:
	 * - `rendered` (bool): review is server-side rendered in this request's HTML (E5). If absent,
	 *   `visible` (VisibleReviews) is consulted; missing both → not rendered.
	 * - `preview` (bool): editor/REST/preview request → never rendered (E5).
	 * - `environment` (SeoEnvironment): detected plugins (E6, E7). Missing → live detection.
	 * - `enabled` (bool): admin setting "Review-Markup ausgeben" (acts like E8, default true).
	 * - `contexts` (list<string>): context types enabled in the settings (`display.schema_contexts`);
	 *   missing = all of ELIGIBLE_CONTEXTS. Can only narrow.
	 *
	 * @param Review               $r   Review.
	 * @param array<string, mixed> $ctx Context.
	 * @return array{eligible: bool, reason: string}
	 */
	public static function evaluate( Review $r, array $ctx = array() ): array {
		// E1 – platform.
		if ( Platform::LOCAL !== $r->platform ) {
			return self::no( 'external_platform' );
		}
		// E2 – kind.
		if ( Kind::REVIEW !== $r->kind ) {
			return self::no( 'not_review' );
		}
		// E3 – status.
		if ( ReviewStatus::APPROVED !== $r->status ) {
			return self::no( 'not_approved' );
		}
		// E3b – received in exchange for a benefit (SET-RC-12): shown with its label, never marked up.
		if ( $r->incentivised ) {
			return self::no( 'incentivised' );
		}
		// E4 – context. Organization/LocalBusiness reviews never get markup; there is no switch for it.
		if ( null === $r->context_type || '' === $r->context_type || 'organization' === $r->context_type ) {
			return self::no( 'self_serving' );
		}
		if ( ! in_array( $r->context_type, self::ELIGIBLE_CONTEXTS, true ) ) {
			return self::no( 'context_not_supported' );
		}
		if ( array_key_exists( 'contexts', $ctx ) && ! in_array( $r->context_type, (array) $ctx['contexts'], true ) ) {
			return self::no( 'context_disabled' );
		}
		// E5 – visibility.
		if ( ! empty( $ctx['preview'] ) || ! self::is_rendered( $r, $ctx ) ) {
			return self::no( 'not_rendered' );
		}
		// E5b – main entity (QA F-09): markup only on the single page of the reviewed item, so the
		// Product/Event node never borrows the @id of an overview or another page. Callers that do not
		// pass 'main_object_id' (tests, Yoast graph with its own check) are not restricted here.
		if ( array_key_exists( 'main_object_id', $ctx ) ) {
			$main = (string) ( $ctx['main_object_id'] ?? '' );
			if ( '' === $main || (string) $r->context_id !== $main ) {
				return self::no( 'not_main_entity' );
			}
		}
		$env = isset( $ctx['environment'] ) && $ctx['environment'] instanceof SeoEnvironment ? $ctx['environment'] : SeoEnvironment::current();
		// E6 – WooCommerce owns Product schema.
		if ( 'product' === $r->context_type && $env->is_woo_product( $r->context_id ) && $env->woo_schema_active() ) {
			return self::no( 'woo_product_schema' );
		}
		// E7 – schema owner.
		if ( $env->has_foreign_schema_owner() ) {
			return self::no( 'foreign_schema_owner' );
		}
		if ( $env->yoast_active() && ! $env->yoast_compatible() ) {
			return self::no( 'yoast_incompatible' );
		}
		// E8 – filter (only ever tightens) + admin switch.
		if ( array_key_exists( 'enabled', $ctx ) && true !== $ctx['enabled'] ) {
			return self::no( 'filtered' );
		}
		/**
		 * Tighten schema eligibility. Called only for reviews that passed E1–E7; any value other
		 * than strict `true` blocks markup. Cannot unlock reviews blocked earlier.
		 *
		 * @param bool                 $eligible Always true here.
		 * @param Review               $r        Review.
		 * @param array<string, mixed> $ctx      Context.
		 */
		if ( true !== apply_filters( self::FILTER, true, $r, $ctx ) ) {
			return self::no( 'filtered' );
		}
		return array(
			'eligible' => true,
			'reason'   => 'eligible',
		);
	}

	/**
	 * Convenience boolean.
	 *
	 * @param Review               $r   Review.
	 * @param array<string, mixed> $ctx Context.
	 */
	public static function is_eligible( Review $r, array $ctx = array() ): bool {
		return self::evaluate( $r, $ctx )['eligible'];
	}

	/**
	 * Human readable reason (diagnostics).
	 *
	 * @param string $reason Reason key.
	 */
	public static function reason_label( string $reason ): string {
		return match ( $reason ) {
			'eligible'              => __( 'Geeignet für Bewertungs-Markup.', 'review-captain' ),
			'external_platform'     => __( 'Externe Plattformbewertung – wird angezeigt, aber nie ausgezeichnet.', 'review-captain' ),
			'not_review'            => __( 'Erfahrungsbericht (Testimonial) – kein Bewertungs-Markup.', 'review-captain' ),
			'not_approved'          => __( 'Nicht freigegeben.', 'review-captain' ),
			'incentivised'          => __( 'Gegen einen Vorteil erhalten – wird mit Kennzeichen angezeigt, aber nie ausgezeichnet.', 'review-captain' ),
			'self_serving'          => __( 'Bewertung über das eigene Unternehmen – selbstbezogen, kein Markup.', 'review-captain' ),
			'context_not_supported' => __( 'Kontext wird für Bewertungs-Markup nicht unterstützt.', 'review-captain' ),
			'context_disabled'      => __( 'Markup für diesen Inhaltstyp ist in den Einstellungen abgeschaltet.', 'review-captain' ),
			'below_minimum'         => __( 'Weniger geeignete Bewertungen auf der Seite als die eingestellte Mindestzahl.', 'review-captain' ),
			'not_rendered'          => __( 'Nicht sichtbar auf der Seite gerendert.', 'review-captain' ),
			'woo_product_schema'    => __( 'WooCommerce besitzt das Produkt-Schema.', 'review-captain' ),
			'foreign_schema_owner'  => __( 'Ein anderes SEO-/Schema-Plugin verantwortet strukturierte Daten.', 'review-captain' ),
			'yoast_incompatible'    => __( 'Yoast-Version nicht kompatibel – Reviews sichtbar, Markup gesperrt.', 'review-captain' ),
			'filtered'              => __( 'Durch Einstellung oder Filter gesperrt.', 'review-captain' ),
			default                 => $reason,
		};
	}

	/**
	 * E5 helper.
	 *
	 * @param Review               $r   Review.
	 * @param array<string, mixed> $ctx Context.
	 */
	private static function is_rendered( Review $r, array $ctx ): bool {
		if ( array_key_exists( 'rendered', $ctx ) ) {
			return true === $ctx['rendered'];
		}
		if ( isset( $ctx['visible'] ) && $ctx['visible'] instanceof VisibleReviews ) {
			return $ctx['visible']->has( $r );
		}
		return false;
	}

	/**
	 * Negative result.
	 *
	 * @param string $reason Reason.
	 * @return array{eligible: bool, reason: string}
	 */
	private static function no( string $reason ): array {
		return array(
			'eligible' => false,
			'reason'   => $reason,
		);
	}
}
