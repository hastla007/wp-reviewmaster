<?php
/**
 * Status notices after redirects (PRG pattern).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Admin;

/**
 * Maps the `rc_notice` query parameter to a translated notice. Only known codes render,
 * so the parameter can never inject text.
 */
final class Notices {

	public const PARAM        = 'rc_notice';
	public const PARAM_FIELDS = 'rc_fields';
	public const PARAM_COUNT  = 'rc_count';

	/**
	 * Add a status code (and optional field list/count) to a redirect URL.
	 *
	 * @param string       $url    URL.
	 * @param string       $code   Code.
	 * @param list<string> $fields Field keys / error codes.
	 * @param int|null     $count  Count.
	 */
	public static function url( string $url, string $code, array $fields = array(), ?int $count = null ): string {
		$args = array( self::PARAM => $code );
		if ( $fields ) {
			$args[ self::PARAM_FIELDS ] = implode( ',', array_map( 'sanitize_key', $fields ) );
		}
		if ( null !== $count ) {
			$args[ self::PARAM_COUNT ] = $count;
		}
		return add_query_arg( $args, $url );
	}

	/**
	 * Known codes.
	 *
	 * @return array<string, array{0: string, 1: string}> code => [type, message]
	 */
	public static function messages(): array {
		return array(
			'saved'                => array( 'success', __( 'Einstellungen gespeichert.', 'review-captain' ) ),
			'saved_invalid'        => array( 'error', __( 'Einige Werte wurden nicht gespeichert, weil sie ungültig sind. Alle anderen Änderungen sind gespeichert. Bitte prüfe:', 'review-captain' ) ),
			'unchanged'            => array( 'info', __( 'Keine Änderungen – es wurde nichts gespeichert.', 'review-captain' ) ),
			'tab_forbidden'        => array( 'error', __( 'Für diesen Bereich fehlt dir die Berechtigung. Es wurde nichts gespeichert.', 'review-captain' ) ),
			'reset_done'           => array( 'success', __( 'Einstellungen wurden auf die Standardwerte zurückgesetzt. Reviews, Profile und Verlauf bleiben unverändert. Der vorherige Stand wurde gesichert.', 'review-captain' ) ),
			'confirm_required'     => array( 'error', __( 'Bitte bestätige die Aktion über das Kontrollkästchen.', 'review-captain' ) ),
			'import_preview'       => array( 'info', __( 'Vorschau erstellt. Prüfe die Änderungen und bestätige den Import.', 'review-captain' ) ),
			'import_invalid'       => array( 'error', __( 'Die Datei konnte nicht importiert werden: kein gültiger Review-Captain-Konfigurationsexport oder nicht unterstützte Version.', 'review-captain' ) ),
			'import_empty'         => array( 'info', __( 'Der Import enthält keine Änderungen gegenüber den aktuellen Einstellungen.', 'review-captain' ) ),
			'import_expired'       => array( 'error', __( 'Die Import-Vorschau ist abgelaufen. Bitte lade die Datei erneut.', 'review-captain' ) ),
			'import_applied'       => array( 'success', __( 'Konfiguration importiert. Der vorherige Stand wurde gesichert und kann wiederhergestellt werden. Zugangsdaten müssen ggf. neu verbunden werden.', 'review-captain' ) ),
			'import_discarded'     => array( 'info', __( 'Import verworfen. Es wurde nichts geändert.', 'review-captain' ) ),
			'rollback_done'        => array( 'success', __( 'Der gesicherte Stand der Einstellungen wurde wiederhergestellt.', 'review-captain' ) ),
			'rollback_missing'     => array( 'error', __( 'Es ist keine Sicherung vorhanden.', 'review-captain' ) ),
			'review_created'       => array( 'success', __( 'Eintrag angelegt.', 'review-captain' ) ),
			'review_saved'         => array( 'success', __( 'Änderungen gespeichert.', 'review-captain' ) ),
			'review_unchanged'     => array( 'info', __( 'Keine Änderungen.', 'review-captain' ) ),
			'review_invalid'       => array( 'error', __( 'Der Eintrag wurde nicht gespeichert. Bitte prüfe:', 'review-captain' ) ),
			'review_missing'       => array( 'error', __( 'Der Eintrag wurde nicht gefunden.', 'review-captain' ) ),
			'review_conflict'      => array( 'error', __( 'Der Eintrag wurde inzwischen von jemand anderem geändert. Deine Änderungen wurden nicht gespeichert – bitte prüfe den aktuellen Stand und speichere erneut.', 'review-captain' ) ),
			'tags_saved'           => array( 'success', __( 'Tags gespeichert.', 'review-captain' ) ),
			'photo_deleted'        => array( 'success', __( 'Foto gelöscht.', 'review-captain' ) ),
			'question_saved'       => array( 'success', __( 'Frage aktualisiert.', 'review-captain' ) ),
			'question_failed'      => array( 'error', __( 'Die Frage wurde nicht geändert (nicht gefunden oder zwischenzeitlich geändert).', 'review-captain' ) ),
			'campaign_saved'       => array( 'success', __( 'Feedback-Link angelegt.', 'review-captain' ) ),
			'campaign_invalid'     => array( 'error', __( 'Der Feedback-Link wurde nicht angelegt. Bitte prüfe:', 'review-captain' ) ),
			'campaign_deleted'     => array( 'success', __( 'Feedback-Link gelöscht.', 'review-captain' ) ),
			'status_changed'       => array( 'success', __( 'Status geändert.', 'review-captain' ) ),
			'status_failed'        => array( 'error', __( 'Dieser Statuswechsel ist nicht erlaubt oder der Eintrag wurde zwischenzeitlich geändert.', 'review-captain' ) ),
			'reason_required'      => array( 'error', __( 'Bitte gib für Ablehnen oder Archivieren einen Grund an.', 'review-captain' ) ),
			'bulk_done'            => array( 'success', __( 'Mehrfachaktion ausgeführt. Geänderte Einträge:', 'review-captain' ) ),
			'bulk_none'            => array( 'warning', __( 'Keine Einträge geändert (nichts ausgewählt oder Statuswechsel nicht erlaubt).', 'review-captain' ) ),
			'profile_saved'        => array( 'success', __( 'Profil gespeichert.', 'review-captain' ) ),
			'profile_invalid'      => array( 'error', __( 'Das Profil wurde nicht gespeichert. Bitte prüfe:', 'review-captain' ) ),
			'profile_deleted'      => array( 'success', __( 'Profil gelöscht. Zugeordnete Reviews bleiben erhalten.', 'review-captain' ) ),
			'checklist_updated'    => array( 'success', __( 'Einrichtungsschritt aktualisiert.', 'review-captain' ) ),
		);
	}

	/**
	 * Human readable labels for error/field codes shown after a notice.
	 *
	 * @param string $code Code.
	 */
	public static function detail_label( string $code ): string {
		$map = array(
			'missing_platform'             => __( 'Plattform fehlt', 'review-captain' ),
			'missing_rating'               => __( 'Originalbewertung fehlt', 'review-captain' ),
			'missing_body'                 => __( 'Text fehlt', 'review-captain' ),
			'missing_date'                 => __( 'Datum fehlt oder ist ungültig', 'review-captain' ),
			'missing_source_url'           => __( 'Profil-/Original-Link fehlt', 'review-captain' ),
			'source_url_not_https'         => __( 'Link muss mit https:// beginnen', 'review-captain' ),
			'missing_source_note'          => __( 'Quellenhinweis fehlt', 'review-captain' ),
			'missing_author'               => __( 'Name fehlt', 'review-captain' ),
			'rating_out_of_scale'          => __( 'Bewertung liegt außerhalb der Skala', 'review-captain' ),
			'invalid_scale'                => __( 'Skala ungültig', 'review-captain' ),
			'empty_review'                 => __( 'Text oder Bewertung fehlt', 'review-captain' ),
			'invalid_context_type'         => __( 'Kontext ungültig', 'review-captain' ),
			'testimonial_must_be_local'    => __( 'Erfahrungsberichte sind immer Website-eigen', 'review-captain' ),
			'external_verification_on_local' => __( 'Verifizierung passt nicht zur Quelle', 'review-captain' ),
			'reply_on_external'            => __( 'Antworten auf Plattformbewertungen sind hier nicht möglich', 'review-captain' ),
			'reply_too_long'               => __( 'Antwort ist zu lang (höchstens 5000 Zeichen)', 'review-captain' ),
			'missing_label'                => __( 'Bezeichnung fehlt', 'review-captain' ),
			'invalid_platform'             => __( 'Plattform ungültig', 'review-captain' ),
			'profile_url_not_https'        => __( 'Profil-URL muss mit https:// beginnen', 'review-captain' ),
			'review_url_not_https'         => __( 'Link „Bewertung abgeben“ muss mit https:// beginnen', 'review-captain' ),
			'missing_url'                  => __( 'Mindestens eine URL angeben', 'review-captain' ),
			'save_failed'                  => __( 'Speichern fehlgeschlagen', 'review-captain' ),
			'reason_required'              => __( 'Grund fehlt', 'review-captain' ),
			'label'                        => __( 'Bezeichnung fehlt oder ist zu lang (höchstens 80 Zeichen)', 'review-captain' ),
			'profile'                      => __( 'Profil nicht gefunden oder ohne https-Bewertungslink', 'review-captain' ),
			'target_url'                   => __( 'Zielseite muss eine https-Adresse dieser Website sein', 'review-captain' ),
			'whatsapp_number'              => __( 'WhatsApp-Nummer: 8–15 Ziffern im internationalen Format ohne führende 0', 'review-captain' ),
			'whatsapp_text'                => __( 'WhatsApp-Text ist zu lang (höchstens 300 Zeichen)', 'review-captain' ),
			'limit'                        => __( 'Höchstzahl an Feedback-Links erreicht', 'review-captain' ),
		);
		if ( isset( $map[ $code ] ) ) {
			return $map[ $code ];
		}
		// Settings fields are passed as "tab-key".
		if ( str_contains( $code, '-' ) ) {
			[ $tab, $key ] = explode( '-', $code, 2 );
			return SettingsFields::label( $tab, $key );
		}
		return $code;
	}

	/**
	 * Render the notice for the current request, if any.
	 */
	public static function render_current(): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only display of a status code.
		$code = isset( $_GET[ self::PARAM ] ) ? sanitize_key( wp_unslash( (string) $_GET[ self::PARAM ] ) ) : '';
		if ( '' === $code ) {
			return '';
		}
		$fields = isset( $_GET[ self::PARAM_FIELDS ] ) ? array_filter( array_map( 'sanitize_key', explode( ',', wp_unslash( (string) $_GET[ self::PARAM_FIELDS ] ) ) ) ) : array();
		$count  = isset( $_GET[ self::PARAM_COUNT ] ) ? absint( $_GET[ self::PARAM_COUNT ] ) : null;
		// phpcs:enable
		return self::render( $code, array_values( $fields ), $count );
	}

	/**
	 * Render a notice.
	 *
	 * @param string       $code   Code.
	 * @param list<string> $fields Details.
	 * @param int|null     $count  Count.
	 */
	public static function render( string $code, array $fields = array(), ?int $count = null ): string {
		$messages = self::messages();
		if ( ! isset( $messages[ $code ] ) ) {
			return '';
		}
		[ $type, $message ] = $messages[ $code ];
		if ( null !== $count ) {
			$message .= ' ' . number_format_i18n( $count );
		}
		$html = '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible" role="' . ( 'error' === $type ? 'alert' : 'status' ) . '"><p>' . esc_html( $message ) . '</p>';
		if ( $fields ) {
			$html .= '<ul class="rc-admin-notice-list">';
			foreach ( $fields as $field ) {
				$html .= '<li>' . esc_html( self::detail_label( $field ) ) . '</li>';
			}
			$html .= '</ul>';
		}
		return $html . '</div>';
	}
}
