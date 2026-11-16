<?php
/**
 * Reviews admin page: list, detail/edit, create (RC-038, RC-040, RC-043, RC-053).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Admin;

use ReviewCaptain\Domain\Kind;
use ReviewCaptain\Domain\Origin;
use ReviewCaptain\Domain\Platform;
use ReviewCaptain\Domain\Review;
use ReviewCaptain\Domain\ReviewStatus;
use ReviewCaptain\Plugin;
use ReviewCaptain\Security\Capabilities;

/**
 * Renders the Reviews screens. Writes go through ReviewActions (admin-post / load hook).
 */
final class ReviewsPage {

	public const SLUG = 'review-captain-reviews';

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( private Plugin $plugin ) {}

	/**
	 * Page callback.
	 */
	public function render(): void {
		if ( ! current_user_can( Capabilities::MODERATE ) ) {
			wp_die( esc_html__( 'Dafür fehlt dir die Berechtigung.', 'review-captain' ), '', array( 'response' => 403 ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only.
		$action = View::req( $_GET, 'action' );
		echo '<div class="wrap rc-admin">';
		if ( 'edit' === $action ) {
			$review = $this->plugin->reviews()->find( absint( View::req( $_GET, 'id' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( null === $review ) {
				echo View::header( __( 'Review', 'review-captain' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo View::notice( 'error', __( 'Der Eintrag wurde nicht gefunden.', 'review-captain' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} else {
				$this->render_edit( $review );
			}
		} elseif ( 'new' === $action ) {
			$this->render_new( View::req( $_GET, 'type', ReviewEditor::TYPE_LOCAL ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		} else {
			$this->render_list();
		}
		echo '</div>';
	}

	/**
	 * List screen.
	 */
	private function render_list(): void {
		if ( ! class_exists( '\WP_List_Table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		}
		$table = new ReviewsListTable( $this->plugin );
		$table->prepare_items();

		echo View::header( __( 'Reviews', 'review-captain' ), View::page_url( self::SLUG, array( 'action' => 'new' ) ), __( 'Neu hinzufügen', 'review-captain' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo Notices::render_current(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		$pending = $this->plugin->reviews()->count( array( 'status' => ReviewStatus::PENDING->value ) );
		if ( 0 === $pending && $this->plugin->reviews()->count() > 0 ) {
			echo View::notice( 'info', __( 'Alles erledigt – keine Reviews warten auf Prüfung.', 'review-captain' ), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		$table->views();
		$this->render_filters();

		$status = View::req( $_GET, 'status' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<form method="post" action="' . esc_url( View::page_url( self::SLUG ) ) . '">';
		echo '<input type="hidden" name="rc_bulk" value="1" />';
		echo '<input type="hidden" name="status" value="' . esc_attr( $status ) . '" />';
		wp_nonce_field( Capabilities::nonce_action( 'bulk_reviews' ), '_rc_nonce' );
		$table->display();
		echo '</form>';
	}

	/**
	 * Filter + search form (GET, read-only).
	 */
	private function render_filters(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
		$get = $_GET;
		// phpcs:enable
		echo '<form method="get" class="rc-admin-filters">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '" />';
		if ( '' !== View::req( $get, 'status' ) ) {
			echo '<input type="hidden" name="status" value="' . esc_attr( View::req( $get, 'status' ) ) . '" />';
		}
		echo '<p class="search-box"><label class="screen-reader-text" for="rc-search">' . esc_html__( 'Reviews durchsuchen', 'review-captain' ) . '</label>';
		echo '<input type="search" id="rc-search" name="s" value="' . esc_attr( View::req( $get, 's' ) ) . '" /> ';
		echo '<button type="submit" class="button">' . esc_html__( 'Reviews durchsuchen', 'review-captain' ) . '</button></p>';

		echo '<div class="tablenav top rc-admin-filterbar"><div class="alignleft actions">';
		$this->select( 'kind', __( 'Typ', 'review-captain' ), array( '' => __( 'Alle Typen', 'review-captain' ) ) + self::kind_choices(), View::req( $get, 'kind' ) );
		$this->select( 'platform', __( 'Plattform', 'review-captain' ), array( '' => __( 'Alle Plattformen', 'review-captain' ) ) + self::platform_choices(), View::req( $get, 'platform' ) );
		$ratings = array( '' => __( 'Jede Bewertung', 'review-captain' ) );
		for ( $i = 5; $i >= 1; $i-- ) {
			/* translators: %d: stars on a 5-point scale */
			$ratings[ (string) $i ] = sprintf( __( 'ab %d von 5 (umgerechnet)', 'review-captain' ), $i );
		}
		$this->select( 'rating', __( 'Bewertung', 'review-captain' ), $ratings, View::req( $get, 'rating' ) );
		$this->select(
			'period',
			__( 'Zeitraum', 'review-captain' ),
			array(
				''    => __( 'Gesamter Zeitraum', 'review-captain' ),
				'7'   => __( 'Letzte 7 Tage', 'review-captain' ),
				'30'  => __( 'Letzte 30 Tage', 'review-captain' ),
				'90'  => __( 'Letzte 90 Tage', 'review-captain' ),
				'365' => __( 'Letzte 12 Monate', 'review-captain' ),
			),
			View::req( $get, 'period' )
		);
		echo '<button type="submit" class="button">' . esc_html__( 'Filtern', 'review-captain' ) . '</button>';
		echo '</div></div></form>';
	}

	/**
	 * Render a labelled select.
	 *
	 * @param string                $name     Name.
	 * @param string                $label    Label.
	 * @param array<string, string> $choices  Choices.
	 * @param string                $current  Current.
	 */
	private function select( string $name, string $label, array $choices, string $current ): void {
		$id = 'rc-filter-' . $name;
		echo '<label class="screen-reader-text" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
		echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
		foreach ( $choices as $value => $text ) {
			echo '<option value="' . esc_attr( (string) $value ) . '"' . selected( (string) $value, $current, false ) . '>' . esc_html( $text ) . '</option>';
		}
		echo '</select> ';
	}

	/**
	 * Kind choices.
	 *
	 * @return array<string, string>
	 */
	public static function kind_choices(): array {
		$out = array();
		foreach ( Kind::cases() as $kind ) {
			$out[ $kind->value ] = $kind->label();
		}
		return $out;
	}

	/**
	 * Platform choices.
	 *
	 * @param bool $external_only Only external platforms.
	 * @return array<string, string>
	 */
	public static function platform_choices( bool $external_only = false ): array {
		$out = array();
		foreach ( Platform::cases() as $platform ) {
			if ( $external_only && ! $platform->is_external() ) {
				continue;
			}
			$out[ $platform->value ] = $platform->label();
		}
		return $out;
	}

	/**
	 * Context type choices.
	 *
	 * @return array<string, string>
	 */
	public static function context_choices(): array {
		return array(
			''             => __( 'Kein Bezug', 'review-captain' ),
			'post'         => __( 'Beitrag/Seite', 'review-captain' ),
			'product'      => __( 'Produkt', 'review-captain' ),
			'event'        => __( 'Veranstaltung', 'review-captain' ),
			'voucher'      => __( 'Gutschein', 'review-captain' ),
			'app'          => __( 'App', 'review-captain' ),
			'form'         => __( 'Formular', 'review-captain' ),
			'course'       => __( 'Kurs', 'review-captain' ),
			'organization' => __( 'Unternehmen', 'review-captain' ),
		);
	}

	/**
	 * Create screen.
	 *
	 * @param string $type Entry type.
	 */
	private function render_new( string $type ): void {
		$types = ReviewEditor::types();
		$type  = array_key_exists( $type, $types ) ? $type : ReviewEditor::TYPE_LOCAL;
		$draft = ReviewActions::take_draft();

		echo View::header( __( 'Neuen Eintrag anlegen', 'review-captain' ), View::page_url( self::SLUG ), __( '← Zur Liste', 'review-captain' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo Notices::render_current(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $types as $key => $label ) {
			$class = 'nav-tab' . ( $key === $type ? ' nav-tab-active' : '' );
			echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( View::page_url( self::SLUG, array( 'action' => 'new', 'type' => $key ) ) ) . '"' . ( $key === $type ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a>';
		}
		echo '</h2>';

		if ( ReviewEditor::TYPE_TESTIMONIAL === $type ) {
			echo View::notice( 'info', __( 'Ein Erfahrungsbericht wird als Website-eigener Bericht gekennzeichnet – nicht als Bewertung einer Plattform. Er erhält kein Bewertungs-Markup für Suchmaschinen.', 'review-captain' ), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} elseif ( ReviewEditor::TYPE_EXTERNAL === $type ) {
			echo View::notice( 'info', __( 'Übernimm nur Texte, deren Nutzung dir erlaubt ist, und verlinke immer das Original. Die Bewertung bleibt auf ihrer Originalskala und wird nicht mit anderen Plattformen verrechnet.', 'review-captain' ), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo View::form_fields( 'save_review' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<input type="hidden" name="review[type]" value="' . esc_attr( $type ) . '" />';
		echo '<table class="form-table" role="presentation"><tbody>';

		if ( ReviewEditor::TYPE_EXTERNAL === $type ) {
			$this->row_select( 'platform', __( 'Plattform', 'review-captain' ), self::platform_choices( true ), (string) ( $draft['platform'] ?? '' ), true );
			$profiles = array( '' => __( '– kein Profil –', 'review-captain' ) );
			foreach ( ( new Profiles() )->all() as $profile ) {
				$profiles[ (string) $profile['id'] ] = Platform::tryFrom( (string) $profile['platform'] )?->label() . ' – ' . $profile['label'];
			}
			$this->row_select( 'profile_id', __( 'Profil', 'review-captain' ), $profiles, (string) ( $draft['profile_id'] ?? '' ) );
			echo '<tr><th scope="row"><label for="rc-rating_value">' . esc_html__( 'Originalbewertung', 'review-captain' ) . ' <span class="required">*</span></label></th><td>';
			echo '<input type="text" inputmode="decimal" id="rc-rating_value" name="review[rating_value]" class="small-text" required value="' . esc_attr( (string) ( $draft['rating_value'] ?? '' ) ) . '" /> ';
			echo '<label for="rc-scale-min">' . esc_html__( 'auf der Skala von', 'review-captain' ) . '</label> <input type="number" step="1" id="rc-scale-min" name="review[rating_scale_min]" class="small-text" value="' . esc_attr( (string) ( $draft['rating_scale_min'] ?? '1' ) ) . '" /> ';
			echo '<label for="rc-scale-max">' . esc_html__( 'bis', 'review-captain' ) . '</label> <input type="number" step="1" id="rc-scale-max" name="review[rating_scale_max]" class="small-text" value="' . esc_attr( (string) ( $draft['rating_scale_max'] ?? '5' ) ) . '" />';
			echo '<p class="description">' . esc_html__( 'Genau so, wie sie auf der Plattform angezeigt wird (z. B. 4 von 5 oder 9 von 10).', 'review-captain' ) . '</p></td></tr>';
		} else {
			echo '<tr><th scope="row"><label for="rc-rating_value">' . esc_html__( 'Bewertung', 'review-captain' ) . '</label></th><td>';
			echo '<select id="rc-rating_value" name="review[rating_value]"><option value="">' . esc_html__( '– ohne Sterne –', 'review-captain' ) . '</option>';
			for ( $i = 5; $i >= 1; $i-- ) {
				/* translators: %d: rating value */
				echo '<option value="' . esc_attr( (string) $i ) . '"' . selected( (string) $i, (string) ( $draft['rating_value'] ?? '' ), false ) . '>' . esc_html( sprintf( __( '%d von 5', 'review-captain' ), $i ) ) . '</option>';
			}
			echo '</select></td></tr>';
		}

		$this->row_text( 'title', __( 'Titel', 'review-captain' ), (string) ( $draft['title'] ?? '' ) );
		$body_label = ReviewEditor::TYPE_EXTERNAL === $type ? __( 'Erlaubter Text (Auszug)', 'review-captain' ) : __( 'Text', 'review-captain' );
		$this->row_textarea( 'body', $body_label, (string) ( $draft['body'] ?? '' ), true );
		$this->row_text( 'author_name', ReviewEditor::TYPE_TESTIMONIAL === $type ? __( 'Name der Person', 'review-captain' ) : __( 'Name', 'review-captain' ), (string) ( $draft['author_name'] ?? '' ), true );
		$this->row_date( 'published_at', ReviewEditor::TYPE_EXTERNAL === $type ? __( 'Datum der Originalbewertung', 'review-captain' ) : __( 'Datum', 'review-captain' ), (string) ( $draft['published_at'] ?? '' ), ReviewEditor::TYPE_EXTERNAL === $type );
		$this->row_text( 'language', __( 'Sprache', 'review-captain' ), (string) ( $draft['language'] ?? '' ), false, __( 'Sprachkürzel, z. B. de oder de_AT. Texte werden nie übersetzt.', 'review-captain' ) );

		if ( ReviewEditor::TYPE_EXTERNAL === $type ) {
			$this->row_text( 'source_url', __( 'Profil-/Original-Link', 'review-captain' ), (string) ( $draft['source_url'] ?? '' ), true, __( 'Nur https-Links. Wird im Frontend als Quelle verlinkt.', 'review-captain' ), 'url' );
			$this->row_text( 'source_note', __( 'Quellenhinweis', 'review-captain' ), (string) ( $draft['source_note'] ?? '' ), true, __( 'z. B. „Auszug aus Google, übernommen am 14.11.2026“.', 'review-captain' ) );
		}
		$this->row_context( (string) ( $draft['context_type'] ?? '' ), (string) ( $draft['context_id'] ?? '' ) );

		echo '<tr><th scope="row">' . esc_html__( 'Verifizierung', 'review-captain' ) . '</th><td>';
		echo esc_html( ReviewEditor::TYPE_EXTERNAL === $type ? __( 'Externe Plattformbewertung', 'review-captain' ) : __( 'Ungeprüft', 'review-captain' ) );
		echo '<p class="description">' . esc_html__( '„Kauf verifiziert“ oder „Teilnahme bestätigt“ kann nur eine angebundene Quelle setzen, nicht manuell.', 'review-captain' ) . '</p></td></tr>';

		$this->row_select(
			'status',
			__( 'Status', 'review-captain' ),
			array(
				ReviewStatus::PENDING->value  => ReviewStatus::PENDING->label(),
				ReviewStatus::APPROVED->value => ReviewStatus::APPROVED->label(),
			),
			(string) ( $draft['status'] ?? ReviewStatus::PENDING->value )
		);
		echo '</tbody></table>';
		echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Eintrag anlegen', 'review-captain' ) . '</button></p>';
		echo '</form>';
	}

	/**
	 * Edit / detail screen.
	 *
	 * @param Review $review Review.
	 */
	private function render_edit( Review $review ): void {
		$external = $review->platform->is_external();
		$draft    = ReviewActions::take_draft();
		$val      = static fn( string $key, string $current ): string => array_key_exists( $key, $draft ) ? (string) $draft[ $key ] : $current;

		echo View::header( $external ? __( 'Review ansehen', 'review-captain' ) : __( 'Review bearbeiten', 'review-captain' ), View::page_url( self::SLUG ), __( '← Zur Liste', 'review-captain' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo Notices::render_current(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		echo '<div class="rc-admin-columns"><div class="rc-admin-main">';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo View::form_fields( 'save_review' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<input type="hidden" name="id" value="' . esc_attr( (string) $review->id ) . '" />';

		if ( Kind::TESTIMONIAL === $review->kind ) {
			echo View::notice( 'info', __( 'Erfahrungsbericht der Website (Text-Testimonial) – wird nicht als Plattformbewertung ausgegeben.', 'review-captain' ), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( $external ) {
			/* translators: %s: platform name */
			echo View::notice( 'info', sprintf( __( 'Inhalt stammt von %s und kann hier nicht geändert werden. Du kannst die Sichtbarkeit (Status), das Profil und den Bezug ändern.', 'review-captain' ), $review->platform->label() ), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		echo '<table class="form-table" role="presentation"><tbody>';
		if ( $external ) {
			$this->row_static( __( 'Plattform', 'review-captain' ), $review->platform->label() );
			$this->row_static( __( 'Originalbewertung', 'review-captain' ), View::rating( $review ) );
			$this->row_static( __( 'Titel', 'review-captain' ), '' !== $review->title ? $review->title : '–' );
			echo '<tr><th scope="row">' . esc_html__( 'Text', 'review-captain' ) . '</th><td><blockquote class="rc-admin-quote">' . nl2br( esc_html( $review->body ) ) . '</blockquote></td></tr>';
			$this->row_static( __( 'Name', 'review-captain' ), $review->author_name );
			$this->row_static( __( 'Datum der Originalbewertung', 'review-captain' ), View::date( $review->published_at ) );
			echo '<tr><th scope="row">' . esc_html__( 'Original-Link', 'review-captain' ) . '</th><td>';
			if ( null !== $review->source_url && Profiles::is_https( $review->source_url ) ) {
				echo '<a href="' . esc_url( $review->source_url ) . '" target="_blank" rel="noopener noreferrer nofollow">' . esc_html( $review->source_url ) . '</a>';
			} else {
				echo '–';
			}
			echo '</td></tr>';
			if ( ! empty( $review->meta['source_note'] ) ) {
				$this->row_static( __( 'Quellenhinweis', 'review-captain' ), (string) $review->meta['source_note'] );
			}
			$profiles = array( '' => __( '– kein Profil –', 'review-captain' ) );
			foreach ( ( new Profiles() )->all( $review->platform->value ) as $profile ) {
				$profiles[ (string) $profile['id'] ] = (string) $profile['label'];
			}
			if ( null === $review->external_id ) {
				$this->row_select( 'profile_id', __( 'Profil', 'review-captain' ), $profiles, $val( 'profile_id', (string) $review->profile_id ) );
			}
		} else {
			echo '<tr><th scope="row"><label for="rc-rating_value">' . esc_html__( 'Bewertung', 'review-captain' ) . '</label></th><td>';
			echo '<select id="rc-rating_value" name="review[rating_value]"><option value="">' . esc_html__( '– ohne Sterne –', 'review-captain' ) . '</option>';
			$current = null !== $review->rating_value ? (string) (int) round( $review->rating_value ) : '';
			for ( $i = 5; $i >= 1; $i-- ) {
				/* translators: %d: rating value */
				echo '<option value="' . esc_attr( (string) $i ) . '"' . selected( (string) $i, $val( 'rating_value', $current ), false ) . '>' . esc_html( sprintf( __( '%d von 5', 'review-captain' ), $i ) ) . '</option>';
			}
			echo '</select></td></tr>';
			$this->row_text( 'title', __( 'Titel', 'review-captain' ), $val( 'title', $review->title ) );
			$this->row_textarea( 'body', __( 'Text', 'review-captain' ), $val( 'body', $review->body ), true );
			$this->row_text( 'author_name', __( 'Name', 'review-captain' ), $val( 'author_name', $review->author_name ), true );
			$this->row_date( 'published_at', __( 'Datum', 'review-captain' ), $val( 'published_at', null !== $review->published_at ? substr( $review->published_at, 0, 10 ) : '' ) );
			$this->row_text( 'language', __( 'Sprache', 'review-captain' ), $val( 'language', $review->language ) );
		}
		$this->row_context( $val( 'context_type', (string) $review->context_type ), $val( 'context_id', (string) $review->context_id ) );
		echo '</tbody></table>';
		echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Änderungen speichern', 'review-captain' ) . '</button></p>';
		echo '</form>';

		$this->render_history( $review );
		echo '</div><div class="rc-admin-side">';
		$this->render_moderation_box( $review );
		$this->render_origin_box( $review );
		echo '</div></div>';
	}

	/**
	 * Moderation box (separate form, reason required for reject/archive).
	 *
	 * @param Review $review Review.
	 */
	private function render_moderation_box( Review $review ): void {
		echo '<div class="postbox" id="rc-moderation"><div class="postbox-header"><h2>' . esc_html__( 'Status', 'review-captain' ) . '</h2></div><div class="inside">';
		echo '<p>' . esc_html__( 'Aktuell:', 'review-captain' ) . ' ' . View::pill( $review->status->label() ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$targets = $review->status->allowed_transitions();
		if ( ! $targets ) {
			echo '<p class="description">' . esc_html__( 'Dieser Eintrag wurde von der Quelle entfernt und kann nicht mehr moderiert werden.', 'review-captain' ) . '</p></div></div>';
			return;
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo View::form_fields( 'review_status' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<input type="hidden" name="id" value="' . esc_attr( (string) $review->id ) . '" />';
		echo '<p><label for="rc-status-to">' . esc_html__( 'Neuer Status', 'review-captain' ) . '</label><br /><select id="rc-status-to" name="to">';
		foreach ( $targets as $target ) {
			echo '<option value="' . esc_attr( $target->value ) . '">' . esc_html( $target->label() ) . '</option>';
		}
		echo '</select></p>';
		echo '<p><label for="rc-status-reason">' . esc_html__( 'Grund', 'review-captain' ) . '</label><br /><input type="text" id="rc-status-reason" name="reason" class="widefat" maxlength="500" aria-describedby="rc-status-reason-desc" />';
		echo '<span class="description" id="rc-status-reason-desc">' . esc_html__( 'Pflicht bei „Abgelehnt“ und „Archiviert“. Wird im Verlauf gespeichert – keine personenbezogenen Daten eintragen.', 'review-captain' ) . '</span></p>';
		echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Status ändern', 'review-captain' ) . '</button></p>';
		echo '</form></div></div>';
	}

	/**
	 * Provenance box (read-only; verification is display only – RC-041).
	 *
	 * @param Review $review Review.
	 */
	private function render_origin_box( Review $review ): void {
		$origins = array(
			Origin::LOCAL_SUBMISSION->value => __( 'Formular auf dieser Website', 'review-captain' ),
			Origin::MANUAL->value           => __( 'Manuell angelegt', 'review-captain' ),
			Origin::CSV_IMPORT->value       => __( 'CSV-Import', 'review-captain' ),
			Origin::PROVIDER_SYNC->value    => __( 'Synchronisierung', 'review-captain' ),
			Origin::CONNECTOR->value        => __( 'Connector', 'review-captain' ),
		);
		echo '<div class="postbox"><div class="postbox-header"><h2>' . esc_html__( 'Herkunft', 'review-captain' ) . '</h2></div><div class="inside"><dl class="rc-admin-dl">';
		$rows = array(
			__( 'Typ', 'review-captain' )           => $review->kind->label(),
			__( 'Ursprung', 'review-captain' )      => $origins[ $review->origin->value ] ?? $review->origin->value,
			__( 'Plattform', 'review-captain' )     => $review->platform->label(),
			__( 'Verifizierung', 'review-captain' ) => $review->verification->label(),
			__( 'Angelegt', 'review-captain' )      => View::date( $review->created_at, true ),
		);
		if ( Origin::LOCAL_SUBMISSION === $review->origin ) {
			$rows[ __( 'Einwilligung', 'review-captain' ) ] = $review->consent_publish ? __( 'ja', 'review-captain' ) . ( null !== $review->consent_at ? ', ' . View::date( $review->consent_at ) : '' ) : __( 'nein', 'review-captain' );
			if ( null !== $review->author_email ) {
				$rows[ __( 'Kontakt (privat)', 'review-captain' ) ] = $review->author_email;
			}
		}
		if ( null !== $review->import_batch_id ) {
			$rows[ __( 'Import-Batch', 'review-captain' ) ] = '#' . $review->import_batch_id;
		}
		foreach ( $rows as $label => $value ) {
			echo '<dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd>';
		}
		echo '</dl><p class="description">' . esc_html__( '„Kauf verifiziert“ und „Teilnahme bestätigt“ setzt nur eine angebundene Quelle; manuell ist das nicht möglich.', 'review-captain' ) . '</p></div></div>';
	}

	/**
	 * Moderation history.
	 *
	 * @param Review $review Review.
	 */
	private function render_history( Review $review ): void {
		$history = $this->plugin->reviews()->history( (int) $review->id );
		echo '<h2>' . esc_html__( 'Änderungsverlauf', 'review-captain' ) . '</h2>';
		if ( ! $history ) {
			echo '<p>' . esc_html__( 'Noch keine Einträge.', 'review-captain' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Zeit', 'review-captain' ) . '</th><th scope="col">' . esc_html__( 'Aktion', 'review-captain' ) . '</th><th scope="col">' . esc_html__( 'Grund', 'review-captain' ) . '</th><th scope="col">' . esc_html__( 'Benutzer', 'review-captain' ) . '</th></tr></thead><tbody>';
		foreach ( $history as $event ) {
			$from   = ReviewStatus::tryFrom( (string) ( $event['from_status'] ?? '' ) );
			$to     = ReviewStatus::tryFrom( (string) ( $event['to_status'] ?? '' ) );
			$action = 'created' === $event['action'] ? __( 'erstellt', 'review-captain' ) . ( $to ? ' (' . $to->label() . ')' : '' ) : ( $from && $to ? $from->label() . ' → ' . $to->label() : (string) $event['action'] );
			$user   = ! empty( $event['user_id'] ) ? get_userdata( (int) $event['user_id'] ) : false;
			echo '<tr><td>' . esc_html( View::date( (string) $event['created_at'], true ) ) . '</td><td>' . esc_html( $action ) . '</td><td>' . esc_html( (string) $event['reason'] ) . '</td><td>' . esc_html( $user ? (string) $user->display_name : __( 'System', 'review-captain' ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Row: text input.
	 *
	 * @param string $key      Field key.
	 * @param string $label    Label.
	 * @param string $value    Value.
	 * @param bool   $required Required.
	 * @param string $help     Help text.
	 * @param string $type     Input type.
	 */
	private function row_text( string $key, string $label, string $value, bool $required = false, string $help = '', string $type = 'text' ): void {
		$id = 'rc-' . $key;
		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . ( $required ? ' <span class="required">*</span>' : '' ) . '</label></th><td>';
		echo '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="review[' . esc_attr( $key ) . ']" class="regular-text" value="' . esc_attr( $value ) . '"' . ( $required ? ' required' : '' ) . ( 'url' === $type ? ' pattern="https://.+"' : '' ) . ' />';
		if ( '' !== $help ) {
			echo '<p class="description">' . esc_html( $help ) . '</p>';
		}
		echo '</td></tr>';
	}

	/**
	 * Row: date input.
	 *
	 * @param string $key      Key.
	 * @param string $label    Label.
	 * @param string $value    Y-m-d.
	 * @param bool   $required Required.
	 */
	private function row_date( string $key, string $label, string $value, bool $required = false ): void {
		$this->row_text( $key, $label, $value, $required, '', 'date' );
	}

	/**
	 * Row: textarea.
	 *
	 * @param string $key      Key.
	 * @param string $label    Label.
	 * @param string $value    Value.
	 * @param bool   $required Required.
	 */
	private function row_textarea( string $key, string $label, string $value, bool $required = false ): void {
		$id = 'rc-' . $key;
		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . ( $required ? ' <span class="required">*</span>' : '' ) . '</label></th><td>';
		echo '<textarea id="' . esc_attr( $id ) . '" name="review[' . esc_attr( $key ) . ']" rows="6" class="large-text"' . ( $required ? ' required' : '' ) . '>' . esc_textarea( $value ) . '</textarea></td></tr>';
	}

	/**
	 * Row: select.
	 *
	 * @param string                $key      Key.
	 * @param string                $label    Label.
	 * @param array<string, string> $choices  Choices.
	 * @param string                $current  Current.
	 * @param bool                  $required Required.
	 */
	private function row_select( string $key, string $label, array $choices, string $current, bool $required = false ): void {
		$id = 'rc-' . $key;
		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . ( $required ? ' <span class="required">*</span>' : '' ) . '</label></th><td>';
		echo '<select id="' . esc_attr( $id ) . '" name="review[' . esc_attr( $key ) . ']"' . ( $required ? ' required' : '' ) . '>';
		if ( $required ) {
			echo '<option value="">' . esc_html__( '– bitte wählen –', 'review-captain' ) . '</option>';
		}
		foreach ( $choices as $value => $text ) {
			echo '<option value="' . esc_attr( (string) $value ) . '"' . selected( (string) $value, $current, false ) . '>' . esc_html( (string) $text ) . '</option>';
		}
		echo '</select></td></tr>';
	}

	/**
	 * Row: context selection.
	 *
	 * @param string $type Context type.
	 * @param string $id   Context ID.
	 */
	private function row_context( string $type, string $id ): void {
		echo '<tr><th scope="row"><label for="rc-context_type">' . esc_html__( 'Bezug', 'review-captain' ) . '</label></th><td>';
		echo '<select id="rc-context_type" name="review[context_type]">';
		foreach ( self::context_choices() as $value => $text ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $value, $type, false ) . '>' . esc_html( $text ) . '</option>';
		}
		echo '</select> <label class="screen-reader-text" for="rc-context_id">' . esc_html__( 'Bezugs-ID', 'review-captain' ) . '</label>';
		echo '<input type="text" id="rc-context_id" name="review[context_id]" class="regular-text" value="' . esc_attr( $id ) . '" placeholder="' . esc_attr__( 'ID, z. B. Beitrags- oder Produkt-ID', 'review-captain' ) . '" />';
		echo '<p class="description">' . esc_html__( 'Optional: Ordnet den Eintrag einer Seite, einem Produkt oder einer Veranstaltung zu (für Widgets mit Filter).', 'review-captain' ) . '</p></td></tr>';
	}

	/**
	 * Row: read-only value.
	 *
	 * @param string $label Label.
	 * @param string $value Value.
	 */
	private function row_static( string $label, string $value ): void {
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
	}
}
