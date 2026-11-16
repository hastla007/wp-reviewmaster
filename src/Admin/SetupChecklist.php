<?php
/**
 * First-run checklist (SET-008, UX-012).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Admin;

use ReviewCaptain\Domain\Origin;
use ReviewCaptain\Plugin;
use ReviewCaptain\Security\Capabilities;

/**
 * Each step can be detected automatically, marked done or skipped. State lives in its own
 * option (never in review_captain_settings); skipping changes no defaults and activates nothing.
 */
final class SetupChecklist {

	public const OPTION = 'review_captain_setup_checklist';

	public const STATES = array( 'done', 'skipped', 'open' );

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( private Plugin $plugin ) {}

	/**
	 * Step definitions: key => [title, description, link].
	 *
	 * @return array<string, array{title: string, description: string, url: string, link: string}>
	 */
	public static function steps(): array {
		return array(
			'brand'   => array(
				'title'       => __( 'Marke & Sprache', 'review-captain' ),
				'description' => __( 'Markenname und Sprache für Hinweise und Formulare prüfen.', 'review-captain' ),
				'url'         => SettingsPage::tab_url( 'general' ),
				'link'        => __( 'Allgemeine Einstellungen', 'review-captain' ),
			),
			'profile' => array(
				'title'       => __( 'Erstes Profil / Bewertungslink', 'review-captain' ),
				'description' => __( 'Profil-Link einer Plattform hinterlegen, damit Besucher dich dort bewerten können.', 'review-captain' ),
				'url'         => View::page_url( SourcesPage::SLUG, array( 'action' => 'new' ) ),
				'link'        => __( 'Profil hinzufügen', 'review-captain' ),
			),
			'form'    => array(
				'title'       => __( 'Bewertungsformular aktiv', 'review-captain' ),
				'description' => __( 'Formular per Block oder Shortcode [review_captain_form v="1"] einbinden und Einreichungen aktiviert lassen.', 'review-captain' ),
				'url'         => SettingsPage::tab_url( 'submissions' ),
				'link'        => __( 'Einreichungen & Moderation', 'review-captain' ),
			),
			'widget'  => array(
				'title'       => __( 'Erstes Widget', 'review-captain' ),
				'description' => __( 'Reviews mit dem Block oder einem Shortcode auf einer Seite anzeigen.', 'review-captain' ),
				'url'         => View::page_url( WidgetsPage::SLUG ),
				'link'        => __( 'Widget erstellen', 'review-captain' ),
			),
			'privacy' => array(
				'title'       => __( 'Datenschutzhinweis', 'review-captain' ),
				'description' => __( 'Datenschutzhinweis im Formular und Aufbewahrungsfristen prüfen.', 'review-captain' ),
				'url'         => SettingsPage::tab_url( 'privacy' ),
				'link'        => __( 'Datenschutz & Aufbewahrung', 'review-captain' ),
			),
		);
	}

	/**
	 * Stored state.
	 *
	 * @return array{steps: array<string, string>, dismissed: bool}
	 */
	public function stored(): array {
		$raw   = get_option( self::OPTION, array() );
		$raw   = is_array( $raw ) ? $raw : array();
		$steps = array();
		foreach ( (array) ( $raw['steps'] ?? array() ) as $key => $state ) {
			if ( isset( self::steps()[ $key ] ) && in_array( $state, array( 'done', 'skipped' ), true ) ) {
				$steps[ (string) $key ] = (string) $state;
			}
		}
		return array(
			'steps'     => $steps,
			'dismissed' => ! empty( $raw['dismissed'] ),
		);
	}

	/**
	 * Automatic detection of completed steps (read-only, best effort).
	 *
	 * @return array<string, bool>
	 */
	public function detected(): array {
		$settings = $this->plugin->settings();
		$detected = array(
			'brand'   => '' !== (string) $settings->get( 'general', 'brand_name', '' ),
			'profile' => ( new Profiles() )->count() > 0,
			'form'    => (bool) $settings->get( 'submissions', 'enabled', false ) && $this->plugin->reviews()->count( array( 'origin' => Origin::LOCAL_SUBMISSION->value ) ) > 0,
			'widget'  => false,
			'privacy' => '' !== (string) $settings->get( 'privacy', 'privacy_notice', '' ),
		);
		/**
		 * Filter auto-detected checklist steps (e.g. the frontend module reports a published widget).
		 *
		 * @param array<string, bool> $detected Detected steps.
		 */
		$filtered = apply_filters( 'review_captain_setup_checklist_detected', $detected );
		return is_array( $filtered ) ? array_map( 'boolval', array_intersect_key( $filtered, $detected ) ) + $detected : $detected;
	}

	/**
	 * Effective state per step: done|skipped|open.
	 *
	 * @return array<string, string>
	 */
	public function states(): array {
		$stored   = $this->stored()['steps'];
		$detected = $this->detected();
		$out      = array();
		foreach ( array_keys( self::steps() ) as $key ) {
			$out[ $key ] = $stored[ $key ] ?? ( ! empty( $detected[ $key ] ) ? 'done' : 'open' );
		}
		return $out;
	}

	/**
	 * Whether every step is done or skipped.
	 */
	public function complete(): bool {
		return ! in_array( 'open', $this->states(), true );
	}

	/**
	 * Set a step state ('open' removes the manual mark).
	 *
	 * @param string $step  Step.
	 * @param string $state done|skipped|open.
	 */
	public function set( string $step, string $state ): bool {
		if ( ! isset( self::steps()[ $step ] ) || ! in_array( $state, self::STATES, true ) ) {
			return false;
		}
		$stored = $this->stored();
		if ( 'open' === $state ) {
			unset( $stored['steps'][ $step ] );
		} else {
			$stored['steps'][ $step ] = $state;
		}
		update_option( self::OPTION, $stored, false );
		return true;
	}

	/**
	 * Hide or show the checklist.
	 *
	 * @param bool $dismissed Dismissed.
	 */
	public function dismiss( bool $dismissed ): void {
		$stored              = $this->stored();
		$stored['dismissed'] = $dismissed;
		update_option( self::OPTION, $stored, false );
	}

	/**
	 * Handler (admin-post: review_captain_checklist).
	 */
	public function handle(): void {
		Capabilities::verify_request( Capabilities::MODERATE, 'checklist' );
		// phpcs:disable WordPress.Security.NonceVerification -- verified above.
		$step  = sanitize_key( wp_unslash( (string) ( $_REQUEST['step'] ?? '' ) ) );
		$state = sanitize_key( wp_unslash( (string) ( $_REQUEST['state'] ?? '' ) ) );
		// phpcs:enable
		if ( 'dismiss' === $step || 'show' === $step ) {
			$this->dismiss( 'dismiss' === $step );
		} else {
			$this->set( $step, $state );
		}
		View::redirect( Notices::url( View::page_url( DashboardPage::SLUG ), 'checklist_updated' ) );
	}

	/**
	 * Render the checklist box (or the "show again" link).
	 */
	public function render(): string {
		$stored = $this->stored();
		if ( $stored['dismissed'] ) {
			return '<p class="rc-admin-checklist-restore"><a href="' . esc_url( View::action_url( 'checklist', array( 'step' => 'show' ) ) ) . '">' . esc_html__( 'Einrichtung erneut anzeigen', 'review-captain' ) . '</a></p>';
		}
		$states = $this->states();
		$done   = count( array_filter( $states, static fn( $s ) => 'open' !== $s ) );
		$html   = '<div class="postbox rc-admin-checklist"><div class="postbox-header"><h2>' . esc_html__( 'Einrichtung', 'review-captain' ) . '</h2></div><div class="inside">';
		/* translators: 1: completed steps, 2: total steps */
		$html .= '<p>' . esc_html( sprintf( __( '%1$d von %2$d Schritten erledigt oder übersprungen. Jeder Schritt ist freiwillig; Überspringen ändert keine Einstellungen.', 'review-captain' ), $done, count( $states ) ) ) . '</p><ol class="rc-admin-steps">';
		foreach ( self::steps() as $key => $step ) {
			$state = $states[ $key ];
			$label = match ( $state ) {
				'done'    => __( 'Erledigt', 'review-captain' ),
				'skipped' => __( 'Übersprungen', 'review-captain' ),
				default   => __( 'Offen', 'review-captain' ),
			};
			$html .= '<li class="rc-admin-step rc-admin-step--' . esc_attr( $state ) . '"><strong>' . esc_html( $step['title'] ) . '</strong> ' . View::pill( $label, 'done' === $state ? 'ok' : 'neutral' );
			$html .= '<br /><span class="description">' . esc_html( $step['description'] ) . '</span><br />';
			$html .= '<a href="' . esc_url( $step['url'] ) . '">' . esc_html( $step['link'] ) . '</a>';
			if ( 'open' === $state ) {
				$html .= ' · <a href="' . esc_url( View::action_url( 'checklist', array( 'step' => $key, 'state' => 'done' ) ) ) . '">' . esc_html__( 'Als erledigt markieren', 'review-captain' ) . '</a>';
				$html .= ' · <a href="' . esc_url( View::action_url( 'checklist', array( 'step' => $key, 'state' => 'skipped' ) ) ) . '">' . esc_html__( 'Überspringen', 'review-captain' ) . '</a>';
			} elseif ( isset( $stored['steps'][ $key ] ) ) {
				$html .= ' · <a href="' . esc_url( View::action_url( 'checklist', array( 'step' => $key, 'state' => 'open' ) ) ) . '">' . esc_html__( 'Wieder öffnen', 'review-captain' ) . '</a>';
			}
			$html .= '</li>';
		}
		$html .= '</ol><p><a class="button" href="' . esc_url( View::action_url( 'checklist', array( 'step' => 'dismiss' ) ) ) . '">' . esc_html__( 'Ausblenden', 'review-captain' ) . '</a></p></div></div>';
		return $html;
	}
}
