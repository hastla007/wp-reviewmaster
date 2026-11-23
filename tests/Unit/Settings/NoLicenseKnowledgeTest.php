<?php
/**
 * Guard (SET-RC-06, SET-RC-P6): the free core knows no license keys and declares no entitlement in settings.
 */

declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Settings;

use ReviewCaptain\Admin\SettingsHints;
use ReviewCaptain\Settings\SettingsSchema;
use ReviewCaptain\Tests\TestCase;

final class NoLicenseKnowledgeTest extends TestCase {

	/**
	 * Names of the form review.<word> the core may use: its own audit events (AuditLog) and the
	 * event schema (review_captain.review.v1). Everything else of this form could be an
	 * entitlement key of the add-on (prefix "review.") and needs a conscious decision here.
	 */
	private const CORE_REVIEW_NAMES = array(
		'review.correction_approved',
		'review.correction_rejected',
		'review.correction_requested',
		'review.created_manual',
		'review.edited',
		'review.photo_deleted',
		'review.reported',
		'review.reports_dismissed',
		'review.status_changed',
		'review.tags_changed',
		'review.withdrawn_by_author',
		'review.v1',
	);

	/**
	 * Shipped code of the core (no tests, no docs). PHP files without comments: a docblock may
	 * explain that the core has no license code, the code itself must not have any.
	 *
	 * @return array<string, string> path => code
	 */
	private static function shipped(): array {
		$out   = array();
		$files = array( 'review-captain.php', 'uninstall.php' );
		foreach ( array( 'src', 'templates', 'blocks', 'assets' ) as $dir ) {
			if ( ! is_dir( REVIEW_CAPTAIN_DIR . $dir ) ) {
				continue;
			}
			$it = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( REVIEW_CAPTAIN_DIR . $dir, \FilesystemIterator::SKIP_DOTS ) );
			foreach ( $it as $file ) {
				if ( in_array( $file->getExtension(), array( 'php', 'js', 'json', 'css' ), true ) ) {
					$files[] = substr( str_replace( '\\', '/', $file->getPathname() ), strlen( REVIEW_CAPTAIN_DIR ) );
				}
			}
		}
		foreach ( $files as $rel ) {
			if ( ! is_file( REVIEW_CAPTAIN_DIR . $rel ) ) {
				continue;
			}
			$code = (string) file_get_contents( REVIEW_CAPTAIN_DIR . $rel );
			if ( str_ends_with( $rel, '.php' ) ) {
				$stripped = '';
				foreach ( token_get_all( $code ) as $token ) {
					if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
						continue;
					}
					$stripped .= is_array( $token ) ? $token[1] : $token;
				}
				$code = $stripped;
			}
			$out[ $rel ] = $code;
		}
		return $out;
	}

	public function test_scan_covers_the_shipped_code(): void {
		$files = self::shipped();
		$this->assertArrayHasKey( 'review-captain.php', $files );
		$this->assertArrayHasKey( 'src/Admin/SettingsHints.php', $files );
		$this->assertArrayHasKey( 'src/Settings/SettingsSchema.php', $files );
		$this->assertGreaterThan( 100, count( $files ) );
	}

	public function test_no_entitlement_keys_or_entitlement_word_in_shipped_code(): void {
		$found = array();
		foreach ( self::shipped() as $rel => $code ) {
			// Any review.<word> that is not a known core name (entitlement keys use the prefix "review.").
			if ( preg_match_all( '/(?<![a-z0-9_])review\.[a-z0-9_]+/', $code, $m ) ) {
				foreach ( array_diff( array_unique( $m[0] ), self::CORE_REVIEW_NAMES ) as $name ) {
					$found[] = $rel . ': ' . $name;
				}
			}
			if ( preg_match( '/entitlement/i', $code, $m ) ) {
				$found[] = $rel . ': ' . $m[0];
			}
		}
		$this->assertSame( array(), $found );
	}

	public function test_no_license_code_and_no_addon_internals_in_shipped_code(): void {
		$found = array();
		foreach ( self::shipped() as $rel => $code ) {
			// Plugin header lines "License:"/"License URI:" are comments and already stripped.
			foreach ( array(
				'/licen[cs]/i'                              => 'license word',
				'/wplc/i'                                   => 'license client prefix',
				'/ReviewCaptainPro/'                        => 'add-on namespace',
				'/REVIEW_CAPTAIN_PRO_/'                     => 'add-on constant',
				'/\bis_entitled\b|\bhas_entitlement\b/'     => 'entitlement check',
				'/sodium_crypto_sign_verify_detached/'      => 'license signature check',
			) as $pattern => $what ) {
				if ( preg_match( $pattern, $code, $m ) ) {
					$found[] = $rel . ': ' . $what . ' (' . $m[0] . ')';
				}
			}
		}
		$this->assertSame( array(), $found );
	}

	public function test_guard_patterns_catch_what_they_should(): void {
		$sample = "<?php\n\$k = 'review.platform_replies'; \$x = \$license->status();";
		$this->assertMatchesRegularExpression( '/licen[cs]/i', $sample );
		preg_match_all( '/(?<![a-z0-9_])review\.[a-z0-9_]+/', $sample, $m );
		$this->assertSame( array( 'review.platform_replies' ), array_values( array_diff( $m[0], self::CORE_REVIEW_NAMES ) ) );
		// The event schema review_captain.review.v1 is not mistaken for a key.
		preg_match_all( '/(?<![a-z0-9_])review\.[a-z0-9_]+/', 'review_captain.review.v1 x_review.analytics', $m2 );
		$this->assertSame( array( 'review.v1' ), $m2[0] );
	}

	public function test_no_settings_field_declares_an_entitlement_or_belongs_to_pro(): void {
		foreach ( SettingsSchema::fields() as $tab => $fields ) {
			foreach ( $fields as $key => $field ) {
				$this->assertArrayNotHasKey( 'entitlement', $field, "{$tab}.{$key}" );
				$this->assertSame( 'core', $field['owner'], "{$tab}.{$key}" );
			}
		}
	}

	public function test_hint_ids_are_not_entitlement_keys(): void {
		foreach ( SettingsHints::defaults() as $hint ) {
			$this->assertDoesNotMatchRegularExpression( '/^review\./', $hint['id'] );
		}
	}
}
