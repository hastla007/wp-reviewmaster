<?php
/**
 * Widgets page: shortcode/block help with a server-side shortcode generator.
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Admin;

use ReviewCaptain\Domain\Platform;
use ReviewCaptain\Plugin;
use ReviewCaptain\Security\Capabilities;

/**
 * Builds `[review_captain v="1" …]` (widget contract v1). Works without JavaScript; the
 * optional admin.js only adds a copy button.
 */
final class WidgetsPage {

	public const SLUG = 'review-captain-widgets';

	/**
	 * Attribute defaults of the widget contract v1 (attributes equal to the default are omitted).
	 *
	 * @return array<string, string>
	 */
	public static function defaults(): array {
		return array(
			'layout'       => 'grid',
			'platform'     => '',
			'kind'         => 'review',
			'min_rating'   => '',
			'limit'        => '10',
			'context_type' => '',
			'context_id'   => '',
			'show_source'  => '1',
			'show_date'    => '1',
			'profile'      => '',
		);
	}

	/**
	 * Context types allowed by widget contract v1.
	 */
	public const CONTEXT_TYPES = array( 'post', 'product', 'event', 'voucher', 'app', 'form' );

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( private Plugin $plugin ) {}

	/**
	 * Sanitize generator input to attribute values (rules of docs/contracts/widget-v1.md).
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, string>
	 */
	public static function sanitize( array $input ): array {
		$d   = self::defaults();
		$out = $d;

		$layout        = sanitize_key( (string) ( $input['layout'] ?? '' ) );
		$out['layout'] = in_array( $layout, array( 'grid', 'list' ), true ) ? $layout : $d['layout'];

		$platforms = array();
		foreach ( self::csv( $input['platform'] ?? '' ) as $p ) {
			$p = sanitize_key( $p );
			if ( in_array( $p, Platform::values(), true ) ) {
				$platforms[] = $p;
			}
		}
		$out['platform'] = implode( ',', array_values( array_unique( $platforms ) ) );

		$kind        = sanitize_key( (string) ( $input['kind'] ?? '' ) );
		$out['kind'] = in_array( $kind, array( 'review', 'testimonial', 'all' ), true ) ? $kind : $d['kind'];

		$min               = str_replace( ',', '.', trim( (string) ( $input['min_rating'] ?? '' ) ) );
		$out['min_rating'] = preg_match( '/^[1-5](\.\d)?$/', $min ) && (float) $min <= 5.0 ? $min : '';

		$limit        = is_numeric( $input['limit'] ?? null ) ? (int) $input['limit'] : (int) $d['limit'];
		$out['limit'] = (string) max( 1, min( 50, $limit ) );

		$ctx                 = sanitize_key( (string) ( $input['context_type'] ?? '' ) );
		$out['context_type'] = in_array( $ctx, self::CONTEXT_TYPES, true ) ? $ctx : '';
		$ctx_id              = (string) preg_replace( '/[^A-Za-z0-9_.:\-]/', '', (string) ( $input['context_id'] ?? '' ) );
		$out['context_id']   = '' !== $out['context_type'] ? mb_substr( $ctx_id, 0, 64 ) : '';

		foreach ( array( 'show_source', 'show_date' ) as $flag ) {
			$value        = strtolower( trim( (string) ( $input[ $flag ] ?? $d[ $flag ] ) ) );
			$out[ $flag ] = in_array( $value, array( '0', 'false' ), true ) ? '0' : ( in_array( $value, array( '1', 'true' ), true ) ? '1' : $d[ $flag ] );
		}

		$profiles = array();
		foreach ( self::csv( $input['profile'] ?? '' ) as $id ) {
			if ( ctype_digit( $id ) && (int) $id > 0 ) {
				$profiles[] = (string) (int) $id;
			}
		}
		$out['profile'] = implode( ',', array_values( array_unique( $profiles ) ) );
		return $out;
	}

	/**
	 * Split a comma list (string or array).
	 *
	 * @param mixed $value Value.
	 * @return list<string>
	 */
	private static function csv( mixed $value ): array {
		$items = is_array( $value ) ? $value : explode( ',', (string) $value );
		return array_values( array_filter( array_map( static fn( $v ) => trim( (string) $v ), $items ), static fn( $v ) => '' !== $v ) );
	}

	/**
	 * Build the shortcode string from sanitized attributes.
	 *
	 * @param array<string, mixed> $input Raw input.
	 */
	public static function build_shortcode( array $input ): string {
		$atts  = self::sanitize( $input );
		$parts = array( 'v="1"' );
		foreach ( self::defaults() as $key => $default ) {
			if ( $atts[ $key ] !== $default && '' !== $atts[ $key ] ) {
				$parts[] = $key . '="' . $atts[ $key ] . '"';
			}
		}
		return '[review_captain ' . implode( ' ', $parts ) . ']';
	}

	/**
	 * Page callback.
	 */
	public function render(): void {
		if ( ! current_user_can( Capabilities::MODERATE ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'review-captain' ), '', array( 'response' => 403 ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only generator.
		$submitted = isset( $_GET['rc_gen'] );
		$input     = $submitted ? wp_unslash( $_GET ) : self::defaults(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$atts      = self::sanitize( is_array( $input ) ? $input : array() );
		if ( ! $submitted ) {
			$atts = self::defaults();
		}
		$shortcode = self::build_shortcode( $atts );

		echo '<div class="wrap rc-admin">';
		echo View::header( __( 'Widgets', 'review-captain' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<p>' . esc_html__( 'Display selected reviews on your website – with the “Review Captain – Reviews” block in the editor or via shortcode. The output is generated server-side; no external scripts are loaded.', 'review-captain' ) . '</p>';

		if ( 0 === $this->plugin->reviews()->count( array( 'status' => 'approved' ) ) ) {
			echo View::notice( 'info', __( 'No approved reviews yet – a widget will then show the empty state text from the display settings.', 'review-captain' ), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		echo '<div class="rc-admin-columns"><div class="rc-admin-main">';
		echo '<h2>' . esc_html__( 'Create shortcode', 'review-captain' ) . '</h2>';
		echo '<form method="get"><input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '" /><input type="hidden" name="rc_gen" value="1" />';
		echo '<table class="form-table" role="presentation"><tbody>';

		$this->select( 'layout', __( 'Layout', 'review-captain' ), array( 'grid' => __( 'Grid', 'review-captain' ), 'list' => __( 'List', 'review-captain' ) ), $atts['layout'], __( 'Carousel, badge, and trust bar are available in Review Captain Pro.', 'review-captain' ) );
		$this->select( 'platform', __( 'Platform', 'review-captain' ), array( '' => __( 'All platforms', 'review-captain' ) ) + ReviewsPage::platform_choices(), $atts['platform'] );
		$this->select(
			'kind',
			__( 'Content type', 'review-captain' ),
			array(
				'all'         => __( 'Reviews and testimonials', 'review-captain' ),
				'review'      => __( 'Reviews only', 'review-captain' ),
				'testimonial' => __( 'Testimonials only', 'review-captain' ),
			),
			$atts['kind']
		);
		$ratings = array( '' => __( 'No minimum rating', 'review-captain' ) );
		for ( $i = 5; $i >= 1; $i-- ) {
			/* translators: %d: stars */
			$ratings[ (string) $i ] = sprintf( __( '%d out of 5 and up', 'review-captain' ), $i );
		}
		$this->select( 'min_rating', __( 'Minimum rating', 'review-captain' ), $ratings, $atts['min_rating'], __( 'Other scales are converted for filtering only; the original rating is always displayed.', 'review-captain' ) );
		echo '<tr><th scope="row"><label for="rc-w-limit">' . esc_html__( 'Number', 'review-captain' ) . '</label></th><td><input type="number" min="1" max="50" id="rc-w-limit" name="limit" class="small-text" value="' . esc_attr( $atts['limit'] ) . '" /></td></tr>';
		$contexts = array_intersect_key( ReviewsPage::context_choices(), array_flip( array_merge( array( '' ), self::CONTEXT_TYPES ) ) );
		$this->select( 'context_type', __( 'Reference', 'review-captain' ), $contexts, $atts['context_type'] );
		echo '<tr><th scope="row"><label for="rc-w-context_id">' . esc_html__( 'Reference ID', 'review-captain' ) . '</label></th><td><input type="text" id="rc-w-context_id" name="context_id" class="regular-text" value="' . esc_attr( $atts['context_id'] ) . '" /><p class="description">' . esc_html__( 'E.g. product or post ID. Empty = all.', 'review-captain' ) . '</p></td></tr>';
		$profiles = array( '' => __( 'All profiles', 'review-captain' ) );
		foreach ( ( new Profiles() )->all() as $profile ) {
			$profiles[ (string) $profile['id'] ] = (string) $profile['label'];
		}
		$this->select( 'profile', __( 'Profile', 'review-captain' ), $profiles, $atts['profile'] );
		echo '<tr><th scope="row">' . esc_html__( 'Display', 'review-captain' ) . '</th><td><fieldset><legend class="screen-reader-text">' . esc_html__( 'Display', 'review-captain' ) . '</legend>';
		echo '<input type="hidden" name="show_source" value="0" /><label><input type="checkbox" name="show_source" value="1"' . checked( $atts['show_source'], '1', false ) . ' /> ' . esc_html__( 'Show source', 'review-captain' ) . '</label><br />';
		echo '<input type="hidden" name="show_date" value="0" /><label><input type="checkbox" name="show_date" value="1"' . checked( $atts['show_date'], '1', false ) . ' /> ' . esc_html__( 'Show date', 'review-captain' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'Attribution for external reviews (platform name and link) always remains visible.', 'review-captain' ) . '</p></fieldset></td></tr>';
		echo '</tbody></table><p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Generate shortcode', 'review-captain' ) . '</button></p></form>';
		echo '</div><div class="rc-admin-side">';

		echo '<div class="postbox"><div class="postbox-header"><h2>' . esc_html__( 'Your shortcode', 'review-captain' ) . '</h2></div><div class="inside">';
		echo '<label class="screen-reader-text" for="rc-shortcode">' . esc_html__( 'Shortcode', 'review-captain' ) . '</label>';
		echo '<textarea id="rc-shortcode" class="large-text code" rows="3" readonly>' . esc_textarea( $shortcode ) . '</textarea>';
		echo '<p><button type="button" class="button rc-admin-copy" data-target="rc-shortcode" data-done="' . esc_attr__( 'Copied', 'review-captain' ) . '" hidden>' . esc_html__( 'Copy', 'review-captain' ) . '</button> <span class="rc-admin-copy-status" aria-live="polite"></span></p>';
		echo '<p class="description">' . esc_html__( 'Paste into a Shortcode block, a classic text field, or a widget field.', 'review-captain' ) . '</p></div></div>';

		echo '<div class="postbox"><div class="postbox-header"><h2>' . esc_html__( 'Block editor', 'review-captain' ) . '</h2></div><div class="inside">';
		echo '<p>' . esc_html__( 'Insert the “Review Captain – Reviews” block and set the same options in the sidebar. For the review form, use the “Review Captain – Form” block or the shortcode:', 'review-captain' ) . '</p>';
		echo '<p><code>[review_captain_form v="1"]</code></p>';
		echo '<p class="description">' . esc_html__( 'Review markup for search engines is only output for approved reviews from this website – never for external platform reviews or testimonials.', 'review-captain' ) . '</p></div></div>';
		echo '</div></div></div>';
	}

	/**
	 * Select row.
	 *
	 * @param string                $name    Name.
	 * @param string                $label   Label.
	 * @param array<string, string> $choices Choices.
	 * @param string                $current Current.
	 * @param string                $help    Help.
	 */
	private function select( string $name, string $label, array $choices, string $current, string $help = '' ): void {
		$id = 'rc-w-' . $name;
		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th><td><select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
		foreach ( $choices as $value => $text ) {
			echo '<option value="' . esc_attr( (string) $value ) . '"' . selected( (string) $value, $current, false ) . '>' . esc_html( $text ) . '</option>';
		}
		echo '</select>';
		if ( '' !== $help ) {
			echo '<p class="description">' . esc_html( $help ) . '</p>';
		}
		echo '</td></tr>';
	}
}
