<?php
/**
 * Public PHP API (docs/m1/vertraege.md, Konzept §7.4).
 *
 * - wp_review_captain_create_local_review_v1( array $payload ): array
 * - wp_review_captain_submit_experience_event_v1( array $event ): array
 * - action wp_review_captain_experience_event_v1( array $event )
 *
 * Core validates events; storing and processing them is done by Review Captain Pro (filter review_captain_accept_experience_event).
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Api;

use ReviewCaptain\Reviews\ReviewService;

defined( 'ABSPATH' ) || exit;

final class PublicApi {

	public const EVENT_TYPES = array( 'experience_completed', 'experience_cancelled', 'experience_refunded', 'experience_no_show', 'recipient_opted_out' );
	public const OBJECT_TYPES = array( 'product', 'voucher_product', 'event', 'appointment', 'course', 'booking' );
	public const BASES        = array( 'consent', 'existing_customer', 'unknown', 'denied' );

	public static function register(): void {
		add_action( 'wp_review_captain_experience_event_v1', array( self::class, 'submit_event' ) );
		require_once __DIR__ . '/functions.php';
	}

	/**
	 * @param array<string,mixed> $payload Payload.
	 * @return array{success:bool, code:string, review_id:int|null}
	 */
	public static function create_local_review( array $payload ): array {
		return ReviewService::create_from_connector( $payload );
	}

	/**
	 * Validate an experience event against experience-event-v1 (RC-021) and hand it to the processor.
	 *
	 * @param array<string,mixed> $event Event.
	 * @return array{accepted:bool, code:string, event_key:string}
	 */
	public static function submit_event( $event ): array {
		$event = is_array( $event ) ? $event : array();
		$code  = self::validate_event( $event );
		$key   = $code === 'ok' ? hash( 'sha256', $event['source'] . '|' . $event['event_id'] ) : '';
		if ( 'ok' !== $code ) {
			self::count( $code );
			return array(
				'accepted'  => false,
				'code'      => $code,
				'event_key' => $key,
			);
		}
		/**
		 * Process a validated event. Return array{accepted:bool, code:string}. Without Pro nothing is stored.
		 *
		 * @param array|null           $result Result or null if no processor.
		 * @param array<string,mixed>  $event  Validated event.
		 * @param string               $key    Idempotency key sha256(source|event_id).
		 */
		$result = apply_filters( 'review_captain_accept_experience_event', null, $event, $key );
		if ( ! is_array( $result ) ) {
			self::count( 'no_processor' );
			return array(
				'accepted'  => false,
				'code'      => 'license_inactive',
				'event_key' => $key,
			);
		}
		return array(
			'accepted'  => ! empty( $result['accepted'] ),
			'code'      => (string) ( $result['code'] ?? 'ok' ),
			'event_key' => $key,
		);
	}

	/**
	 * @param array<string,mixed> $e Event.
	 */
	public static function validate_event( array $e ): string {
		$allowed = array( 'contract', 'source', 'event_id', 'experience_id', 'event_type', 'occurred_at', 'object', 'recipient_ref', 'locale', 'communication', 'verification', 'context' );
		if ( array_diff( array_keys( $e ), $allowed ) ) {
			return 'invalid_payload';
		}
		if ( ! isset( $e['contract'] ) || ! preg_match( '/^(\d+)\.\d+$/', (string) $e['contract'], $m ) ) {
			return 'invalid_payload';
		}
		if ( '1' !== $m[1] ) {
			return 'incompatible_contract';
		}
		if ( ! preg_match( '/^(connector:)?[a-z0-9-]{2,40}$/', (string) ( $e['source'] ?? '' ) ) ) {
			return 'invalid_payload';
		}
		foreach ( array( 'event_id', 'experience_id' ) as $f ) {
			if ( ! isset( $e[ $f ] ) || ! is_string( $e[ $f ] ) || '' === $e[ $f ] || strlen( $e[ $f ] ) > 191 ) {
				return 'invalid_payload';
			}
		}
		if ( ! in_array( $e['event_type'] ?? '', self::EVENT_TYPES, true ) ) {
			return 'invalid_payload';
		}
		if ( empty( $e['occurred_at'] ) || false === strtotime( (string) $e['occurred_at'] ) ) {
			return 'invalid_payload';
		}
		$obj = $e['object'] ?? null;
		if ( ! is_array( $obj ) || ! in_array( $obj['type'] ?? '', self::OBJECT_TYPES, true ) || empty( $obj['id'] ) || strlen( (string) $obj['id'] ) > 64 ) {
			return 'invalid_payload';
		}
		if ( isset( $e['recipient_ref'] ) && ( ! is_string( $e['recipient_ref'] ) || strlen( $e['recipient_ref'] ) > 191 || is_email( $e['recipient_ref'] ) || false !== strpos( $e['recipient_ref'], '@' ) ) ) {
			return 'invalid_payload'; // No e-mail addresses in events.
		}
		if ( 'experience_completed' === $e['event_type'] ) {
			if ( empty( $e['recipient_ref'] ) || ! is_array( $e['communication'] ?? null ) || ! in_array( $e['communication']['basis'] ?? '', self::BASES, true ) ) {
				return 'invalid_payload';
			}
		}
		if ( isset( $e['verification'] ) && ! in_array( $e['verification'], array( 'purchase', 'attendance' ), true ) ) {
			return 'invalid_payload';
		}
		if ( isset( $e['context'] ) ) {
			if ( ! is_array( $e['context'] ) || array_diff( array_keys( $e['context'] ), array( 'campaign_ref', 'order_item_ids', 'channel' ) ) || strlen( (string) wp_json_encode( $e['context'] ) ) > 2048 ) {
				return 'invalid_payload';
			}
		}
		return 'ok';
	}

	private static function count( string $code ): void {
		$stats          = (array) get_option( 'review_captain_event_stats', array() );
		$stats[ $code ] = (int) ( $stats[ $code ] ?? 0 ) + 1;
		update_option( 'review_captain_event_stats', $stats, false );
	}
}
