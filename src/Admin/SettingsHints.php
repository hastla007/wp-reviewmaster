<?php
/**
 * Grey hint lines for add-on settings (SET-RC-06, owner decision S1/S5 of 23.11.2026).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Admin;

use ReviewCaptain\Settings\Settings;
use ReviewCaptain\Settings\SettingsSchema;

/**
 * One quiet text line per function of Review Captain Pro that is on sale, at the end of the
 * matching settings tab: no input, no lock, no function code, no license check. A line disappears
 * when a settings field (any owner) declares 'replaces_hint' => <id>, or when the operator switches
 * off general.show_pro_hints.
 *
 * Data shape (shared suite contract, provisional): {id, tab, title, text, link}.
 */
final class SettingsHints {

	/**
	 * Ids of the core hint lines. Add-ons name them in 'replaces_hint' or remove them from the filter.
	 */
	public const REQUESTS    = 'requests.invitations';
	public const SENDER      = 'requests.sender';
	public const SEND_WINDOW = 'requests.send_window';
	public const LIMITS      = 'requests.limits';
	public const REMINDERS   = 'requests.reminders';
	public const PLATFORM    = 'sources.platform_import';
	public const WIDGETS     = 'display.advanced_widgets';
	public const REPORTS     = 'general.reports';

	/**
	 * Whether hints (and the dashboard info) are shown: setting general.show_pro_hints.
	 *
	 * @param Settings $settings Settings.
	 */
	public static function enabled( Settings $settings ): bool {
		return false !== $settings->get( 'general', 'show_pro_hints', true );
	}

	/**
	 * Core hint lines: only functions that are sold today (S5). Texts in du-form.
	 *
	 * @return list<array{id: string, tab: string, title: string, text: string, link: string}>
	 */
	public static function defaults(): array {
		$lines = array(
			array( self::REPORTS, 'general', __( 'Auswertungen', 'review-captain' ), __( 'Auswertungen zu Bewertungen, Quellen und Einladungen. Verfügbar mit Review Captain Pro.', 'review-captain' ) ),
			array( self::PLATFORM, 'sources', __( 'Automatischer Abgleich', 'review-captain' ), __( 'Bewertungen von Google, Trustpilot und anderen Plattformen automatisch übernehmen. Verfügbar mit Review Captain Pro.', 'review-captain' ) ),
			array( self::REQUESTS, 'requests', __( 'Bewertungs-Einladungen', 'review-captain' ), __( 'Bewertungs-Einladungen automatisch nach Kauf oder Besuch senden. Verfügbar mit Review Captain Pro.', 'review-captain' ) ),
			array( self::SENDER, 'requests', __( 'Absender', 'review-captain' ), __( 'Absendername und -adresse der Einladungen. Verfügbar mit Review Captain Pro.', 'review-captain' ) ),
			array( self::SEND_WINDOW, 'requests', __( 'Versandzeiten', 'review-captain' ), __( 'Einladungen nur zu bestimmten Tageszeiten senden. Verfügbar mit Review Captain Pro.', 'review-captain' ) ),
			array( self::LIMITS, 'requests', __( 'Grenzen', 'review-captain' ), __( 'Tageslimit und Mindestabstand je Empfänger. Verfügbar mit Review Captain Pro.', 'review-captain' ) ),
			array( self::REMINDERS, 'requests', __( 'Erinnerungen', 'review-captain' ), __( 'Eine Erinnerung für unbeantwortete Einladungen senden. Verfügbar mit Review Captain Pro.', 'review-captain' ) ),
			array( self::WIDGETS, 'display', __( 'Weitere Widgets', 'review-captain' ), __( 'Karussell, Plattformleiste und schwebendes Badge. Verfügbar mit Review Captain Pro.', 'review-captain' ) ),
		);
		$out   = array();
		foreach ( $lines as [ $id, $tab, $title, $text ] ) {
			/**
			 * Link of a core hint line (e.g. to the shop). Default empty: no link.
			 *
			 * @param string $link Default ''.
			 * @param string $id   Hint id.
			 */
			$link  = apply_filters( 'review_captain_settings_hint_link', '', $id );
			$out[] = array(
				'id'    => $id,
				'tab'   => $tab,
				'title' => $title,
				'text'  => $text,
				'link'  => is_string( $link ) ? $link : '',
			);
		}
		return $out;
	}

	/**
	 * All hint lines after the filter, validated: unique id, known tab, non-empty title and text,
	 * link empty or http(s).
	 *
	 * @return list<array{id: string, tab: string, title: string, text: string, link: string}>
	 */
	public static function all(): array {
		/**
		 * Filter the hint lines of the settings page.
		 *
		 * @param list<array{id: string, tab: string, title: string, text: string, link: string}> $hints Hint lines.
		 */
		$hints = apply_filters( 'review_captain_settings_hints', self::defaults() );
		if ( ! is_array( $hints ) ) {
			return array();
		}
		$tabs = SettingsSchema::tabs();
		$out  = array();
		$seen = array();
		foreach ( $hints as $hint ) {
			if ( ! is_array( $hint ) ) {
				continue;
			}
			$id    = is_string( $hint['id'] ?? null ) ? $hint['id'] : '';
			$tab   = is_string( $hint['tab'] ?? null ) ? $hint['tab'] : '';
			$title = is_string( $hint['title'] ?? null ) ? trim( $hint['title'] ) : '';
			$text  = is_string( $hint['text'] ?? null ) ? trim( $hint['text'] ) : '';
			$link  = is_string( $hint['link'] ?? null ) ? trim( $hint['link'] ) : '';
			if ( ! preg_match( '/^[a-z0-9_.-]{1,64}$/', $id ) || isset( $seen[ $id ] ) || ! isset( $tabs[ $tab ] ) || '' === $title || '' === $text ) {
				continue;
			}
			if ( '' !== $link && ! preg_match( '#^https?://#i', $link ) ) {
				$link = '';
			}
			$seen[ $id ] = true;
			$out[]       = array(
				'id'    => $id,
				'tab'   => $tab,
				'title' => $title,
				'text'  => $text,
				'link'  => $link,
			);
		}
		return $out;
	}

	/**
	 * Hint ids replaced by a settings field ('replaces_hint' => id or list of ids).
	 *
	 * @param array<string, array<string, array<string, mixed>>> $schema Schema (SettingsSchema::fields()).
	 * @return list<string>
	 */
	public static function replaced( array $schema ): array {
		$out = array();
		foreach ( $schema as $fields ) {
			foreach ( (array) $fields as $field ) {
				foreach ( (array) ( is_array( $field ) ? ( $field['replaces_hint'] ?? array() ) : array() ) as $id ) {
					if ( is_string( $id ) && '' !== $id ) {
						$out[ $id ] = true;
					}
				}
			}
		}
		return array_keys( $out );
	}

	/**
	 * Hint lines still to show in a tab (switch on, not replaced by a field).
	 *
	 * @param string   $tab      Tab.
	 * @param Settings $settings Settings.
	 * @return list<array{id: string, tab: string, title: string, text: string, link: string}>
	 */
	public static function for_tab( string $tab, Settings $settings ): array {
		if ( ! self::enabled( $settings ) ) {
			return array();
		}
		$replaced = self::replaced( SettingsSchema::fields() );
		return array_values( array_filter( self::all(), static fn( array $hint ): bool => $tab === $hint['tab'] && ! in_array( $hint['id'], $replaced, true ) ) );
	}

	/**
	 * Quiet grey list of hint lines (no input, no lock, no warning colour; at most one link each).
	 *
	 * @param list<array{id: string, tab: string, title: string, text: string, link: string}> $hints Hint lines.
	 */
	public static function render( array $hints ): string {
		if ( ! $hints ) {
			return '';
		}
		$html = '<ul class="rc-admin-hints" aria-label="' . esc_attr__( 'Weitere Funktionen in Review Captain Pro', 'review-captain' ) . '">';
		foreach ( $hints as $hint ) {
			$html .= '<li class="rc-admin-hint" data-hint="' . esc_attr( $hint['id'] ) . '"><span class="rc-admin-hint-badge">' . esc_html__( 'Pro', 'review-captain' ) . '</span> <strong>' . esc_html( $hint['title'] ) . ':</strong> ' . esc_html( $hint['text'] );
			if ( '' !== $hint['link'] ) {
				/* translators: %s: title of the hint line */
				$html .= ' <a href="' . esc_url( $hint['link'] ) . '" aria-label="' . esc_attr( sprintf( __( 'Mehr erfahren: %s', 'review-captain' ), $hint['title'] ) ) . '">' . esc_html__( 'Mehr erfahren', 'review-captain' ) . '</a>';
			}
			$html .= '</li>';
		}
		return $html . '</ul>';
	}
}
