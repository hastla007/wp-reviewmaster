<?php
/**
 * Dashboard (konzept §8.1, Free part).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Admin;

use ReviewCaptain\Domain\Platform;
use ReviewCaptain\Domain\ReviewStatus;
use ReviewCaptain\Plugin;
use ReviewCaptain\Security\Capabilities;

/**
 * Counters, per-platform summary (never a cross-platform score, RC-114), recent entries,
 * setup checklist, quick actions and a quiet, locked Pro info.
 */
final class DashboardPage {

	public const SLUG = 'review-captain';

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( private Plugin $plugin ) {}

	/**
	 * Per-platform rows for display. Each row stays on its own scale; there is no overall average.
	 *
	 * @return list<array{platform: string, label: string, count: int, average: string, scale: string}>
	 */
	public function platform_rows(): array {
		$rows = array();
		foreach ( $this->plugin->reviews()->summary_by_platform() as $row ) {
			$platform = Platform::tryFrom( $row['platform'] );
			$rows[]   = array(
				'platform' => $row['platform'],
				'label'    => $platform ? $platform->label() : $row['platform'],
				'count'    => $row['count'],
				'average'  => null !== $row['average'] ? View::number( (float) $row['average'] ) : '–',
				'scale'    => View::number( $row['scale_max'] ),
			);
		}
		return $rows;
	}

	/**
	 * Page callback.
	 */
	public function render(): void {
		if ( ! current_user_can( Capabilities::MODERATE ) ) {
			wp_die( esc_html__( 'Dafür fehlt dir die Berechtigung.', 'review-captain' ), '', array( 'response' => 403 ) );
		}
		$repo     = $this->plugin->reviews();
		$pending  = $repo->count( array( 'status' => ReviewStatus::PENDING->value ) );
		$approved = $repo->count( array( 'status' => ReviewStatus::APPROVED->value ) );
		$total    = $repo->count();
		$new_30   = $repo->count( array( 'date_from' => gmdate( 'Y-m-d', time() - 30 * DAY_IN_SECONDS ) ) );

		echo '<div class="wrap rc-admin">';
		echo View::header( 'Review Captain', View::page_url( ReviewsPage::SLUG, array( 'action' => 'new' ) ), __( 'Review hinzufügen', 'review-captain' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo Notices::render_current(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		echo ( new SetupChecklist( $this->plugin ) )->render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( 0 === $total ) {
			echo '<div class="card rc-admin-empty"><h2>' . esc_html__( 'Noch keine Reviews', 'review-captain' ) . '</h2>';
			echo '<p>' . esc_html__( 'Lege dein erstes Review an oder sammle Bewertungen mit einem Formular auf deiner Website.', 'review-captain' ) . '</p>';
			echo '<p><a class="button button-primary" href="' . esc_url( View::page_url( ReviewsPage::SLUG, array( 'action' => 'new' ) ) ) . '">' . esc_html__( 'Review hinzufügen', 'review-captain' ) . '</a> ';
			echo '<a class="button" href="' . esc_url( View::page_url( WidgetsPage::SLUG ) ) . '">' . esc_html__( 'Formular einbinden', 'review-captain' ) . '</a></p></div>';
		}

		$pending_url  = View::page_url( ReviewsPage::SLUG, array( 'status' => ReviewStatus::PENDING->value ) );
		$approved_url = View::page_url( ReviewsPage::SLUG, array( 'status' => ReviewStatus::APPROVED->value ) );
		echo '<div class="rc-admin-grid">';
		$this->kpi( __( 'Warten auf Prüfung', 'review-captain' ), $pending, $pending_url, $pending > 0 ? __( 'Jetzt prüfen', 'review-captain' ) : __( 'Alles erledigt', 'review-captain' ) );
		$this->kpi( __( 'Freigegeben (sichtbar)', 'review-captain' ), $approved, $approved_url, __( 'Ansehen', 'review-captain' ) );
		$this->kpi( __( 'Neu (30 Tage)', 'review-captain' ), $new_30, View::page_url( ReviewsPage::SLUG, array( 'period' => 30 ) ), __( 'Zur Liste', 'review-captain' ) );
		echo '</div>';

		$this->render_platforms();
		echo '<div class="rc-admin-columns"><div class="rc-admin-main">';
		$this->render_recent();
		echo '</div><div class="rc-admin-side">';
		$this->render_quick_actions();
		$this->render_pro_info();
		echo '</div></div></div>';
	}

	/**
	 * KPI card.
	 *
	 * @param string $label Label.
	 * @param int    $value Value.
	 * @param string $url   Link.
	 * @param string $link  Link text.
	 */
	private function kpi( string $label, int $value, string $url, string $link ): void {
		echo '<div class="postbox rc-admin-kpi"><div class="inside"><p class="rc-admin-kpi__label">' . esc_html( $label ) . '</p>';
		echo '<p class="rc-admin-kpi__value">' . esc_html( number_format_i18n( $value ) ) . '</p>';
		echo '<p><a href="' . esc_url( $url ) . '">' . esc_html( $link ) . '</a></p></div></div>';
	}

	/**
	 * Per-platform summary.
	 */
	private function render_platforms(): void {
		echo '<div class="postbox"><div class="postbox-header"><h2>' . esc_html__( 'Bewertungen nach Quelle', 'review-captain' ) . '</h2></div><div class="inside">';
		$rows = $this->platform_rows();
		if ( ! $rows ) {
			echo '<p>' . esc_html__( 'Noch keine freigegebenen Bewertungen.', 'review-captain' ) . '</p></div></div>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Quelle', 'review-captain' ) . '</th><th scope="col">' . esc_html__( 'Durchschnitt (Originalskala)', 'review-captain' ) . '</th><th scope="col">' . esc_html__( 'Anzahl', 'review-captain' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			echo '<tr><td>' . esc_html( $row['label'] ) . '</td><td>' . esc_html( $row['average'] . ' / ' . $row['scale'] ) . '</td><td>' . esc_html( number_format_i18n( $row['count'] ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<p class="description">' . esc_html__( 'Werte je Quelle getrennt und nur aus gespeicherten, freigegebenen Einträgen. Es wird bewusst kein plattformübergreifender Gesamtschnitt gebildet.', 'review-captain' ) . '</p></div></div>';
	}

	/**
	 * Recent entries.
	 */
	private function render_recent(): void {
		$items = $this->plugin->reviews()->query( array( 'limit' => 5 ) );
		echo '<div class="postbox"><div class="postbox-header"><h2>' . esc_html__( 'Zuletzt eingegangen', 'review-captain' ) . '</h2></div><div class="inside">';
		if ( ! $items ) {
			echo '<p>' . esc_html__( 'Noch keine Einträge.', 'review-captain' ) . '</p></div></div>';
			return;
		}
		echo '<ul class="rc-admin-recent">';
		foreach ( $items as $item ) {
			$url    = View::page_url( ReviewsPage::SLUG, array( 'action' => 'edit', 'id' => $item->id ) );
			$action = ReviewStatus::PENDING === $item->status ? __( 'Prüfen', 'review-captain' ) : __( 'Ansehen', 'review-captain' );
			echo '<li><span class="rc-admin-rating">' . esc_html( View::rating( $item ) ) . '</span> „' . esc_html( View::excerpt( $item->body, 80 ) ) . '“ · ' . esc_html( $item->platform->label() ) . ' · ' . esc_html( View::date( $item->published_at ?? $item->created_at ) ) . ' · ' . View::pill( $item->status->label() ) . ' <a href="' . esc_url( $url ) . '">' . esc_html( $action ) . '</a></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</ul><p><a href="' . esc_url( View::page_url( ReviewsPage::SLUG ) ) . '">' . esc_html__( 'Zur Review-Liste', 'review-captain' ) . '</a></p></div></div>';
	}

	/**
	 * Quick actions (only those the user may use).
	 */
	private function render_quick_actions(): void {
		echo '<div class="postbox"><div class="postbox-header"><h2>' . esc_html__( 'Schnellaktionen', 'review-captain' ) . '</h2></div><div class="inside"><ul>';
		if ( current_user_can( Capabilities::MANAGE_SOURCES ) ) {
			echo '<li><a href="' . esc_url( View::page_url( SourcesPage::SLUG, array( 'action' => 'new' ) ) ) . '">' . esc_html__( 'Profil-Link hinzufügen', 'review-captain' ) . '</a></li>';
		}
		echo '<li><a href="' . esc_url( View::page_url( WidgetsPage::SLUG ) ) . '">' . esc_html__( 'Widget erstellen', 'review-captain' ) . '</a></li>';
		echo '<li><a href="' . esc_url( View::page_url( ReviewsPage::SLUG, array( 'action' => 'new', 'type' => ReviewEditor::TYPE_EXTERNAL ) ) ) . '">' . esc_html__( 'Externe Bewertung übernehmen', 'review-captain' ) . '</a></li>';
		if ( current_user_can( Capabilities::VIEW_REPORTS ) ) {
			echo '<li><a href="' . esc_url( View::page_url( DiagnosticsPage::SLUG ) ) . '">' . esc_html__( 'Diagnose', 'review-captain' ) . '</a></li>';
		}
		echo '</ul></div></div>';
	}

	/**
	 * Quiet, locked Pro info (no upsell wall, nothing clickable). Hidden once Pro registers itself.
	 */
	private function render_pro_info(): void {
		/**
		 * Whether to show the locked "In Review Captain Pro" info on the dashboard.
		 *
		 * @param bool $show Default true; the Pro add-on returns false.
		 */
		if ( ! apply_filters( 'review_captain_show_pro_info', true ) ) {
			return;
		}
		echo '<div class="postbox rc-admin-pro-card"><div class="postbox-header"><h2>' . esc_html__( 'In Review Captain Pro', 'review-captain' ) . '</h2></div><div class="inside">';
		echo '<ul class="rc-admin-pro-list">';
		foreach ( array(
			__( 'Review-Anfragen nach echten Erlebnissen', 'review-captain' ),
			__( 'Analytics je Quelle', 'review-captain' ),
			__( 'Automatische Synchronisierung über offizielle Schnittstellen', 'review-captain' ),
			__( 'Captain-Suite-Integrationen und Connectoren', 'review-captain' ),
		) as $feature ) {
			echo '<li>' . View::pro_lock( $feature ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</ul><p class="description">' . esc_html__( 'Alle Kernfunktionen bleiben ohne Pro vollständig nutzbar.', 'review-captain' ) . '</p></div></div>';
	}
}
