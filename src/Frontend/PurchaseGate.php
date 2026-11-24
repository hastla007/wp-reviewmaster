<?php
/**
 * "Only buyers may review" (SET-RC-32, owner decision E6 of 24.11.2026: Free).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Frontend;

use ReviewCaptain\Domain\Verification;
use ReviewCaptain\Plugin;

/**
 * Decides whether the local review form may be used for a context when
 * `submissions.require_purchase` is on. Only a trusted source can confirm a purchase or an
 * attendance (Verification::requires_source_contract()):
 *
 * - WooCommerce (built in): the logged-in customer has a paid order (wc_get_is_paid_statuses())
 *   containing the product (context_type `product`, numeric context_id; variations count for
 *   their product);
 * - any other source via the filter `review_captain_purchase_proof`, e.g. the invitation link of
 *   Review Captain Pro (token in `rc_invite`, carried by the form) or an event/booking connector.
 *
 * `submissions.purchase_max_age_days` (0 = unlimited) limits the age of the confirmed purchase; a
 * proof without date cannot satisfy a limit. A refused check never stores anything: the form shows
 * a message instead (renderer) and the handler rejects with the same code (no silent drop).
 * Automatic approval rules are not part of the core (Pro, SET-RC-35).
 */
final class PurchaseGate {

	/**
	 * Query/POST field carrying an invitation token to the trusted sources.
	 */
	public const INVITE_FIELD = 'rc_invite';

	/**
	 * Error codes (messages in FormHandler::error_messages()).
	 */
	public const CODES = array( 'purchase_login', 'purchase_required', 'purchase_too_old' );

	/**
	 * Most recent orders of a customer inspected for the product.
	 */
	public const MAX_ORDERS = 100;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( private Plugin $plugin ) {}

	/**
	 * Whether submissions require a confirmed purchase.
	 */
	public function required(): bool {
		return (bool) $this->plugin->settings()->get( 'submissions', 'require_purchase', false );
	}

	/**
	 * Maximum age of the purchase in days (0 = unlimited).
	 */
	public function max_age_days(): int {
		return max( 0, (int) $this->plugin->settings()->get( 'submissions', 'purchase_max_age_days', 0 ) );
	}

	/**
	 * Invitation token from request data ('' = none or invalid).
	 *
	 * @param array<string, mixed> $source $_GET or the POST data (unslashed).
	 */
	public static function invite_token( array $source ): string {
		$raw = isset( $source[ self::INVITE_FIELD ] ) && is_scalar( $source[ self::INVITE_FIELD ] ) ? trim( (string) $source[ self::INVITE_FIELD ] ) : '';
		return preg_match( '/^[A-Za-z0-9_.:-]{1,191}$/', $raw ) ? $raw : '';
	}

	/**
	 * Check one context.
	 *
	 * @param string $type   Context type ('' = none).
	 * @param string $cid    Context ID ('' = none).
	 * @param string $invite Invitation token ('' = none).
	 * @return array{ok: bool, code: string, level: ?Verification, source: string}
	 */
	public function check( string $type, string $cid, string $invite = '' ): array {
		$user_id = is_user_logged_in() ? (int) get_current_user_id() : 0;
		$max_age = $this->max_age_days();
		$proof   = null;
		if ( $user_id > 0 && 'product' === $type && ctype_digit( $cid ) ) {
			$proof = self::woocommerce_proof( $user_id, (int) $cid );
		}
		/**
		 * Confirm a purchase or attendance for the review form (SET-RC-32). Only trusted sources may
		 * answer (order system, booking system, the invitation link of Review Captain Pro).
		 *
		 * Proof shape: level (purchase_verified|attendance_confirmed), confirmed_at (Unix time, needed
		 * when an age limit is set), source (key, e.g. "invitation"). Any other level is refused.
		 *
		 * @param array|null $proof   Proof found so far (WooCommerce), null = none.
		 * @param array      $context context_type, context_id, user_id (0 = guest), invite (token or ''), max_age_days (0 = unlimited).
		 */
		$proof = apply_filters(
			'review_captain_purchase_proof',
			$proof,
			array(
				'context_type' => $type,
				'context_id'   => $cid,
				'user_id'      => $user_id,
				'invite'       => $invite,
				'max_age_days' => $max_age,
			)
		);
		$level = is_array( $proof ) ? Verification::tryFrom( (string) ( $proof['level'] ?? '' ) ) : null;
		if ( null === $level || ! $level->requires_source_contract() ) {
			return self::result( false, $user_id > 0 || '' !== $invite ? 'purchase_required' : 'purchase_login' );
		}
		if ( $max_age > 0 ) {
			$at = isset( $proof['confirmed_at'] ) && is_numeric( $proof['confirmed_at'] ) ? (int) $proof['confirmed_at'] : 0;
			if ( $at <= 0 ) {
				return self::result( false, 'purchase_required' ); // No date: the age limit cannot be confirmed.
			}
			if ( time() - $at > $max_age * DAY_IN_SECONDS ) {
				return self::result( false, 'purchase_too_old' );
			}
		}
		return self::result( true, '', $level, sanitize_key( (string) ( $proof['source'] ?? 'filter' ) ) );
	}

	/**
	 * Latest paid WooCommerce order of a customer containing the product.
	 *
	 * @param int $user_id    Customer (WordPress user ID).
	 * @param int $product_id Product or variation ID.
	 * @return array{level: string, confirmed_at: ?int, source: string}|null
	 */
	public static function woocommerce_proof( int $user_id, int $product_id ): ?array {
		if ( $user_id <= 0 || $product_id <= 0 || ! function_exists( 'wc_get_orders' ) ) {
			return null;
		}
		$statuses = function_exists( 'wc_get_is_paid_statuses' ) ? (array) wc_get_is_paid_statuses() : array( 'processing', 'completed' );
		$orders   = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'status'      => $statuses,
				'limit'       => self::MAX_ORDERS,
				'orderby'     => 'date',
				'order'       => 'DESC',
				'return'      => 'objects',
			)
		);
		foreach ( is_array( $orders ) ? $orders : array() as $order ) {
			if ( ! is_object( $order ) || ! method_exists( $order, 'get_items' ) ) {
				continue;
			}
			foreach ( (array) $order->get_items() as $item ) {
				if ( ! is_object( $item ) || ! method_exists( $item, 'get_product_id' ) ) {
					continue;
				}
				$variation = method_exists( $item, 'get_variation_id' ) ? (int) $item->get_variation_id() : 0;
				if ( (int) $item->get_product_id() !== $product_id && $variation !== $product_id ) {
					continue;
				}
				$date = method_exists( $order, 'get_date_paid' ) ? $order->get_date_paid() : null;
				if ( ! $date && method_exists( $order, 'get_date_created' ) ) {
					$date = $order->get_date_created();
				}
				return array(
					'level'        => Verification::PURCHASE_VERIFIED->value,
					'confirmed_at' => $date instanceof \DateTimeInterface ? $date->getTimestamp() : null,
					'source'       => 'woocommerce',
				);
			}
		}
		return null;
	}

	/**
	 * Result envelope.
	 *
	 * @param bool              $ok     Allowed.
	 * @param string            $code   Error code ('' when allowed).
	 * @param Verification|null $level  Confirmed level.
	 * @param string            $source Source key.
	 * @return array{ok: bool, code: string, level: ?Verification, source: string}
	 */
	private static function result( bool $ok, string $code, ?Verification $level = null, string $source = '' ): array {
		return array(
			'ok'     => $ok,
			'code'   => $code,
			'level'  => $level,
			'source' => $source,
		);
	}
}
