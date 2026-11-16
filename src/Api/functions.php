<?php
/**
 * Global versioned API functions.
 *
 * @package ReviewCaptain
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wp_review_captain_create_local_review_v1' ) ) {
	/**
	 * Create a pending, unverified local review (e.g. from Form Captain). Idempotent via idempotency_key.
	 *
	 * @param array<string,mixed> $payload rating (int 1-5), review_text, title, display_name, review_type, profile_id,
	 *                                     source_plugin, source_entry_id, idempotency_key, verified (must be false).
	 * @return array{success:bool, code:string, review_id:int|null}
	 */
	function wp_review_captain_create_local_review_v1( array $payload ): array {
		return \ReviewCaptain\Api\PublicApi::create_local_review( $payload );
	}
}

if ( ! function_exists( 'wp_review_captain_submit_experience_event_v1' ) ) {
	/**
	 * Submit an experience event (schema docs/contracts/experience-event-v1.schema.json).
	 *
	 * @param array<string,mixed> $event Event.
	 * @return array{accepted:bool, code:string, event_key:string}
	 */
	function wp_review_captain_submit_experience_event_v1( array $event ): array {
		return \ReviewCaptain\Api\PublicApi::submit_event( $event );
	}
}

if ( ! function_exists( 'review_captain_check_request' ) ) {
	/**
	 * Verify capability and nonce for an admin action; dies with 403 otherwise (RC-037).
	 *
	 * @param string $capability   Required capability.
	 * @param string $nonce_action Nonce action.
	 */
	function review_captain_check_request( string $capability, string $nonce_action ): void {
		\ReviewCaptain\Security\Capabilities::check( $capability, $nonce_action );
	}
}
