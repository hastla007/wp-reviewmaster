<?php
/**
 * Review over the link of a review invitation (P2-10, owner decision of 24.11.2026).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Frontend;

use ReviewCaptain\Domain\Review;
use ReviewCaptain\Plugin;
use ReviewCaptain\Support\Uuid;

/**
 * The invitation mail of Review Captain Pro links to the own review form with a signed token
 * (query argument rc_request). The form passes it on as a hidden field; after a review was stored
 * the core asks the add-on through the filter review_captain_request_token_v1 which request the
 * token belongs to, stores the reference in the review meta (request_uuid, personal meta: export
 * and erasure) and fires review_captain_request_responded_v1. The add-on marks the request as
 * responded and sends no reminder any more. The core never signs or checks a token itself: without
 * the add-on (or with an invalid token) nothing happens and the review is stored as usual.
 * Reviews on external platforms cannot be detected.
 */
final class RequestResponse {

	/**
	 * Query argument of the invitation link and hidden field of the form.
	 */
	public const FIELD = 'rc_request';

	/**
	 * Meta key of the review with the request reference.
	 */
	public const META_KEY = 'request_uuid';

	/**
	 * Payload schema of review_captain_request_responded_v1.
	 */
	public const SCHEMA = 'review_captain.request_response.v1';

	/**
	 * Hidden form field with the token of the current page address, if there is one.
	 *
	 * @return array<string, string> Empty or array( FIELD => token ).
	 */
	public static function hidden_field(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, the token is checked by the add-on on submit.
		$token = isset( $_GET[ self::FIELD ] ) && is_string( $_GET[ self::FIELD ] ) ? self::clean( sanitize_text_field( wp_unslash( $_GET[ self::FIELD ] ) ) ) : '';
		return '' === $token ? array() : array( self::FIELD => $token );
	}

	/**
	 * Token in a valid shape (letters, digits, ._~-; at most 200 characters), otherwise ''.
	 *
	 * @param mixed $token Raw value.
	 */
	public static function clean( $token ): string {
		return is_string( $token ) && 1 === preg_match( '/^[A-Za-z0-9._~-]{1,200}$/', $token ) ? $token : '';
	}

	/**
	 * Request reference of a token, answered by the add-on; null when nobody knows the token.
	 *
	 * @param string $token Token from the form.
	 */
	public static function resolve( string $token ): ?string {
		$token = self::clean( $token );
		if ( '' === $token ) {
			return null;
		}
		try {
			/**
			 * Request reference (request UUID) of a signed invitation token, or null. Review
			 * Captain Pro checks the signature; the core treats the token as opaque.
			 *
			 * @param string|null $ref   Default null (unknown token).
			 * @param string      $token Token of the invitation link.
			 */
			$ref = apply_filters( 'review_captain_request_token_v1', null, $token );
		} catch ( \Throwable $e ) {
			return null;
		}
		return is_string( $ref ) && 1 === preg_match( '/^[A-Za-z0-9._:-]{1,191}$/', $ref ) ? $ref : null;
	}

	/**
	 * After new reviews were stored: link them to the request of the token and fire
	 * review_captain_request_responded_v1 once. Without a valid token nothing happens.
	 *
	 * @param Plugin               $plugin  Plugin.
	 * @param list<Review>         $reviews Reviews stored by this submission.
	 * @param array<string, mixed> $post    Unslashed POST data.
	 * @return string|null Request reference, or null.
	 */
	public static function record( Plugin $plugin, array $reviews, array $post ): ?string {
		$ref = self::resolve( (string) ( $post[ self::FIELD ] ?? '' ) );
		if ( null === $ref || ! $reviews ) {
			return null;
		}
		foreach ( $reviews as $review ) {
			if ( null === $review->id ) {
				continue;
			}
			$review->meta[ self::META_KEY ] = $ref;
			try {
				$plugin->reviews()->update( $review );
			} catch ( \Throwable $e ) {
				unset( $review->meta[ self::META_KEY ] ); // The review stays stored without the link.
			}
		}
		/**
		 * A local review was submitted over the link of a review invitation (after commit). The
		 * payload carries no text, name, e-mail or token.
		 *
		 * @param array{schema: string, event_id: string, request_uuid: string, review_uuid: string, review_uuids: list<string>, review_status: string, occurred_at: string} $payload Payload.
		 */
		do_action(
			'review_captain_request_responded_v1',
			array(
				'schema'        => self::SCHEMA,
				'event_id'      => Uuid::v4(),
				'request_uuid'  => $ref,
				'review_uuid'   => $reviews[0]->uuid,
				'review_uuids'  => array_values(
					array_map(
						static function ( Review $review ): string {
							return $review->uuid;
						},
						$reviews
					)
				),
				'review_status' => $reviews[0]->status->value,
				'occurred_at'   => gmdate( 'c' ),
			)
		);
		return $ref;
	}
}
