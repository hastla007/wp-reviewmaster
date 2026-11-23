<?php
/**
 * Old/new values of settings changes for the audit log (SET-RC-17).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Audit;

use ReviewCaptain\Settings\SettingsSchema;

/**
 * Builds the `changes` context of settings audit entries. Values are reduced before they reach the
 * log: secrets are never written, e-mail addresses are masked, values of fields marked as personal
 * or contact configuration are hidden, other texts are shortened to TEXT_MAX characters.
 */
final class SettingsChanges {

	/**
	 * Keys that look like secrets (same rule as the configuration export).
	 */
	private const SECRET_PATTERN = '/(secret|token|key|password|passwd)/i';

	/**
	 * Placeholder for values that are never logged.
	 */
	public const HIDDEN = '[verborgen]';

	/**
	 * Texts longer than this are shortened.
	 */
	public const TEXT_MAX = 40;

	/**
	 * Changes between two settings states for the given "tab.key" list.
	 *
	 * @param array<string, mixed> $before  Values before (by tab).
	 * @param array<string, mixed> $after   Values after (by tab).
	 * @param list<string>         $changed Changed "tab.key" names.
	 * @return list<array{field: string, from: string|int|bool|null, to: string|int|bool|null}>
	 */
	public static function between( array $before, array $after, array $changed ): array {
		$schema = SettingsSchema::fields();
		$out    = array();
		foreach ( $changed as $name ) {
			[ $tab, $key ] = array_pad( explode( '.', (string) $name, 2 ), 2, '' );
			$field         = $schema[ $tab ][ $key ] ?? array( 'type' => 'string' );
			$out[]         = array(
				'field' => (string) $name,
				'from'  => self::value( $key, $field, $before[ $tab ][ $key ] ?? null ),
				'to'    => self::value( $key, $field, $after[ $tab ][ $key ] ?? null ),
			);
		}
		return $out;
	}

	/**
	 * Loggable form of one value.
	 *
	 * @param string               $key   Field key.
	 * @param array<string, mixed> $field Field definition.
	 * @param mixed                $value Value.
	 * @return string|int|bool|null
	 */
	public static function value( string $key, array $field, mixed $value ): string|int|bool|null {
		$type = (string) ( $field['type'] ?? 'string' );
		if ( 'secret' === $type || preg_match( self::SECRET_PATTERN, $key ) ) {
			return self::HIDDEN;
		}
		if ( null === $value || is_bool( $value ) || is_int( $value ) ) {
			return $value;
		}
		if ( is_array( $value ) ) {
			if ( 'personal' === ( $field['privacy'] ?? 'none' ) ) {
				// Personal lists (e.g. names): only "changed" is logged (SET-RC-P1).
				return array() === $value ? '' : self::HIDDEN;
			}
			// Lists (roles, e-mail recipients, …): every element passes the same rules as a single
			// value, so addresses in submissions.notify_recipients are masked as well (P4-2, SET-RC-P1).
			$item  = array( 'type' => 'email_list' === $type ? 'email' : 'list_item' ) + $field;
			$parts = array_map( static fn( $v ): string => is_scalar( $v ) ? (string) self::value( $key, $item, (string) $v ) : '', $value );
			return self::shorten( implode( ', ', $parts ), 100 );
		}
		if ( ! is_scalar( $value ) ) {
			return null;
		}
		$value = (string) $value;
		if ( 'email' === $type || is_email( $value ) ) {
			return self::mask_email( $value );
		}
		if ( in_array( $field['privacy'] ?? 'none', array( 'personal', 'config' ), true ) ) {
			// May be a person's name (e.g. requests.sender_name): only "changed" is logged.
			return '' === $value ? '' : self::HIDDEN;
		}
		$value = self::mask_embedded_emails( $value );
		if ( in_array( $type, array( 'string', 'textarea' ), true ) ) {
			return self::shorten( $value, self::TEXT_MAX );
		}
		return self::shorten( $value, 100 );
	}

	/**
	 * Mask e-mail addresses inside a longer text (e.g. a notification body naming a contact).
	 *
	 * @param string $text Text.
	 */
	public static function mask_embedded_emails( string $text ): string {
		return (string) preg_replace_callback(
			'/[^\s@<>"\'(),;:]+@[^\s@<>"\'(),;:]+\.[a-z]{2,}/iu',
			static fn( array $m ): string => self::mask_email( $m[0] ),
			$text
		);
	}

	/**
	 * Mask an e-mail address: first character of the local part plus the domain.
	 *
	 * @param string $email Address.
	 */
	public static function mask_email( string $email ): string {
		if ( '' === $email ) {
			return '';
		}
		$at = strrpos( $email, '@' );
		if ( false === $at ) {
			return self::HIDDEN;
		}
		return mb_substr( $email, 0, 1 ) . '***' . substr( $email, $at );
	}

	/**
	 * Shorten a text to a maximum length (with an ellipsis).
	 *
	 * @param string $text Text.
	 * @param int    $max  Max characters.
	 */
	public static function shorten( string $text, int $max ): string {
		$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
		return mb_strlen( $text ) > $max ? mb_substr( $text, 0, $max - 1 ) . '…' : $text;
	}
}
