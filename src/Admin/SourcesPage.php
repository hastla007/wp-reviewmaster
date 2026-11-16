<?php
/**
 * Sources: public profile links per platform (Core). Native API connections are added by Pro via the
 * review_captain_sources_page action.
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Admin;

use ReviewCaptain\Profiles\ProfileRepository;
use ReviewCaptain\Reviews\ReviewService;

defined( 'ABSPATH' ) || exit;

final class SourcesPage {

	private const PAGE = 'review-captain-sources';

	public static function register_actions(): void {
		add_action( 'admin_post_review_captain_save_profile', array( self::class, 'handle_save' ) );
		add_action( 'admin_post_review_captain_delete_profile', array( self::class, 'handle_delete' ) );
	}

	public static function render(): void {
		echo '<div class="wrap"><h1>' . esc_html__( 'Sources', 'review-captain' ) . '</h1>';
		Menu::notices();
		$profiles = ProfileRepository::all();

		echo '<div class="rc-cards">';
		foreach ( ReviewService::EXTERNAL_PROVIDERS as $provider ) {
			if ( 'other' === $provider ) {
				continue;
			}
			$mine = array_filter( $profiles, static fn( $p ) => $p['provider'] === $provider );
			echo '<div class="rc-source-card postbox"><div class="inside"><h2>' . esc_html( ReviewService::provider_label( $provider ) ) . '</h2>';
			if ( $mine ) {
				echo '<ul>';
				foreach ( $mine as $p ) {
					echo '<li><a href="' . esc_url( $p['public_url'] ) . '" rel="noopener noreferrer">' . esc_html( $p['label'] ? $p['label'] : $p['public_url'] ) . '</a> ';
					echo '<form method="post" action="' . esc_url( Menu::post_url() ) . '" class="rc-inline"><input type="hidden" name="action" value="review_captain_delete_profile"><input type="hidden" name="id" value="' . (int) $p['id'] . '">';
					wp_nonce_field( 'rc_delete_profile_' . (int) $p['id'] );
					echo '<button type="submit" class="button-link button-link-delete">' . esc_html__( 'Remove', 'review-captain' ) . '</button></form></li>';
				}
				echo '</ul>';
			} else {
				echo '<p class="rc-pill">' . esc_html__( 'No profile link', 'review-captain' ) . '</p>';
			}
			/**
			 * Pro renders the connection status / connect button here.
			 *
			 * @param string $provider Provider key.
			 */
			if ( has_action( 'review_captain_source_card' ) ) {
				do_action( 'review_captain_source_card', $provider );
			} else {
				echo '<p class="description">' . esc_html__( 'Automatic import is available with Review Captain Pro where the platform grants API access. Without Pro you can add reviews manually and link to your profile.', 'review-captain' ) . '</p>';
			}
			echo '</div></div>';
		}
		echo '</div>';

		echo '<div class="postbox"><div class="inside"><h2>' . esc_html__( 'Add profile link', 'review-captain' ) . '</h2>';
		echo '<p>' . esc_html__( 'Profile links are used by the call-to-action block (“Review us on …”). All visitors see the same neutral buttons.', 'review-captain' ) . '</p>';
		echo '<form method="post" action="' . esc_url( Menu::post_url() ) . '"><input type="hidden" name="action" value="review_captain_save_profile">';
		wp_nonce_field( 'rc_save_profile' );
		echo '<select name="provider" aria-label="' . esc_attr__( 'Platform', 'review-captain' ) . '">';
		foreach ( ReviewService::EXTERNAL_PROVIDERS as $p ) {
			echo '<option value="' . esc_attr( $p ) . '">' . esc_html( ReviewService::provider_label( $p ) ) . '</option>';
		}
		echo '</select> <input type="text" name="label" placeholder="' . esc_attr__( 'Label, e.g. Vienna store', 'review-captain' ) . '"> <input type="url" name="url" class="regular-text" placeholder="https://" required> ';
		submit_button( __( 'Add', 'review-captain' ), 'secondary', 'submit', false );
		echo '</form></div></div>';

		do_action( 'review_captain_sources_page' );
		echo '</div>';
	}

	public static function handle_save(): void {
		review_captain_check_request( 'rc_manage_reviews', 'rc_save_profile' );
		$res = ProfileRepository::save_link(
			0,
			isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : '',
			isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '',
			isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : ''
		);
		Menu::back( self::PAGE, is_wp_error( $res ) ? '' : 'saved', is_wp_error( $res ) ? $res->get_error_message() : '' );
	}

	public static function handle_delete(): void {
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked below.
		review_captain_check_request( 'rc_manage_reviews', 'rc_delete_profile_' . $id );
		ProfileRepository::delete( $id );
		Menu::back( self::PAGE, 'deleted' );
	}
}
