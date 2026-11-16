<?php
/**
 * Versioned shortcode API (RC-051): [review_captain_widget], [review_captain_form], [review_captain_cta].
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Frontend;

use ReviewCaptain\Profiles\ProfileRepository;
use ReviewCaptain\Widgets\WidgetRepository;

defined( 'ABSPATH' ) || exit;

final class Shortcodes {

	public const VERSION = 1;

	public static function register(): void {
		add_shortcode( 'review_captain_widget', array( self::class, 'widget' ) );
		add_shortcode( 'review_captain_form', array( self::class, 'form' ) );
		add_shortcode( 'review_captain_cta', array( self::class, 'cta' ) );
	}

	/**
	 * [review_captain_widget id="12"] or inline: [review_captain_widget layout="list" providers="site,google" limit="4" rating_min="4" context_type="product" context_id="42"]
	 *
	 * @param array<string,string>|string $atts Attributes.
	 */
	public static function widget( $atts ): string {
		$atts = shortcode_atts(
			array(
				'v'            => (string) self::VERSION,
				'id'           => '',
				'layout'       => 'grid',
				'providers'    => '',
				'types'        => '',
				'limit'        => '',
				'rating_min'   => '',
				'columns'      => '',
				'context_type' => '',
				'context_id'   => '',
				'testimonials' => '',
			),
			(array) $atts,
			'review_captain_widget'
		);
		if ( (int) $atts['v'] > self::VERSION ) {
			return '';
		}
		$id = absint( $atts['id'] );
		if ( $id ) {
			$widget = WidgetRepository::find( $id );
			if ( ! $widget || 'active' !== $widget['status'] ) {
				return '';
			}
			return Renderer::widget( $widget['config'], (string) $widget['layout'], 'rc-widget-' . $id );
		}
		$config = array(
			'providers'            => '' !== $atts['providers'] ? array_map( 'trim', explode( ',', $atts['providers'] ) ) : array(),
			'types'                => '' !== $atts['types'] ? array_map( 'trim', explode( ',', $atts['types'] ) ) : array(),
			'limit'                => '' !== $atts['limit'] ? (int) $atts['limit'] : 6,
			'rating_min'           => (int) $atts['rating_min'],
			'columns'              => (int) $atts['columns'],
			'context_type'         => $atts['context_type'],
			'context_id'           => $atts['context_id'],
			'include_testimonials' => in_array( strtolower( $atts['testimonials'] ), array( '1', 'yes', 'true' ), true ),
		);
		return Renderer::widget( $config, sanitize_key( $atts['layout'] ) );
	}

	/**
	 * @param array<string,string>|string $atts Attributes.
	 */
	public static function form( $atts ): string {
		$atts = shortcode_atts(
			array(
				'context_type' => '',
				'context_id'   => '',
				'type'         => 'general',
			),
			(array) $atts,
			'review_captain_form'
		);
		return SubmissionForm::render( $atts );
	}

	/**
	 * @param array<string,string>|string $atts Attributes.
	 */
	public static function cta( $atts ): string {
		$atts     = shortcode_atts(
			array(
				'profiles' => '',
				'text'     => '',
			),
			(array) $atts,
			'review_captain_cta'
		);
		$ids      = array_filter( array_map( 'absint', explode( ',', $atts['profiles'] ) ) );
		$profiles = array_filter(
			ProfileRepository::all(),
			static function ( $p ) use ( $ids ) {
				return ! empty( $p['public_url'] ) && ( ! $ids || in_array( (int) $p['id'], $ids, true ) );
			}
		);
		return Renderer::cta( array_values( $profiles ), sanitize_text_field( $atts['text'] ) );
	}
}
