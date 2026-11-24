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
use ReviewCaptain\Security\PowCaptcha;
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
			$cards[] = $this->render_card( $review, $d );
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
				'id'           => $id,
				'classes'      => implode( ' ', $classes ),
				'layout'       => $d->layout,
				'columns'      => $d->columns,
				'cards'        => $cards,
				'summary'      => $summary,
				'empty_text'   => '' !== $empty_text ? $empty_text : __( 'Es sind noch keine Bewertungen vorhanden.', 'review-captain' ),
				'pagination'   => $d->paginate ? $this->pagination( $d, $page, count( $reviews ) ) : array(),
				'notice'       => 1 === $this->instances['widget'] ? self::report_notice( PublicTexts::get( $settings, 'submissions', 'report_confirmation' ) ) : '',
				'filter_notice' => $this->filter_notice( $d ),
				'verification' => $this->verification_notice( 'widget' ),
			)
		);
	}

	/**
	 * Verification notice "So prüfen wir Bewertungen" (SET-RC-01) for a widget or form.
	 *
	 * Empty setting = neutral draft that names only the levels occurring on this site (the form
	 * always adds "unverified", because submissions are unverified). Legal review pending.
	 *
	 * @param string $context widget|form.
	 * @return array{html: string, position: string}
	 */
	public function verification_notice( string $context ): array {
		$settings = $this->plugin->settings();
		$position = 'above' === $settings->get( 'display', 'verification_notice_position', 'below' ) ? 'above' : 'below';
		if ( ! $settings->get( 'display', 'verification_notice_show', true ) ) {
			return array(
				'html'     => '',
				'position' => $position,
			);
		}
		$levels = $this->plugin->reviews()->levels_in_use();
		if ( 'form' === $context ) {
			// With "only buyers" (SET-RC-32) new submissions carry a confirmed level.
			$levels[] = ( new PurchaseGate( $this->plugin ) )->required() ? Verification::PURCHASE_VERIFIED->value : Verification::UNVERIFIED->value;
		}
		$levels = array_values( array_unique( $levels ) );
		$text   = trim( (string) $settings->get( 'display', 'verification_notice', '' ) );
		if ( '' === $text ) {
			$text = PublicTexts::default_verification_notice( $settings, $levels );
		}
		/**
		 * Filter the verification notice text ("So prüfen wir Bewertungen"). Return '' to hide it.
		 *
		 * @param string       $text    Plain text (paragraphs separated by blank lines).
		 * @param string       $context widget|form.
		 * @param list<string> $levels  Levels occurring on this site (Verification values, "testimonial").
		 */
		$text = apply_filters( 'review_captain_verification_notice', $text, $context, $levels );
		$text = is_string( $text ) ? trim( $text ) : '';
		return array(
			'html'     => '' === $text ? '' : TemplateLoader::render(
				'verification-notice.php',
				array(
					'title' => PublicTexts::get( $settings, 'display', 'verification_notice_title' ),
					'html'  => wpautop( esc_html( $text ) ),
				)
			),
			'position' => $position,
		);
	}

	/**
	 * Render one review card (template review-card.php).
	 *
	 * SET-RC-12: the label "received in exchange for a benefit" cannot be hidden. If a theme
	 * template drops it, the label is inserted at the start of the card.
	 *
	 * @param Review           $r Review.
	 * @param WidgetDefinition $d Definition.
	 */
	public function render_card( Review $r, WidgetDefinition $d ): string {
		$data = $this->card_data( $r, $d );
		$html = TemplateLoader::render( 'review-card.php', $data );
		return self::ensure_incentivised_label( $html, (string) $data['incentivised'] );
	}

	/**
	 * Insert the benefit label into card HTML that lacks it (SET-RC-12).
	 *
	 * @param string $html  Rendered card.
	 * @param string $label Label text ('' = review not flagged).
	 */
	public static function ensure_incentivised_label( string $html, string $label ): string {
		if ( '' === $label || str_contains( $html, 'rc-badge--incentivised' ) ) {
			return $html;
		}
		$badge = '<p class="rc-badge rc-badge--incentivised">' . esc_html( $label ) . '</p>';
		$end   = strpos( $html, '>' );
		return false === $end ? $badge . $html : substr( $html, 0, $end + 1 ) . $badge . substr( $html, $end + 1 );
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

		// SET-RC-02: only the texts are configurable; the level comes from the stored review.
		$badges = array();
		if ( Kind::TESTIMONIAL === $r->kind ) {
			$badges[] = array(
				'class' => 'rc-badge rc-badge--testimonial',
				'text'  => PublicTexts::level_label( $settings, PublicTexts::TESTIMONIAL ),
			);
		}
		$level_class = match ( $r->verification ) {
			Verification::PURCHASE_VERIFIED    => 'rc-verified',
			Verification::ATTENDANCE_CONFIRMED => 'rc-verified rc-verified--attendance',
			Verification::EXTERNAL_PLATFORM    => 'rc-badge rc-badge--external',
			Verification::UNVERIFIED           => Kind::TESTIMONIAL !== $r->kind && $settings->get( 'display', 'show_unverified_label', false ) ? 'rc-badge rc-badge--unverified' : '',
		};
		if ( '' !== $level_class ) {
			$badges[] = array(
				'class' => $level_class,
				'text'  => PublicTexts::level_label( $settings, $r->verification->value ),
			);
		}

		// SET-RC-12: "received in exchange for a benefit" – always first, no switch.
		$incentivised = $r->incentivised ? PublicTexts::incentivised_label( $settings ) : '';
		if ( '' !== $incentivised ) {
			array_unshift(
				$badges,
				array(
					'class' => 'rc-badge rc-badge--incentivised',
					'text'  => $incentivised,
				)
			);
		}

		// SET-RC-13: merchant reply to an own review (never for platform reviews, see E4).
		$reply = null;
		if ( ! $external && $r->has_reply() && $settings->get( 'display', 'show_replies', true ) ) {
			$reply_ts = null !== $r->reply_at ? strtotime( $r->reply_at . ' UTC' ) : false;
			$reply    = array(
				'label' => PublicTexts::reply_label( $settings ),
				'html'  => wpautop( esc_html( trim( (string) $r->reply_body ) ) ),
				'date'  => false === $reply_ts ? null : array(
					'iso'  => gmdate( 'c', $reply_ts ),
					'text' => wp_date( $this->date_format(), $reply_ts ),
				),
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
				// display.widget_new_tab / new_tab attribute also applies to the link to the original (SET-RC-P5).
				'link_attrs' => EmbedPolicy::external_link_attrs( $d->new_tab ),
				'new_tab'    => $d->new_tab,
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

		// RC-125A: approved author corrections are marked for readers (no misleading silent edits).
		$edited = null;
		if ( ! empty( $r->meta['edited_at'] ) && is_string( $r->meta['edited_at'] ) ) {
			$ts     = strtotime( $r->meta['edited_at'] . ' UTC' );
			$edited = false === $ts ? null : array(
				'iso'  => gmdate( 'c', $ts ),
				/* translators: %s: date */
				'text' => sprintf( __( 'bearbeitet am %s', 'review-captain' ), wp_date( $this->date_format(), $ts ) ),
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
				'reasons'      => ReportStore::enabled_reason_labels( $settings ),
				'pow'          => PowCaptcha::field( 'rc-report-' . $r->uuid, PowCaptcha::scope( 'report', array( 'rc_review' => $r->uuid ) ) ),
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
			'rating'    => $this->rating_data( $r, $show_stars, (string) $settings->get( 'display', 'rating_display', 'both' ) ),
			'author'    => $author,
			'avatar'    => $author && $settings->get( 'display', 'show_avatar', false ) ? mb_strtoupper( mb_substr( $author, 0, 1 ) ) : '',
			'date'      => $date,
			'edited'    => $edited,
			'source'    => $source,
			'badges'    => $badges,
			// Plain text of the benefit label ('' = not flagged); also in badges (SET-RC-12).
			'incentivised' => $incentivised,
			// {label, html, date{iso,text}|null} or null (SET-RC-13).
			'reply'     => $reply,
		);
	}

	/**
	 * Rating view data on the original scale.
	 *
	 * `display.rating_display` (SET-RC-22): stars|number|both. Stars exist only for 1–5 scales;
	 * without stars the number is always shown. The label for screen readers is always present.
	 *
	 * @param Review $r          Review.
	 * @param bool   $show_stars Whether to draw stars (display.show_stars).
	 * @param string $display    stars|number|both.
	 * @return array{label: string, value: string, stars: list<string>, show_value: bool}|null
	 */
	public function rating_data( Review $r, bool $show_stars = true, string $display = 'both' ): ?array {
		if ( null === $r->rating_value ) {
			return null;
		}
		$show_stars = $show_stars && 'number' !== $display;
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
			'label'      => $label,
			'value'      => $fmt( $value ) . ' / ' . $fmt( $max ),
			'stars'      => $stars,
			'show_value' => ! $stars || 'stars' !== $display,
		);
	}

	/**
	 * Format an average with `display.decimal_places` (0–2, SET-RC-22); whole numbers without decimals.
	 *
	 * @param float $average Average.
	 */
	public function format_average( float $average ): string {
		$places  = max( 0, min( 2, (int) $this->plugin->settings()->get( 'display', 'decimal_places', 1 ) ) );
		$rounded = round( $average, $places );
		return number_format_i18n( $rounded, floor( $rounded ) === $rounded ? 0 : $places );
	}

	/**
	 * Per-platform summary – separate rows, never a combined score (RC-114).
	 *
	 * @param WidgetDefinition $d Definition.
	 * @return list<array{platform: string, text: string}>
	 */
	public function summary( WidgetDefinition $d ): array {
		$args = $d->query_args();
		// P3: the average is never computed over a star-filtered selection (min_rating only narrows the
		// cards shown); a filtered average would overstate the rating (SET-RC-03, transparency).
		unset( $args['limit'], $args['orderby'], $args['order'], $args['min_normalized'] );
		$args['kind'] = 'review';
		$out          = array();
		foreach ( $this->plugin->reviews()->summary_by_platform( $args ) as $row ) {
			if ( null === $row['average'] || $row['count'] < 1 ) {
				continue;
			}
			$platform = Platform::tryFrom( $row['platform'] ) ?? Platform::OTHER;
			$avg      = $this->format_average( (float) $row['average'] );
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
	 * Notice for a filtered selection (SET-RC-03, owner decision E3): shown whenever a widget filters
	 * by minimum rating or platform. Text: display.filter_notice (placeholders {stars}, {platform}),
	 * empty = translated default for the case; `review_captain_public_text` ("display.filter_notice")
	 * has the last word. Legal review of the wording pending.
	 *
	 * @param WidgetDefinition $d Definition.
	 * @return string Plain text ('' = no filter or hidden by the filter).
	 */
	public function filter_notice( WidgetDefinition $d ): string {
		if ( ! $d->is_filtered() ) {
			return '';
		}
		$stars_filter = null !== $d->min_rating && $d->min_rating > 1.0;
		$stars        = $stars_filter ? (float) $d->min_rating : 1.0;
		$platforms    = array_map( static fn( string $p ): string => ( Platform::tryFrom( $p ) ?? Platform::OTHER )->label(), $d->platforms );
		$text         = trim( (string) $this->plugin->settings()->get( 'display', 'filter_notice', '' ) );
		if ( '' === $text ) {
			if ( $stars_filter && $platforms ) {
				$text = __( 'Es werden nur Bewertungen von {platform} ab {stars} Sternen angezeigt.', 'review-captain' );
			} elseif ( $stars_filter ) {
				$text = __( 'Es werden nur Bewertungen ab {stars} Sternen angezeigt.', 'review-captain' );
			} else {
				$text = __( 'Es werden nur Bewertungen von {platform} angezeigt.', 'review-captain' );
			}
		}
		/**
		 * Filter a configurable public text (see PublicTexts::get()); here the filter notice.
		 *
		 * @param string $text Text (setting or translated default, placeholders not yet replaced).
		 * @param string $id   "display.filter_notice".
		 */
		$filtered = apply_filters( 'review_captain_public_text', $text, 'display.filter_notice' );
		$text     = is_string( $filtered ) ? trim( $filtered ) : $text;
		return strtr(
			$text,
			array(
				'{stars}'    => number_format_i18n( $stars, floor( $stars ) === $stars ? 0 : 1 ),
				'{platform}' => $platforms ? implode( ', ', $platforms ) : __( 'allen Plattformen', 'review-captain' ),
			)
		);
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

		// SET-RC-32: only confirmed buyers/participants see the form; everybody else a clear message.
		$gate = new PurchaseGate( $this->plugin );
		if ( $gate->required() ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- token is checked by the trusted source.
			$invite = PurchaseGate::invite_token( wp_unslash( $_GET ) );
			$denied = '';
			if ( $items ) {
				$allowed = array();
				foreach ( $items as $item ) {
					$check = $gate->check( $type, $item['id'], $invite );
					if ( $check['ok'] ) {
						$allowed[] = $item;
					} else {
						$denied = $check['code'];
					}
				}
				$items = $allowed;
				if ( $items ) {
					$ids                          = array_map( static fn( array $i ): string => $i['id'], $items );
					$hidden['rc_context_ids']     = implode( ',', $ids );
					$hidden['rc_context_ids_sig'] = FormHandler::sign_context_list( $type, $ids );
					$denied                       = '';
				}
			} else {
				$check  = $gate->check( $type, $cid, $invite );
				$denied = $check['ok'] ? '' : $check['code'];
			}
			if ( '' !== $denied ) {
				return TemplateLoader::render(
					'review-form.php',
					array(
						'disabled' => true,
						'id'       => 'rc-form-' . $number,
						'message'  => FormHandler::error_message( $denied ),
					)
				);
			}
			if ( '' !== $invite ) {
				$hidden[ PurchaseGate::INVITE_FIELD ] = $invite;
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
				'consent_html'    => PublicTexts::consent_html( $settings ),
				'name_label'      => PublicTexts::get( $settings, 'submissions', 'name_label' ),
				'email_hint'      => PublicTexts::get( $settings, 'submissions', 'email_hint' ),
				'submit_label'    => PublicTexts::get( $settings, 'submissions', 'submit_label' ),
				'verification'    => $this->verification_notice( 'form' ),
				'privacy_notice'  => (string) $settings->get( 'privacy', 'privacy_notice', '' ),
				'min_length'      => FormHandler::text_limits( $settings )[0],
				'max_length'      => FormHandler::text_limits( $settings )[1],
				'success'         => $state['success'],
				'errors'          => $errors,
				'pow'             => PowCaptcha::field(
					'rc-form-' . $number,
					PowCaptcha::scope(
						'review',
						array(
							'rc_context_type' => $type,
							'rc_context_id'   => $cid,
						)
					)
				),
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
	 *
	 * @param string $confirmation Confirmation text (submissions.report_confirmation); '' = default.
	 */
	public static function report_notice( string $confirmation = '' ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$flag = isset( $_GET['rc_reported'] ) ? sanitize_key( wp_unslash( (string) $_GET['rc_reported'] ) ) : '';
		if ( '1' === $flag ) {
			return '' !== trim( $confirmation ) ? $confirmation : __( 'Danke für Ihre Meldung. Wir prüfen die Bewertung.', 'review-captain' );
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
		return (string) wp_date( $this->date_format(), $ts );
	}

	/**
	 * Absolute date format: general.date_format, else the WordPress date format (also for the
	 * "bearbeitet am" line, which never uses the relative style, review P2).
	 */
	private function date_format(): string {
		$format = trim( (string) $this->plugin->settings()->get( 'general', 'date_format', '' ) );
		if ( '' === $format ) {
			$format = (string) get_option( 'date_format', 'j. F Y' );
		}
		return '' !== $format ? $format : 'j. F Y';
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
