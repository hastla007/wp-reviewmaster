<?php
/**
 * Server-side rendering of widgets (RC-049, RC-052, RC-053, SEO-015, UX-005).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Frontend;

use ReviewCaptain\Domain\Kind;
use ReviewCaptain\Domain\Platform;
use ReviewCaptain\Domain\Review;
use ReviewCaptain\Domain\ReviewStatus;
use ReviewCaptain\Domain\Verification;
use ReviewCaptain\Media\PhotoStore;
use ReviewCaptain\Moderation\ReportHandler;
use ReviewCaptain\Moderation\ReportStore;
use ReviewCaptain\Plugin;
use ReviewCaptain\Security\Capabilities;
use ReviewCaptain\Security\EmbedPolicy;
use ReviewCaptain\Seo\VisibleReviews;
use ReviewCaptain\Widgets\WidgetDefinition;
use ReviewCaptain\Widgets\WidgetRegistry;

/**
 * Renders reviews widgets, the submission form and the CTA. Output works without JavaScript.
 */
class Renderer {

	/**
	 * Plugin.
	 */
	private Plugin $plugin;

	/**
	 * Collector for SEO (E5).
	 */
	private VisibleReviews $visible;

	/**
	 * Profile lookup.
	 */
	private ProfileLookup $profiles;

	/**
	 * Forced preview state (null = detect).
	 */
	private ?bool $preview = null;

	/**
	 * Instance counters per widget type.
	 *
	 * @var array<string, int>
	 */
	private array $instances = array();

	/**
	 * Constructor.
	 *
	 * @param Plugin             $plugin   Plugin.
	 * @param VisibleReviews     $visible  Visible collector.
	 * @param ProfileLookup|null $profiles Profiles.
	 */
	public function __construct( Plugin $plugin, VisibleReviews $visible, ?ProfileLookup $profiles = null ) {
		$this->plugin   = $plugin;
		$this->visible  = $visible;
		$this->profiles = $profiles ?? new ProfileLookup();
	}

	/**
	 * Register the core widget types.
	 *
	 * @param WidgetRegistry $registry Registry.
	 */
	public function register_types( WidgetRegistry $registry ): void {
		$registry->register_core( 'reviews', array( $this, 'render_reviews' ), __( 'Bewertungen (Raster/Liste)', 'review-captain' ) );
		$registry->register_core( 'form', array( $this, 'render_form' ), __( 'Bewertungsformular', 'review-captain' ) );
		$registry->register_core( 'cta', array( $this, 'render_cta' ), __( 'Bewertungs-Aufruf (CTA)', 'review-captain' ) );
	}

	/**
	 * Force preview mode (editor/REST); null = autodetect.
	 *
	 * @param bool|null $preview Preview.
	 */
	public function set_preview( ?bool $preview ): void {
		$this->preview = $preview;
	}

	/**
	 * Whether this request is an editor/REST/admin preview (no SEO collection, E5).
	 */
	public function is_preview(): bool {
		if ( null !== $this->preview ) {
			return $this->preview;
		}
		if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return true;
		}
		if ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) {
			return true;
		}
		return function_exists( 'is_preview' ) && is_preview();
	}

	/**
	 * Reset instance counters (tests).
	 */
	public function reset_instances(): void {
		$this->instances = array();
	}

	/**
	 * Current page of a paginated widget (rc_page ≥ 2, otherwise 1).
	 */
	public static function current_page(): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination.
		$raw = isset( $_GET['rc_page'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['rc_page'] ) ) : '';
		return ctype_digit( $raw ) && (int) $raw >= 2 && (int) $raw <= 1000 ? (int) $raw : 1;
	}

	/**
	 * Select the approved reviews a definition shows on a page. Shared with SEO priming.
	 *
	 * @param WidgetDefinition $d    Definition.
	 * @param int              $page Page.
	 * @return list<Review>
	 */
	public function select_reviews( WidgetDefinition $d, int $page = 1 ): array {
		$args           = $d->query_args();
		$args['offset'] = ( max( 1, $page ) - 1 ) * $d->limit;
		$reviews        = $this->plugin->reviews()->query( $args );
		// Defence in depth: never output anything not approved.
		return array_values( array_filter( $reviews, static fn( Review $r ): bool => ReviewStatus::APPROVED === $r->status && null === $r->removed_at ) );
	}

	/**
	 * Render the reviews widget (grid|list).
	 *
	 * @param WidgetDefinition $d Definition.
	 */
	public function render_reviews( WidgetDefinition $d ): string {
		$settings = $this->plugin->settings();
		$id       = $this->next_id( 'widget' );
		$page     = $d->paginate ? self::current_page() : 1;
		$reviews  = $this->select_reviews( $d, $page );
		$preview  = $this->is_preview();

		if ( $settings->get( 'display', 'show_photos', false ) ) {
			PhotoStore::prime( array_map( static fn( Review $r ): int => (int) $r->id, $reviews ) );
		}
		$cards = array();
		foreach ( $reviews as $review ) {
			$cards[] = TemplateLoader::render( 'review-card.php', $this->card_data( $review, $d ) );
			if ( ! $preview ) {
				$this->visible->add( $review, $id );
			}
		}

		$summary = array();
		if ( $d->show_summary && 'testimonial' !== $d->kind && $reviews ) {
			$summary = $this->summary( $d );
		}

		$empty_text = trim( (string) $settings->get( 'display', 'empty_text', '' ) );
		$classes    = array( 'rc-widget', 'rc-widget--' . $d->layout );
		if ( $settings->get( 'display', 'reduced_motion', true ) ) {
			$classes[] = 'rc-widget--reduced-motion';
		}

		$this->enqueue_style();
		return TemplateLoader::render(
			'reviews-grid.php',
			array(
				'id'         => $id,
				'classes'    => implode( ' ', $classes ),
				'layout'     => $d->layout,
				'columns'    => $d->columns,
				'cards'      => $cards,
				'summary'    => $summary,
				'empty_text' => '' !== $empty_text ? $empty_text : __( 'Es sind noch keine Bewertungen vorhanden.', 'review-captain' ),
				'pagination' => $d->paginate ? $this->pagination( $d, $page, count( $reviews ) ) : array(),
				'notice'     => 1 === $this->instances['widget'] ? self::report_notice() : '',
			)
		);
	}

	/**
	 * Prepared data for one review card.
	 *
	 * @param Review           $r Review.
	 * @param WidgetDefinition $d Definition.
	 * @return array<string, mixed>
	 */
	public function card_data( Review $r, WidgetDefinition $d ): array {
		$settings    = $this->plugin->settings();
		$external    = $r->platform->is_external();
		$show_author = $d->show_author ?? (bool) $settings->get( 'display', 'show_author', true );
		$show_date   = $d->show_date ?? (bool) $settings->get( 'display', 'show_date', true );
		$show_stars  = (bool) $settings->get( 'display', 'show_stars', true );

		$classes = array( 'rc-card', 'rc-card--' . $r->kind->value, $external ? 'rc-card--external' : 'rc-card--local' );

		$badges = array();
		if ( Kind::TESTIMONIAL === $r->kind ) {
			$badges[] = array(
				'class' => 'rc-badge rc-badge--testimonial',
				'text'  => __( 'Erfahrungsbericht dieser Website', 'review-captain' ),
			);
		}
		if ( Verification::PURCHASE_VERIFIED === $r->verification ) {
			$badges[] = array(
				'class' => 'rc-verified',
				'text'  => __( 'Verifizierter Kauf', 'review-captain' ),
			);
		}

		// Source: external records ALWAYS show label + link to the original (SET-014, RC-043).
		$source = null;
		if ( $external || $d->show_source ) {
			$url    = null !== $r->source_url && ProfileLookup::is_https_url( $r->source_url ) ? $r->source_url : '';
			$source = array(
				'platform'   => $r->platform->value,
				'label'      => $external
					/* translators: %s: platform name, e.g. Google */
					? sprintf( __( 'via %s', 'review-captain' ), $r->platform->label() )
					: __( 'Bewertung auf dieser Website', 'review-captain' ),
				'url'        => $external ? $url : '',
				/* translators: %s: platform name */
				'link_text'  => sprintf( __( 'Original auf %s ansehen', 'review-captain' ), $r->platform->label() ),
				'link_attrs' => EmbedPolicy::external_link_attrs(),
			);
		}

		$date = null;
		if ( $show_date ) {
			$raw = $r->published_at ?? ( '' !== $r->created_at ? $r->created_at : null );
			$ts  = null !== $raw ? strtotime( $raw . ' UTC' ) : false;
			if ( false !== $ts ) {
				$date = array(
					'iso'  => gmdate( 'c', $ts ),
					'text' => $this->format_date( $ts ),
				);
			}
		}

		$author = '';
		if ( $show_author ) {
			$author = trim( $r->author_name );
		}

		$photo = null;
		if ( null !== $r->id && $settings->get( 'display', 'show_photos', false ) && ReviewStatus::APPROVED === $r->status && null !== PhotoStore::for_review( $r->id ) ) {
			$media = (array) PhotoStore::for_review( $r->id );
			$photo = array(
				'url'    => PhotoStore::url( $r->uuid ),
				'width'  => (int) ( $media['width'] ?? 0 ),
				'height' => (int) ( $media['height'] ?? 0 ),
				'alt'    => '' !== $author
					/* translators: %s: author name */
					? sprintf( __( 'Foto zur Bewertung von %s', 'review-captain' ), $author )
					: __( 'Foto zur Bewertung', 'review-captain' ),
			);
		}

		$report = null;
		if ( $settings->get( 'submissions', 'reports_enabled', false ) && ReviewStatus::APPROVED === $r->status ) {
			$report = array(
				'action'       => admin_url( 'admin-post.php' ),
				'hidden'       => array(
					'action'    => ReportHandler::ACTION,
					'rc_review' => $r->uuid,
					'rc_return' => self::current_url(),
				),
				'nonce_action' => ReportHandler::NONCE_ACTION,
				'nonce_name'   => ReportHandler::NONCE_FIELD,
				'reasons'      => ReportStore::reason_labels(),
			);
		}

		return array(
			'uuid'      => $r->uuid,
			'photo'     => $photo,
			'heading'   => 'h' . max( 2, min( 6, $d->heading_level ) ),
			'report'    => $report,
			'classes'   => implode( ' ', $classes ),
			'kind'      => $r->kind->value,
			'external'  => $external,
			'title'     => trim( $r->title ),
			'body_html' => '' !== trim( $r->body ) ? wpautop( esc_html( trim( $r->body ) ) ) : '',
			'allowed'   => array(
				'p'  => array(),
				'br' => array(),
			),
			'rating'    => $this->rating_data( $r, $show_stars ),
			'author'    => $author,
			'avatar'    => $author && $settings->get( 'display', 'show_avatar', false ) ? mb_strtoupper( mb_substr( $author, 0, 1 ) ) : '',
			'date'      => $date,
			'source'    => $source,
			'badges'    => $badges,
		);
	}

	/**
	 * Rating view data on the original scale.
	 *
	 * @param Review $r          Review.
	 * @param bool   $show_stars Whether to draw stars.
	 * @return array{label: string, value: string, stars: list<string>}|null
	 */
	public function rating_data( Review $r, bool $show_stars = true ): ?array {
		if ( null === $r->rating_value ) {
			return null;
		}
		$value = $r->rating_value;
		$max   = $r->rating_scale_max;
		$fmt   = static fn( float $v ): string => number_format_i18n( $v, floor( $v ) === $v ? 0 : 1 );
		$stars = array();
		$five  = 5.0 === $max && $r->rating_scale_min <= 1.0;
		if ( $five ) {
			/* translators: 1: rating value, 2: scale maximum */
			$label = sprintf( __( '%1$s von %2$s Sternen', 'review-captain' ), $fmt( $value ), $fmt( $max ) );
			if ( $show_stars ) {
				$rounded = round( $value * 2 ) / 2;
				for ( $i = 1; $i <= 5; $i++ ) {
					$stars[] = $rounded >= $i ? 'full' : ( $rounded >= $i - 0.5 ? 'half' : 'empty' );
				}
			}
		} else {
			// Other scales are never converted into stars.
			/* translators: 1: rating value, 2: scale maximum */
			$label = sprintf( __( '%1$s von %2$s Punkten', 'review-captain' ), $fmt( $value ), $fmt( $max ) );
		}
		return array(
			'label' => $label,
			'value' => $fmt( $value ) . ' / ' . $fmt( $max ),
			'stars' => $stars,
		);
	}

	/**
	 * Per-platform summary – separate rows, never a combined score (RC-114).
	 *
	 * @param WidgetDefinition $d Definition.
	 * @return list<array{platform: string, text: string}>
	 */
	public function summary( WidgetDefinition $d ): array {
		$args = $d->query_args();
		unset( $args['limit'], $args['orderby'], $args['order'] );
		$args['kind'] = 'review';
		$out          = array();
		foreach ( $this->plugin->reviews()->summary_by_platform( $args ) as $row ) {
			if ( null === $row['average'] || $row['count'] < 1 ) {
				continue;
			}
			$platform = Platform::tryFrom( $row['platform'] ) ?? Platform::OTHER;
			$avg      = number_format_i18n( $row['average'], floor( $row['average'] ) === $row['average'] ? 0 : 1 );
			$out[]    = array(
				'platform' => $platform->value,
				'text'     => sprintf(
					/* translators: 1: platform name, 2: average, 3: scale max, 4: number of reviews */
					_n( '%1$s: %2$s von %3$s (%4$s Bewertung)', '%1$s: %2$s von %3$s (%4$s Bewertungen)', $row['count'], 'review-captain' ),
					$platform->label(),
					$avg,
					number_format_i18n( $row['scale_max'], 0 ),
					number_format_i18n( $row['count'] )
				),
			);
		}
		return $out;
	}

	/**
	 * Render the submission form (RC-039).
	 *
	 * @param WidgetDefinition $d Definition (context_type/context_id used).
	 */
	public function render_form( WidgetDefinition $d ): string {
		$settings = $this->plugin->settings();
		$number   = $this->next_number( 'form' );
		$this->enqueue_style();
		if ( ! $settings->get( 'submissions', 'enabled', true ) ) {
			return TemplateLoader::render(
				'review-form.php',
				array(
					'disabled' => true,
					'id'       => 'rc-form-' . $number,
					'message'  => __( 'Bewertungen können derzeit nicht abgegeben werden.', 'review-captain' ),
				)
			);
		}

		$state   = FormHandler::request_state( $number );
		$draft   = $state['draft'];
		$user    = is_user_logged_in() ? wp_get_current_user() : null;
		$type    = (string) ( $d->context_type ?? '' );
		$cid     = (string) ( $d->context_id ?? '' );
		$errors  = array();
		foreach ( $state['errors'] as $code ) {
			$errors[ $code ] = FormHandler::error_message( $code );
		}

		// Multi-product form (RC-123): context_ids="12,13" with context_type, signed list.
		$items  = array();
		$hidden = array();
		if ( '' !== $type && $settings->get( 'submissions', 'multi_product_enabled', false ) && '' !== (string) ( $d->raw['context_ids'] ?? '' ) ) {
			$ids = FormHandler::parse_context_list( (string) $d->raw['context_ids'] );
			foreach ( $ids as $item_id ) {
				$items[] = array(
					'id'    => $item_id,
					'label' => self::context_label( $type, $item_id ),
				);
			}
			if ( $ids ) {
				$cid    = '';
				$hidden = array(
					'rc_context_ids'     => implode( ',', $ids ),
					'rc_context_ids_sig' => FormHandler::sign_context_list( $type, $ids ),
				);
			}
		}
		$photos = ! $items && (bool) $settings->get( 'submissions', 'photos_enabled', false );

		return TemplateLoader::render(
			'review-form.php',
			array(
				'disabled'        => false,
				'id'              => 'rc-form-' . $number,
				'number'          => $number,
				'action'          => admin_url( 'admin-post.php' ),
				'hidden'          => array(
					'action'          => FormHandler::ACTION,
					'rc_form'         => (string) $number,
					'rc_ts'           => FormHandler::sign_timestamp( time() ),
					'rc_context_type' => $type,
					'rc_context_id'   => $cid,
					'rc_context_sig'  => FormHandler::sign_context( $type, $cid ),
					'rc_return'       => self::current_url(),
				) + $hidden,
				'items'           => $items,
				'photos'          => $photos,
				'photo_max_kb'    => (int) $settings->get( 'submissions', 'photo_max_kb', 2048 ),
				'nonce_action'    => FormHandler::NONCE_ACTION,
				'nonce_name'      => FormHandler::NONCE_FIELD,
				'honeypot'        => (bool) $settings->get( 'submissions', 'honeypot', true ),
				'honeypot_name'   => FormHandler::HONEYPOT_FIELD,
				'allow_title'     => (bool) $settings->get( 'submissions', 'allow_title', true ),
				'require_consent' => (bool) $settings->get( 'submissions', 'require_consent', true ),
				'privacy_notice'  => (string) $settings->get( 'privacy', 'privacy_notice', '' ),
				'min_length'      => (int) $settings->get( 'submissions', 'min_text_length', 20 ),
				'max_length'      => (int) $settings->get( 'submissions', 'max_text_length', 5000 ),
				'success'         => $state['success'],
				'errors'          => $errors,
				'values'          => array(
					'rating' => (string) ( $draft['rating'] ?? '' ),
					'title'  => (string) ( $draft['title'] ?? '' ),
					'body'   => (string) ( $draft['body'] ?? '' ),
					'name'   => (string) ( $draft['name'] ?? ( $user ? $user->display_name : '' ) ),
					// Never prefill the e-mail: cached pages must not leak it (security review SR-07).
					'email'  => '',
				),
			)
		);
	}

	/**
	 * Human readable label of a context item in a multi-product form.
	 *
	 * @param string $type Context type.
	 * @param string $id   Context ID.
	 */
	public static function context_label( string $type, string $id ): string {
		if ( in_array( $type, array( 'post', 'product', 'event', 'voucher', 'course' ), true ) && ctype_digit( $id ) ) {
			$title = trim( wp_strip_all_tags( (string) get_the_title( (int) $id ) ) );
			if ( '' !== $title ) {
				return $title;
			}
		}
		/* translators: %s: context ID */
		return sprintf( __( 'Produkt %s', 'review-captain' ), $id );
	}

	/**
	 * Status message after a public report (redirect parameter rc_reported).
	 */
	public static function report_notice(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$flag = isset( $_GET['rc_reported'] ) ? sanitize_key( wp_unslash( (string) $_GET['rc_reported'] ) ) : '';
		if ( '1' === $flag ) {
			return __( 'Danke für Ihre Meldung. Wir prüfen die Bewertung.', 'review-captain' );
		}
		if ( 'error' === $flag ) {
			return __( 'Die Meldung konnte nicht übermittelt werden. Bitte versuchen Sie es später erneut.', 'review-captain' );
		}
		return '';
	}

	/**
	 * Render the neutral review CTA (RC-054). No star preselection, no gating.
	 *
	 * @param WidgetDefinition $d Definition.
	 */
	public function render_cta( WidgetDefinition $d ): string {
		$profile = null !== $d->profile_id ? $this->profiles->find( $d->profile_id ) : null;
		if ( null === $profile || Platform::LOCAL->value === $profile['platform'] ) {
			if ( current_user_can( Capabilities::MODERATE ) ) {
				return '<p class="rc-cta rc-cta--missing">' . esc_html__( 'Review Captain: Das Bewertungsprofil für diesen Aufruf wurde nicht gefunden oder hat keine gültige https-Adresse.', 'review-captain' ) . '</p>';
			}
			return WidgetRegistry::comment( 'Review Captain: CTA-Profil nicht verfügbar.' );
		}
		$platform = Platform::tryFrom( $profile['platform'] ) ?? Platform::OTHER;
		$name     = Platform::OTHER === $platform && '' !== $profile['label'] ? $profile['label'] : $platform->label();
		$this->enqueue_style();
		return TemplateLoader::render(
			'cta.php',
			array(
				'platform' => $platform->value,
				'url'      => $profile['url'],
				/* translators: %s: platform name, e.g. Google */
				'text'     => sprintf( __( 'Bewerte uns auf %s', 'review-captain' ), $name ),
				'new_tab'  => $d->new_tab,
				// Operator content → no "ugc" (embed-security.md §5).
				'rel'      => 'nofollow noopener noreferrer',
			)
		);
	}

	/**
	 * Enqueue frontend CSS when allowed (display.load_css).
	 */
	public function enqueue_style(): void {
		if ( ! $this->plugin->settings()->get( 'display', 'load_css', true ) ) {
			return;
		}
		FrontendModule::register_assets();
		wp_enqueue_style( FrontendModule::STYLE_HANDLE );
	}

	/**
	 * Current request URL (validated again on submit).
	 */
	public static function current_url(): string {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) ) : '';
		$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_HOST'] ) ) : '';
		if ( '' === $uri || '' === $host || ! str_starts_with( $uri, '/' ) ) {
			$id = (int) get_the_ID();
			return $id > 0 ? (string) get_permalink( $id ) : home_url( '/' );
		}
		return ( is_ssl() ? 'https://' : 'http://' ) . $host . $uri;
	}

	/**
	 * Pagination links.
	 *
	 * @param WidgetDefinition $d     Definition.
	 * @param int              $page  Current page.
	 * @param int              $shown Number of reviews shown.
	 * @return array{prev: string, next: string}
	 */
	private function pagination( WidgetDefinition $d, int $page, int $shown ): array {
		$total = $this->plugin->reviews()->count( $d->query_args() );
		$base  = remove_query_arg( array( 'rc_page', 'rc_submitted', 'rc_error', 'rc_form', 'rc_draft' ), self::current_url() );
		$prev  = '';
		$next  = '';
		if ( $page > 1 ) {
			$prev = 2 === $page ? $base : add_query_arg( 'rc_page', $page - 1, $base );
		}
		if ( $shown > 0 && $page * $d->limit < $total ) {
			$next = add_query_arg( 'rc_page', $page + 1, $base );
		}
		return array(
			'prev' => $prev,
			'next' => $next,
		);
	}

	/**
	 * Format a timestamp according to display.date_style.
	 *
	 * @param int $ts Timestamp.
	 */
	private function format_date( int $ts ): string {
		$settings = $this->plugin->settings();
		if ( 'relative' === $settings->get( 'display', 'date_style', 'absolute' ) ) {
			/* translators: %s: human readable time difference */
			return sprintf( __( 'vor %s', 'review-captain' ), human_time_diff( $ts, time() ) );
		}
		$format = trim( (string) $settings->get( 'general', 'date_format', '' ) );
		if ( '' === $format ) {
			$format = (string) get_option( 'date_format', 'j. F Y' );
		}
		return (string) wp_date( '' !== $format ? $format : 'j. F Y', $ts );
	}

	/**
	 * Next HTML ID for a type.
	 *
	 * @param string $type Type.
	 */
	private function next_id( string $type ): string {
		return 'rc-' . $type . '-' . $this->next_number( $type );
	}

	/**
	 * Next instance number.
	 *
	 * @param string $type Type.
	 */
	private function next_number( string $type ): int {
		$this->instances[ $type ] = ( $this->instances[ $type ] ?? 0 ) + 1;
		return $this->instances[ $type ];
	}
}
