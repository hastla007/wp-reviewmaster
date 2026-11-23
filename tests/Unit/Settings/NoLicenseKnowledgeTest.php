<?php
/**
 * Guard (SET-RC-06): the free core knows no license keys and declares no entitlement in settings.
 */

declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Settings;

use ReviewCaptain\Admin\SettingsHints;
use ReviewCaptain\Settings\SettingsSchema;
use ReviewCaptain\Tests\TestCase;

final class NoLicenseKnowledgeTest extends TestCase {

	/**
	 * Shipped code of the core (no tests, no docs).
	 *
	 * @return array<string, string> path => contents
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
			if ( is_file( REVIEW_CAPTAIN_DIR . $rel ) ) {
				$out[ $rel ] = (string) file_get_contents( REVIEW_CAPTAIN_DIR . $rel );
			}
		}
		return $out;
	}

	public function test_no_entitlement_keys_or_entitlement_word_in_shipped_code(): void {
		$found = array();
		foreach ( self::shipped() as $rel => $code ) {
			// Entitlement keys of the add-on: review.<feature>, quoted or not (audit events like
			// review.status_changed are core events and not listed here).
			if ( preg_match( '/review\.(review_requests|platform_sync|advanced_widgets|analytics|suite_integrations|custom_connectors|platform_replies)\b/', $code, $m ) ) {
				$found[] = $rel . ': ' . $m[0];
			}
			if ( preg_match( '/entitlement/i', $code, $m ) ) {
				$found[] = $rel . ': ' . $m[0];
			}
		}
		$this->assertSame( array(), $found );
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
