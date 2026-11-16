<?php
/**
 * Server-side rendering of review cards, grid/list widgets, testimonials and CTA (RC-049, RC-052, RC-053, RC-054).
 * Output works without JavaScript; all user content is escaped.
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Frontend;

use ReviewCaptain\Reviews\ReviewRepository;
use ReviewCaptain\Reviews\ReviewService;
use ReviewCaptain\Seo\SchemaOutput;
use ReviewCaptain\Settings\Settings;
use ReviewCaptain\Testimonials\TestimonialRepository;
use ReviewCaptain\Widgets\WidgetRepository;

defined( 'ABSPATH' ) || exit;

final class Renderer {

	/**
	 * Render a widget from a stored definition or an inline config.
	 *
	 * @param array<string,mixed> $config Widget config (see WidgetRepository::default_config()).
	 */
	public static function widget( array $config, string $layout = 'grid', string $instance = '' ): string {
		$config = WidgetRepository::sanitize_config( $config );
		/**
		 * Pro layouts render themselves. Return a non-null string to short-circuit.
		 *
		 * @param string|null         $html   Rendered HTML.
		 * @param string              $layout Layout key.
		 * @param array<string,mixed> $config Config.
		 */
		$custom = apply_filters( 'review_captain_render_layout', null, $layout, $config );
		if ( is_string( $custom ) ) {
			return $custom;
		}
		if ( ! in_array( $layout, WidgetRepository::CORE_LAYOUTS, true ) ) {
			$layout = 'grid'; // Fallback when a Pro layout is unavailable (license expired).
		}

		$cache_key = 'w_' . md5( wp_json_encode( array( $config, $layout, get_option( 'review_captain_cache_version', 1 ), determine_locale() ) ) );
		$cached    = wp_cache_get( $cache_key, 'review_captain' );
		if ( is_array( $cached ) ) {
			SchemaOutput::collect( $cached['schema'] );
			Assets::enqueue_frontend();
			return $cached['html'];
		}

		$filters = array( 'public' => true );
		if ( $config['providers'] ) {
			$filters['provider'] = $config['providers'];
		}
		if ( $config['types'] ) {
			$filters['type'] = $config['types'];
		}
		if ( $config['rating_min'] > 0 ) {
			$filters['rating_min'] = $config['rating_min'];
		}
		if ( '' !== $config['context_type'] ) {
			$filters['context_type'] = $config['context_type'];
			if ( '' !== $config['context_id'] ) {
				$filters['context_id'] = $config['context_id'];
			}
		}
		if ( $config['ids'] ) {
			$filters['ids'] = $config['ids'];
		}
		$reviews      = ReviewRepository::query( $filters, $config['limit'], 0, $config['order'] );
		$testimonials = $config['include_testimonials'] ? TestimonialRepository::publishable( $config['context_type'], $config['context_id'], $config['limit'] ) : array();

		if ( ! $reviews && ! $testimonials ) {
			$html = 'message' === Settings::get( 'empty_state' )
				? '<div class="rc-widget rc-widget--empty"><p>' . esc_html__( 'No reviews yet.', 'review-captain' ) . '</p></div>'
				: '';
			return $html;
		}

		$display = array(
			'show_date'   => null !== $config['show_date'] ? $config['show_date'] : (bool) Settings::get( 'show_date' ),
			'author_mode' => null !== $config['author_mode'] ? $config['author_mode'] : (string) Settings::get( 'author_mode' ),
			'show_avatar' => (bool) Settings::get( 'show_avatar' ),
		);

		$style = $config['columns'] > 0 ? ' style="--rc-columns:' . (int) $config['columns'] . '"' : '';
		$html  = '<div class="rc-widget rc-widget--' . esc_attr( $layout ) . '"' . ( $instance ? ' id="' . esc_attr( $instance ) . '"' : '' ) . $style . '>';
		if ( $config['show_summary'] && $reviews ) {
			$html .= self::summary( $filters );
		}
		$html .= '<ul class="rc-' . ( 'list' === $layout ? 'list' : 'grid' ) . '" role="list">';
		foreach ( $reviews as $review ) {
			$html .= '<li>' . self::card( $review, $display ) . '</li>';
		}
		foreach ( $testimonials as $t ) {
			$html .= '<li>' . self::testimonial( $t ) . '</li>';
		}
		$html .= '</ul></div>';

		$schema = SchemaOutput::eligible_reviews( $reviews );
		SchemaOutput::collect( $schema );
		wp_cache_set(
			$cache_key,
			array(
				'html'   => $html,
				'schema' => $schema,
			),
			'review_captain',
			15 * MINUTE_IN_SECONDS
		);
		Assets::enqueue_frontend();
		ReviewService::milestone( 'first_widget_published_at', ! is_admin() && ! wp_is_json_request() );
		return $html;
	}

	/**
	 * Per-source summary. Never a cross-platform average (RC-113/114).
	 *
	 * @param array<string,mixed> $filters Filters.
	 */
	public static function summary( array $filters ): string {
		unset( $filters['ids'] );
		$data = ReviewRepository::summary_by_provider( array_diff_key( $filters, array( 'public' => 1 ) ) );
		if ( ! $data ) {
			return '';
		}
		$html = '<div class="rc-summary">';
		foreach ( $data as $key => $row ) {
			$provider = explode( ':', $key )[0];
			$html    .= '<div class="rc-summary__item">';
			$html    .= '<span class="rc-source">' . esc_html( ReviewService::provider_label( $provider ) ) . '</span> ';
			if ( null !== $row['average'] ) {
				$html .= '<strong class="rc-summary__score">' . esc_html( number_format_i18n( $row['average'], 1 ) ) . '</strong>';
				/* translators: %d: maximum of the rating scale */
				$html .= ' <span class="rc-summary__scale">' . esc_html( sprintf( __( 'of %d', 'review-captain' ), $row['scale_max'] ) ) . '</span>';
			}
			/* translators: %s: number of reviews */
			$html .= ' <span class="rc-summary__count">' . esc_html( sprintf( _n( '%s review', '%s reviews', $row['count'], 'review-captain' ), number_format_i18n( $row['count'] ) ) ) . '</span>';
			$html .= '</div>';
		}
		return $html . '</div>';
	}

	/**
	 * One review card.
	 *
	 * @param array<string,mixed> $r       Review row.
	 * @param array<string,mixed> $display Display options.
	 */
	public static function card( array $r, array $display = array() ): string {
		$display  = wp_parse_args(
			$display,
			array(
				'show_date'   => true,
				'author_mode' => 'first_name_initial',
				'show_avatar' => false,
			)
		);
		$external = 'site' !== $r['provider'];
		$html     = '<article class="rc-card rc-review' . ( $external ? ' rc-review--external' : ' rc-review--own' ) . '">';
		$html    .= '<div class="rc-review__head">';
		if ( null !== $r['rating_value'] && '' !== $r['rating_value'] ) {
			$html .= self::stars( (float) $r['rating_value'], (int) $r['rating_min'], (int) $r['rating_max'] );
		}
		if ( 'purchase' === $r['verification'] ) {
			$html .= '<span class="rc-badge">' . esc_html__( 'Verified purchase', 'review-captain' ) . '</span>';
		} elseif ( 'attendance' === $r['verification'] ) {
			$html .= '<span class="rc-badge">' . esc_html__( 'Attendance confirmed', 'review-captain' ) . '</span>';
		}
		$html .= '</div>';
		if ( ! empty( $r['title'] ) ) {
			$html .= '<h3 class="rc-review__title">' . esc_html( $r['title'] ) . '</h3>';
		}
		if ( ! empty( $r['content'] ) ) {
			$html .= '<div class="rc-review__text">' . wpautop( esc_html( $r['content'] ) ) . '</div>';
		}
		$html .= '<footer class="rc-review__meta">';
		if ( $display['show_avatar'] && ! empty( $r['author_avatar_url'] ) && 'click_to_load' !== Settings::get( 'external_services_consent_mode' ) ) {
			$html .= '<img class="rc-avatar" src="' . esc_url( $r['author_avatar_url'] ) . '" alt="" width="32" height="32" loading="lazy" decoding="async">';
		}
		$name = self::author_name( (string) $r['author_name'], (string) $display['author_mode'] );
		if ( '' !== $name ) {
			$html .= '<span class="rc-review__author">' . esc_html( $name ) . '</span>';
		}
		$date = $r['published_at'] ?? $r['created_at'];
		if ( $display['show_date'] && $date ) {
			$ts    = strtotime( $date . ' UTC' );
			$html .= '<time datetime="' . esc_attr( gmdate( 'c', $ts ) ) . '">' . esc_html( wp_date( (string) Settings::get( 'date_format' ), $ts ) ) . '</time>';
		}
		// Source attribution is mandatory for external reviews (cannot be disabled).
		if ( $external ) {
			$label = ! empty( $r['attribution'] ) ? $r['attribution'] : ReviewService::provider_label( (string) $r['provider'] );
			if ( ! empty( $r['source_url'] ) ) {
				$html .= '<a class="rc-source" href="' . esc_url( $r['source_url'] ) . '" rel="nofollow noopener noreferrer">' . esc_html( $label ) . '</a>';
			} else {
				$html .= '<span class="rc-source">' . esc_html( $label ) . '</span>';
			}
		} else {
			$html .= '<span class="rc-badge rc-badge--own">' . esc_html__( 'on this website', 'review-captain' ) . '</span>';
		}
		$html .= '</footer>';
		if ( ! empty( $r['reply_text'] ) ) {
			$html .= '<div class="rc-review__reply"><strong>' . esc_html__( 'Reply from the owner', 'review-captain' ) . '</strong>' . wpautop( esc_html( $r['reply_text'] ) ) . '</div>';
		}
		return $html . '</article>';
	}

	/**
	 * Accessible stars: decorative glyphs plus text alternative. Non-5 scales are shown as number.
	 */
	public static function stars( float $value, int $min = 1, int $max = 5 ): string {
		/* translators: 1: rating, 2: scale maximum */
		$label = sprintf( __( '%1$s out of %2$d', 'review-captain' ), number_format_i18n( $value, floor( $value ) === $value ? 0 : 1 ), $max );
		if ( 1 !== $min || 5 !== $max ) {
			return '<span class="rc-rating">' . esc_html( $label ) . '</span>';
		}
		$full = (int) round( $value );
		$html = '<span class="rc-stars" aria-hidden="true">';
		for ( $i = 1; $i <= 5; $i++ ) {
			$html .= $i <= $full ? '★' : '<span class="rc-stars__off">★</span>';
		}
		return $html . '</span><span class="rc-sr">' . esc_html( $label ) . '</span>';
	}

	public static function author_name( string $name, string $mode ): string {
		$name = trim( $name );
		if ( '' === $name || 'anonymous' === $mode ) {
			return '';
		}
		$parts = preg_split( '/\s+/u', $name );
		switch ( $mode ) {
			case 'initials':
				return implode( '', array_map( static fn( $p ) => mb_strtoupper( mb_substr( $p, 0, 1 ) ) . '.', $parts ) );
			case 'first_name_initial':
				if ( count( $parts ) < 2 ) {
					return $parts[0];
				}
				return $parts[0] . ' ' . mb_strtoupper( mb_substr( (string) end( $parts ), 0, 1 ) ) . '.';
			case 'first_name':
				return $parts[0];
			default:
				return $name;
		}
	}

	/**
	 * Text testimonial, visibly distinguished from platform reviews (RC-053).
	 *
	 * @param array<string,mixed> $t Testimonial row.
	 */
	public static function testimonial( array $t ): string {
		$mode = 'first_name' === $t['display_name_mode'] ? 'first_name' : ( 'initials' === $t['display_name_mode'] ? 'initials' : ( 'anonymous' === $t['display_name_mode'] ? 'anonymous' : 'full' ) );
		$name = self::author_name( (string) $t['display_name'], $mode );
		$html = '<figure class="rc-card rc-testimonial"><blockquote class="rc-quote">' . wpautop( esc_html( (string) $t['quote'] ) ) . '</blockquote><figcaption class="rc-review__meta">';
		if ( '' !== $name ) {
			$html .= '<span class="rc-review__author">' . esc_html( $name ) . '</span>';
		}
		if ( ! empty( $t['role_label'] ) ) {
			$html .= '<span>' . esc_html( $t['role_label'] ) . '</span>';
		}
		$html .= '<span class="rc-badge rc-badge--own">' . esc_html__( 'Testimonial', 'review-captain' ) . '</span>';
		return $html . '</figcaption></figure>';
	}

	/**
	 * Neutral call to action to rate on an official profile (RC-054). No pre-selected stars, no gating.
	 *
	 * @param array<int,array<string,mixed>> $profiles Profile rows.
	 */
	public static function cta( array $profiles, string $text = '' ): string {
		if ( ! $profiles ) {
			return '';
		}
		Assets::enqueue_frontend();
		$text = '' !== $text ? $text : __( 'We would appreciate your honest review.', 'review-captain' );
		$html = '<div class="rc-cta"><p>' . esc_html( $text ) . '</p><div class="rc-cta__buttons">';
		foreach ( $profiles as $p ) {
			/* translators: %s: platform name */
			$label = sprintf( __( 'Review us on %s', 'review-captain' ), ReviewService::provider_label( (string) $p['provider'] ) );
			$html .= '<a class="rc-btn" href="' . esc_url( $p['public_url'] ) . '" rel="nofollow noopener noreferrer">' . esc_html( $label ) . '</a>';
		}
		return $html . '</div></div>';
	}
}
