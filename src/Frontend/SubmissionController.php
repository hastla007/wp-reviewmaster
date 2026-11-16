<?php
/**
 * Handles review submissions via admin-post (no JS) and REST (enhanced). Honeypot, nonce, rate limit (RC-039, TEC-010).
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Frontend;

use ReviewCaptain\Reviews\ReviewService;
use ReviewCaptain\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class SubmissionController {

	public static function register(): void {
		add_action( 'admin_post_review_captain_submit', array( self::class, 'handle_post' ) );
		add_action( 'admin_post_nopriv_review_captain_submit', array( self::class, 'handle_post' ) );
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	public static function routes(): void {
		register_rest_route(
			'review-captain/v1',
			'/reviews',
			array(
				'methods'             => 'POST',
				// Public by design: protected by REST nonce, honeypot and rate limit below.
				'permission_callback' => '__return_true',
				'callback'            => array( self::class, 'handle_rest' ),
			)
		);
		// Fresh nonce for pages served from a full-page cache (TEC-012).
		register_rest_route(
			'review-captain/v1',
			'/form-nonce',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => static function () {
					$response = new \WP_REST_Response( array( 'nonce' => wp_create_nonce( 'rc_submit_review' ) ) );
					$response->header( 'Cache-Control', 'no-store' );
					$response->header( 'X-Robots-Tag', 'noindex' );
					return $response;
				},
			)
		);
	}

	public static function handle_post(): void {
		$referer = wp_get_referer();
		$target  = $referer ? $referer : home_url( '/' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in process().
		$result = self::process( wp_unslash( $_POST ), (string) ( $_POST['_rc_nonce'] ?? '' ) );
		wp_safe_redirect( add_query_arg( 'rc_submitted', is_wp_error( $result ) ? 'error' : 'ok', remove_query_arg( 'rc_submitted', $target ) ) . '#rc-form' );
		exit;
	}

	public static function handle_rest( \WP_REST_Request $request ): \WP_REST_Response {
		$params = $request->get_params();
		$result = self::process( $params, (string) ( $params['_rc_nonce'] ?? '' ) );
		if ( is_wp_error( $result ) ) {
			$fields = array();
			foreach ( $result->get_error_codes() as $code ) {
				$fields[ $code ] = $result->get_error_message( $code );
			}
			return new \WP_REST_Response(
				array(
					'success' => false,
					'errors'  => $fields,
				),
				'rate_limited' === $result->get_error_code() ? 429 : 400
			);
		}
		return new \WP_REST_Response( array( 'success' => true ), 201 );
	}

	/**
	 * @param array<string,mixed> $input Raw input.
	 * @return int|true|\WP_Error Review ID, true for silently discarded spam, or error.
	 */
	public static function process( array $input, string $nonce ) {
		if ( ! wp_verify_nonce( $nonce, 'rc_submit_review' ) ) {
			return new \WP_Error( 'nonce', __( 'Your session has expired. Please reload the page and try again.', 'review-captain' ) );
		}
		// Honeypot or implausibly fast submission: pretend success, store nothing.
		$started = isset( $input['rc_started'] ) ? (int) $input['rc_started'] : 0;
		if ( ( Settings::get( 'honeypot_enabled' ) && ! empty( $input['rc_website'] ) ) || ( $started > 0 && time() - $started < 3 ) ) {
			self::count_spam();
			return true;
		}
		if ( '' !== (string) Settings::get( 'consent_text' ) && empty( $input['consent'] ) ) {
			return new \WP_Error( 'consent', __( 'Please confirm the consent text.', 'review-captain' ) );
		}
		if ( ! self::rate_limit_ok() ) {
			return new \WP_Error( 'rate_limited', __( 'Too many reviews were sent. Please try again later.', 'review-captain' ) );
		}
		$id = ReviewService::create_local(
			array(
				'rating'       => $input['rating'] ?? null,
				'title'        => $input['title'] ?? '',
				'content'      => $input['content'] ?? '',
				'author_name'  => $input['author_name'] ?? '',
				'email'        => $input['email'] ?? '',
				'context_type' => $input['context_type'] ?? '',
				'context_id'   => $input['context_id'] ?? '',
				'type'         => $input['type'] ?? 'general',
			)
		);
		if ( is_int( $id ) && Settings::get( 'notify_moderators' ) ) {
			self::notify( $id );
		}
		return $id;
	}

	/**
	 * Rate limit per anonymized client key. Only a hash of the IP is stored, for one hour.
	 */
	private static function rate_limit_ok(): bool {
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key   = 'rc_rl_' . substr( hash_hmac( 'sha256', $ip . gmdate( 'YmdH' ), wp_salt( 'nonce' ) ), 0, 32 );
		$count = (int) get_transient( $key );
		if ( $count >= (int) Settings::get( 'rate_limit_per_hour' ) ) {
			return false;
		}
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		return true;
	}

	private static function count_spam(): void {
		update_option( 'review_captain_spam_blocked', (int) get_option( 'review_captain_spam_blocked', 0 ) + 1, false );
	}

	private static function notify( int $id ): void {
		$to = (string) get_option( 'admin_email' );
		if ( ! is_email( $to ) ) {
			return;
		}
		wp_mail(
			$to,
			/* translators: %s: site name */
			sprintf( __( '[%s] New review awaiting moderation', 'review-captain' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ),
			__( 'A new review was submitted and is waiting for moderation:', 'review-captain' ) . "\n\n" . admin_url( 'admin.php?page=review-captain-reviews&action=view&id=' . $id )
		);
	}
}
