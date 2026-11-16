<?php
/**
 * Experience event payload validator (CON-004, RC-021).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Connectors;

use ReviewCaptain\Domain\Review;
use ReviewCaptain\Support\ErrorCode;

/**
 * Validates experience events in both accepted shapes:
 *
 * - connector poll format (docs/connectors/contract-v1.md §3.1): items returned by
 *   ExperienceSourceInterface::poll_experiences();
 * - push/event schema `review_captain.experience_event.v1` (docs/contracts/experience-event-v1.md),
 *   detected by the field `schema`.
 *
 * Errors are returned as "field:reason" strings (reasons: missing, invalid, too_long, forbidden,
 * pii, incompatible). An empty list means valid. Unknown, non-forbidden fields are ignored.
 */
final class ExperienceEvent {

	public const SCHEMA = 'review_captain.experience_event.v1';

	public const CONNECTOR_EVENT_TYPES = array( 'experience_completed', 'experience_cancelled', 'experience_refunded' );

	public const SCHEMA_EVENT_TYPES = array(
		'experience_created',
		'experience_completed',
		'experience_updated',
		'experience_cancelled',
		'experience_refunded',
		'experience_no_show',
		'experience_voided',
		'permission_changed',
	);

	/**
	 * Event types that revoke an experience (recipient_ref may be null).
	 */
	public const REVOKING_EVENT_TYPES = array( 'experience_cancelled', 'experience_refunded', 'experience_no_show', 'experience_voided' );

	/**
	 * Fields that would carry personal data or review content and are never accepted.
	 */
	public const FORBIDDEN_FIELDS = array(
		'email',
		'e_mail',
		'mail',
		'author_email',
		'recipient_email',
		'customer_email',
		'name',
		'first_name',
		'last_name',
		'full_name',
		'author_name',
		'customer_name',
		'phone',
		'telephone',
		'mobile',
		'address',
		'street',
		'body',
		'text',
		'review_text',
		'comment',
		'password',
		'token',
		'access_token',
		'secret',
		'ticket_code',
		'voucher_code',
		'iban',
		'card_number',
		'payment',
	);

	/**
	 * Maximum JSON size of a schema event (16 KiB).
	 */
	public const MAX_BYTES = 16384;

	private const EMAIL_PATTERN = '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i';

	/**
	 * Validate a payload.
	 *
	 * @param array<string, mixed> $payload Payload.
	 * @return list<string>
	 */
	public static function validate( array $payload ): array {
		$errors = self::forbidden( $payload, '' );
		if ( array_key_exists( 'schema', $payload ) ) {
			$errors = array_merge( $errors, self::validate_schema_event( $payload ) );
		} else {
			$errors = array_merge( $errors, self::validate_connector_event( $payload ) );
		}
		return array_values( array_unique( $errors ) );
	}

	/**
	 * Error code for a list of validation errors (null when valid).
	 *
	 * @param list<string> $errors Errors from validate().
	 */
	public static function error_code( array $errors ): ?string {
		if ( ! $errors ) {
			return null;
		}
		foreach ( $errors as $error ) {
			if ( str_ends_with( $error, ':incompatible' ) ) {
				return ErrorCode::CONTRACT_INCOMPATIBLE;
			}
		}
		return ErrorCode::DATA_INVALID_PAYLOAD;
	}

	/**
	 * Fill defaults of the connector format (source, idempotency_key, locale) after validation.
	 *
	 * @param array<string, mixed> $payload       Valid payload.
	 * @param string               $connector_key Connector key.
	 * @return array<string, mixed>
	 */
	public static function with_defaults( array $payload, string $connector_key ): array {
		$payload['source'] = $connector_key;
		if ( empty( $payload['idempotency_key'] ) ) {
			$payload['idempotency_key'] = sha1( $connector_key . '|' . (string) ( $payload['event_id'] ?? '' ) );
		}
		$payload['locale'] = isset( $payload['locale'] ) && '' !== $payload['locale'] ? $payload['locale'] : null;
		return $payload;
	}

	/**
	 * Whether a string is ISO 8601 with time zone (Z or ±hh:mm).
	 *
	 * @param mixed $value     Value.
	 * @param bool  $utc_only  Require "Z".
	 */
	public static function is_iso8601( mixed $value, bool $utc_only = false ): bool {
		if ( ! is_string( $value ) ) {
			return false;
		}
		$zone = $utc_only ? 'Z' : '(Z|[+\-]\d{2}:?\d{2})';
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d{1,6})?)?' . $zone . '$/', $value ) ) {
			return false;
		}
		try {
			new \DateTimeImmutable( $value );
		} catch ( \Exception $e ) {
			return false;
		}
		[ $y, $m, $d ] = array_map( 'intval', explode( '-', substr( $value, 0, 10 ) ) );
		return checkdate( $m, $d, $y );
	}

	/**
	 * Connector poll format.
	 *
	 * @param array<string, mixed> $p Payload.
	 * @return list<string>
	 */
	private static function validate_connector_event( array $p ): array {
		$e = array();
		if ( ! isset( $p['contract'] ) ) {
			$e[] = 'contract:missing';
		} elseif ( ! is_string( $p['contract'] ) || ! preg_match( '/^1\.\d+$/', $p['contract'] ) ) {
			$e[] = is_string( $p['contract'] ) && preg_match( '/^\d+\.\d+$/', $p['contract'] ) ? 'contract:incompatible' : 'contract:invalid';
		}
		self::string_field( $p, 'event_id', 191, true, $e );
		self::string_field( $p, 'experience_id', 191, true, $e );
		self::enum_field( $p, 'event_type', self::CONNECTOR_EVENT_TYPES, $e );
		self::time_field( $p, 'occurred_at', false, $e );
		if ( self::string_field( $p, 'object_type', 40, true, $e ) && ! preg_match( '/^[a-z0-9_]{2,40}$/', $p['object_type'] ) ) {
			$e[] = 'object_type:invalid';
		}
		self::string_field( $p, 'object_id', 64, true, $e );

		$completed = 'experience_completed' === ( $p['event_type'] ?? null );
		if ( $completed || isset( $p['recipient_ref'] ) ) {
			self::recipient_ref( $p, $e );
		}
		if ( isset( $p['locale'] ) && ( ! is_string( $p['locale'] ) || ! preg_match( '/^[A-Za-z]{2,3}([_-][A-Za-z0-9]{2,8})*$/', $p['locale'] ) || strlen( $p['locale'] ) > 20 ) ) {
			$e[] = 'locale:invalid';
		}
		if ( ! array_key_exists( 'communication_allowed', $p ) ) {
			$e[] = 'communication_allowed:missing';
		} elseif ( ! is_bool( $p['communication_allowed'] ) ) {
			$e[] = 'communication_allowed:invalid';
		}
		if ( ! isset( $p['consent'] ) ) {
			$e[] = 'consent:missing';
		} elseif ( ! is_array( $p['consent'] ) ) {
			$e[] = 'consent:invalid';
		} else {
			if ( ! in_array( $p['consent']['status'] ?? null, array( 'granted', 'denied', 'unknown' ), true ) ) {
				$e[] = 'consent.status:invalid';
			}
			if ( ! isset( $p['consent']['source'] ) || ! is_string( $p['consent']['source'] ) || mb_strlen( $p['consent']['source'] ) > 64 ) {
				$e[] = 'consent.source:invalid';
			}
			if ( isset( $p['consent']['at'] ) && ! self::is_iso8601( $p['consent']['at'] ) ) {
				$e[] = 'consent.at:invalid';
			}
		}
		if ( array_key_exists( 'idempotency_key', $p ) && null !== $p['idempotency_key'] ) {
			self::string_field( $p, 'idempotency_key', 191, true, $e );
		}
		if ( isset( $p['context'] ) ) {
			if ( ! is_array( $p['context'] ) || count( $p['context'] ) > 10 ) {
				$e[] = 'context:invalid';
			} else {
				foreach ( $p['context'] as $k => $v ) {
					if ( ! is_string( $k ) || ! preg_match( '/^[a-z0-9_]{1,40}$/', $k ) || ! ( is_scalar( $v ) || null === $v ) || ( is_string( $v ) && mb_strlen( $v ) > 191 ) ) {
						$e[] = 'context:invalid';
						break;
					}
				}
			}
		}
		return $e;
	}

	/**
	 * Event schema review_captain.experience_event.v1.
	 *
	 * @param array<string, mixed> $p Payload.
	 * @return list<string>
	 */
	private static function validate_schema_event( array $p ): array {
		$e    = array();
		$json = wp_json_encode( $p );
		if ( false === $json || strlen( (string) $json ) > self::MAX_BYTES ) {
			$e[] = 'payload:too_long';
		}
		if ( self::SCHEMA !== $p['schema'] ) {
			$e[] = 'schema:invalid';
		}
		if ( ! isset( $p['contract'] ) ) {
			$e[] = 'contract:missing';
		} elseif ( ! is_string( $p['contract'] ) || ! preg_match( '/^1\.\d+$/', $p['contract'] ) ) {
			$e[] = 'contract:incompatible';
		}
		$source_ok = self::string_field( $p, 'source', 64, true, $e );
		if ( $source_ok && ! preg_match( '/^[a-z0-9][a-z0-9_.-]{1,63}$/', $p['source'] ) ) {
			$e[]       = 'source:invalid';
			$source_ok = false;
		}
		if ( self::string_field( $p, 'event_id', 36, true, $e ) && ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $p['event_id'] ) ) {
			$e[] = 'event_id:invalid';
		}
		if ( self::string_field( $p, 'experience_id', 191, true, $e ) && $source_ok && ! str_starts_with( $p['experience_id'], $p['source'] . ':' ) ) {
			$e[] = 'experience_id:not_owner';
		}
		self::enum_field( $p, 'event_type', self::SCHEMA_EVENT_TYPES, $e );
		if ( self::time_field( $p, 'occurred_at', true, $e ) && strtotime( $p['occurred_at'] ) > time() + HOUR_IN_SECONDS ) {
			$e[] = 'occurred_at:future';
		}

		if ( ! isset( $p['object_ref'] ) ) {
			$e[] = 'object_ref:missing';
		} elseif ( ! self::valid_object_ref( $p['object_ref'] ) ) {
			$e[] = 'object_ref:invalid';
		}

		if ( ! array_key_exists( 'recipient_ref', $p ) ) {
			$e[] = 'recipient_ref:missing';
		} elseif ( null === $p['recipient_ref'] ) {
			if ( ! in_array( $p['event_type'] ?? null, self::REVOKING_EVENT_TYPES, true ) ) {
				$e[] = 'recipient_ref:missing';
			}
		} else {
			self::recipient_ref( $p, $e );
		}

		if ( ! array_key_exists( 'locale', $p ) ) {
			$e[] = 'locale:missing';
		} elseif ( null !== $p['locale'] && ( ! is_string( $p['locale'] ) || ! preg_match( '/^[A-Za-z]{2,3}([_-][A-Za-z0-9]{2,8})*$/', $p['locale'] ) || strlen( $p['locale'] ) > 35 ) ) {
			$e[] = 'locale:invalid';
		}

		if ( ! isset( $p['send_permission'] ) ) {
			$e[] = 'send_permission:missing';
		} elseif ( ! is_array( $p['send_permission'] ) ) {
			$e[] = 'send_permission:invalid';
		} else {
			$sp = $p['send_permission'];
			if ( ! in_array( $sp['status'] ?? null, array( 'granted', 'denied', 'revoked', 'unknown' ), true ) ) {
				$e[] = 'send_permission.status:invalid';
			}
			if ( 'email' !== ( $sp['channel'] ?? null ) ) {
				$e[] = 'send_permission.channel:invalid';
			}
			if ( isset( $sp['basis'] ) && ! in_array( $sp['basis'], array( 'consent', 'legitimate_interest', 'contract', 'other' ), true ) ) {
				$e[] = 'send_permission.basis:invalid';
			}
			if ( isset( $sp['recorded_at'] ) && ! self::is_iso8601( $sp['recorded_at'] ) ) {
				$e[] = 'send_permission.recorded_at:invalid';
			}
			foreach ( array( 'origin' => 64, 'evidence_ref' => 191 ) as $field => $max ) {
				if ( isset( $sp[ $field ] ) && ( ! is_string( $sp[ $field ] ) || mb_strlen( $sp[ $field ] ) > $max ) ) {
					$e[] = 'send_permission.' . $field . ':invalid';
				}
			}
		}

		self::string_field( $p, 'idempotency_key', 191, true, $e );

		if ( isset( $p['context'] ) ) {
			if ( ! is_array( $p['context'] ) ) {
				$e[] = 'context:invalid';
			} else {
				$c = $p['context'];
				foreach ( array( 'campaign_ref' => 64, 'affiliate_ref' => 64, 'trigger_detail' => 40 ) as $field => $max ) {
					if ( isset( $c[ $field ] ) && ( ! is_string( $c[ $field ] ) || mb_strlen( $c[ $field ] ) > $max ) ) {
						$e[] = 'context.' . $field . ':invalid';
					}
				}
				if ( isset( $c['items'] ) ) {
					if ( ! is_array( $c['items'] ) || count( $c['items'] ) > 50 ) {
						$e[] = 'context.items:invalid';
					} else {
						foreach ( $c['items'] as $item ) {
							if ( ! self::valid_object_ref( $item ) ) {
								$e[] = 'context.items:invalid';
								break;
							}
						}
					}
				}
				foreach ( array_keys( $c ) as $k ) {
					if ( ! in_array( $k, array( 'campaign_ref', 'affiliate_ref', 'items', 'trigger_detail' ), true ) ) {
						$e[] = 'context.' . $k . ':forbidden';
					}
				}
			}
		}
		if ( isset( $p['producer'] ) && ( ! is_array( $p['producer'] ) || ! is_string( $p['producer']['plugin'] ?? null ) || ! is_string( $p['producer']['version'] ?? null ) ) ) {
			$e[] = 'producer:invalid';
		}
		return $e;
	}

	/**
	 * Object reference {type, id, context_type}.
	 *
	 * @param mixed $ref Ref.
	 */
	private static function valid_object_ref( mixed $ref ): bool {
		if ( ! is_array( $ref ) ) {
			return false;
		}
		if ( ! isset( $ref['type'], $ref['id'] ) || ! is_string( $ref['type'] ) || ! is_string( $ref['id'] ) ) {
			return false;
		}
		if ( '' === $ref['type'] || mb_strlen( $ref['type'] ) > 40 || '' === $ref['id'] || mb_strlen( $ref['id'] ) > 64 ) {
			return false;
		}
		$ct = $ref['context_type'] ?? null;
		return null === $ct || ( is_string( $ct ) && in_array( $ct, Review::CONTEXT_TYPES, true ) );
	}

	/**
	 * Recipient reference: opaque, no e-mail.
	 *
	 * @param array<string, mixed> $p Payload.
	 * @param list<string>         $e Errors.
	 */
	private static function recipient_ref( array $p, array &$e ): void {
		if ( ! self::string_field( $p, 'recipient_ref', 191, true, $e ) ) {
			return;
		}
		if ( str_contains( $p['recipient_ref'], '@' ) ) {
			$e[] = 'recipient_ref:pii';
		}
	}

	/**
	 * Recursively reject forbidden field names and e-mail-like values.
	 *
	 * @param array<mixed> $data Data.
	 * @param string       $path Path prefix.
	 * @return list<string>
	 */
	private static function forbidden( array $data, string $path ): array {
		$e = array();
		foreach ( $data as $k => $v ) {
			$name = '' === $path ? (string) $k : $path . '.' . $k;
			if ( is_string( $k ) && in_array( strtolower( $k ), self::FORBIDDEN_FIELDS, true ) ) {
				$e[] = $name . ':forbidden';
				continue;
			}
			if ( is_array( $v ) ) {
				$e = array_merge( $e, self::forbidden( $v, $name ) );
			} elseif ( is_string( $v ) && 'recipient_ref' !== $name && preg_match( self::EMAIL_PATTERN, $v ) ) {
				$e[] = $name . ':pii';
			}
		}
		return $e;
	}

	/**
	 * Required/optional string field with max length.
	 *
	 * @param array<string, mixed> $p        Payload.
	 * @param string               $field    Field.
	 * @param int                  $max      Max length.
	 * @param bool                 $required Required.
	 * @param list<string>         $e        Errors.
	 */
	private static function string_field( array $p, string $field, int $max, bool $required, array &$e ): bool {
		if ( ! isset( $p[ $field ] ) || '' === $p[ $field ] ) {
			if ( $required ) {
				$e[] = $field . ':missing';
			}
			return false;
		}
		if ( ! is_string( $p[ $field ] ) ) {
			$e[] = $field . ':invalid';
			return false;
		}
		if ( mb_strlen( $p[ $field ] ) > $max ) {
			$e[] = $field . ':too_long';
			return false;
		}
		return true;
	}

	/**
	 * Enum field.
	 *
	 * @param array<string, mixed> $p       Payload.
	 * @param string               $field   Field.
	 * @param list<string>         $allowed Allowed values.
	 * @param list<string>         $e       Errors.
	 */
	private static function enum_field( array $p, string $field, array $allowed, array &$e ): void {
		if ( ! isset( $p[ $field ] ) ) {
			$e[] = $field . ':missing';
		} elseif ( ! in_array( $p[ $field ], $allowed, true ) ) {
			$e[] = $field . ':invalid';
		}
	}

	/**
	 * ISO 8601 time field.
	 *
	 * @param array<string, mixed> $p        Payload.
	 * @param string               $field    Field.
	 * @param bool                 $utc_only Require Z.
	 * @param list<string>         $e        Errors.
	 */
	private static function time_field( array $p, string $field, bool $utc_only, array &$e ): bool {
		if ( ! isset( $p[ $field ] ) ) {
			$e[] = $field . ':missing';
			return false;
		}
		if ( ! self::is_iso8601( $p[ $field ], $utc_only ) ) {
			$e[] = $field . ':invalid';
			return false;
		}
		return true;
	}
}
