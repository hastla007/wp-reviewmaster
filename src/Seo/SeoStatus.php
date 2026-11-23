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
					? sprintf( __( 'Erkannt: Version %1$s%2$s – kompatibel.', 'review-captain' ), (string) $env->yoast_version(), $env->yoast_premium() ? ' (Premium)' : '' )
					/* translators: 1: Yoast version, 2: minimum version */
					: sprintf( __( 'Erkannt: Version %1$s – nicht kompatibel (mindestens %2$s). Reviews bleiben sichtbar, Markup ist gesperrt.', 'review-captain' ), (string) $env->yoast_version(), SeoEnvironment::YOAST_MIN_VERSION )
			);
		} else {
			$items[] = self::item( __( 'Yoast SEO', 'review-captain' ), 'info', __( 'Nicht erkannt.', 'review-captain' ) );
		}

		$items[] = match ( $mode ) {
			'yoast'    => self::item( __( 'Schema-Ausgabe', 'review-captain' ), 'ok', __( 'Yoast-Graph-Integration aktiv: Bewertungs-Knoten werden an Yoasts Graph angehängt, kein eigenes JSON-LD.', 'review-captain' ) ),
			'fallback' => self::item( __( 'Schema-Ausgabe', 'review-captain' ), 'info', __( 'Kein SEO-Plugin erkannt: Fallback-JSON-LD für geeignete, sichtbare Bewertungen aktiv.', 'review-captain' ) ),
			default    => self::item( __( 'Schema-Ausgabe', 'review-captain' ), 'warn', self::blocked_reason( $env ) ),
		};

		foreach ( $env->foreign_schema_owners() as $owner ) {
			$items[] = self::item(
				__( 'Konflikt', 'review-captain' ),
				'warn',
				/* translators: %s: plugin name */
				sprintf( __( '%s verantwortet strukturierte Daten. Review Captain gibt deshalb kein Bewertungs-Markup aus; andere Plugins werden nicht verändert.', 'review-captain' ), $owner )
			);
		}

		if ( $env->woo_schema_active() ) {
			$items[] = self::item(
				__( 'WooCommerce', 'review-captain' ),
				'info',
				$env->yoast_woo()
					? __( 'Produkt-Schema durch Yoast WooCommerce SEO – Bewertungen zu WooCommerce-Produkten erhalten kein zusätzliches Markup.', 'review-captain' )
					: __( 'WooCommerce besitzt das Produkt-Schema – Bewertungen zu WooCommerce-Produkten erhalten kein zusätzliches Markup.', 'review-captain' )
			);
		}

		if ( false === $plugin->settings()->get( 'display', 'schema_enabled', true ) ) {
			$items[] = self::item( __( 'Einstellung', 'review-captain' ), 'warn', __( '„Review-Markup ausgeben“ ist deaktiviert.', 'review-captain' ) );
		}

		// SET-RC-23: narrowed contexts and minimum number – say why nothing may be output.
		$contexts = SchemaEligibility::enabled_contexts( $plugin->settings()->get( 'display', 'schema_contexts', SchemaEligibility::ELIGIBLE_CONTEXTS ) );
		if ( ! $contexts ) {
			$items[] = self::item( __( 'Inhaltstypen', 'review-captain' ), 'warn', __( 'Für keinen Inhaltstyp ist Markup eingeschaltet – es werden keine strukturierten Daten ausgegeben.', 'review-captain' ) );
		} elseif ( count( $contexts ) < count( SchemaEligibility::ELIGIBLE_CONTEXTS ) ) {
			$labels  = array(
				'product' => __( 'Produkte', 'review-captain' ),
				'post'    => __( 'Beiträge', 'review-captain' ),
				'event'   => __( 'Veranstaltungen', 'review-captain' ),
			);
			$items[] = self::item(
				__( 'Inhaltstypen', 'review-captain' ),
				'info',
				/* translators: %s: list of content types */
				sprintf( __( 'Markup nur für: %s. Bewertungen zu anderen Inhalten erhalten kein Markup.', 'review-captain' ), implode( ', ', array_map( static fn( string $c ): string => $labels[ $c ] ?? $c, $contexts ) ) )
			);
		}
		$min = SeoModule::min_reviews( $plugin->settings() );
		if ( $min > 1 ) {
			$items[] = self::item(
				__( 'Mindestzahl', 'review-captain' ),
				'info',
				/* translators: %d: minimum number of reviews */
				sprintf( _n( 'Markup erst ab %d geeigneten Bewertung auf der Seite.', 'Markup erst ab %d geeigneten Bewertungen auf der Seite; darunter wird nichts ausgegeben.', $min, 'review-captain' ), $min )
			);
		}
		$items[] = self::item( __( 'Erfahrungsberichte', 'review-captain' ), 'info', __( 'Erfahrungsberichte der Website (Testimonials) erhalten nie Bewertungs-Markup.', 'review-captain' ) );

		$items[] = self::item( __( 'Externe Bewertungen', 'review-captain' ), 'info', __( 'Bewertungen von Google, Trustpilot, Trusted Shops, App Store, Google Play und anderen Plattformen werden angezeigt, aber nie ausgezeichnet.', 'review-captain' ) );
		$items[] = self::item( __( 'Unternehmensbewertungen', 'review-captain' ), 'info', __( 'Bewertungen ohne Produkt-, Beitrags- oder Event-Bezug gelten als selbstbezogen und erhalten kein Markup.', 'review-captain' ) );
		$items[] = self::item( __( 'Gesamtbewertung', 'review-captain' ), 'info', __( 'Es wird kein AggregateRating ausgegeben (Entscheidung offen).', 'review-captain' ) );
		$items[] = self::item( __( 'Indexierung', 'review-captain' ), 'info', __( 'Seiten mit Review-Captain-Parametern (z. B. rc_page) erhalten noindex, follow. Titel, Beschreibung, Canonical und Sitemaps bleiben unverändert.', 'review-captain' ) );
		$items[] = self::item( __( 'Hinweis', 'review-captain' ), 'info', __( 'Strukturierte Daten ermöglichen Rich Results, garantieren sie aber nicht.', 'review-captain' ) );

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
