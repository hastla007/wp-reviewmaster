<?php
/**
 * Release check (SET-RC-05, like SET-024): every core setting has a label and is read somewhere.
 */

declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Settings;

use ReviewCaptain\Admin\SettingsFields;
use ReviewCaptain\Settings\SettingsSchema;
use ReviewCaptain\Tests\TestCase;

final class SettingsReadersTest extends TestCase {

	/**
	 * Core fields consumed only by Review Captain Pro (automatic source sync,
	 * pro:src/Platforms/Sync/SyncRunner.php). Documented in docs/settings/release-gate.md.
	 * Pro-owned fields (owner = pro) are skipped automatically.
	 */
	private const PRO_ONLY = array(
		'sources.sync_window_start',
		'sources.sync_window_end',
		'sources.max_pages_per_run',
		'sources.pause_after_auth_errors',
	);

	/**
	 * Files that only describe settings (schema, texts) and therefore do not count as readers.
	 */
	private const NOT_READERS = array( 'src/Settings/SettingsSchema.php', 'src/Admin/SettingsFields.php' );

	/**
	 * PHP sources of src/ that may read settings.
	 *
	 * @return array<string, string> path => contents
	 */
	private static function sources(): array {
		$out = array();
		$it  = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( REVIEW_CAPTAIN_DIR . 'src', \FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $file ) {
			if ( 'php' !== $file->getExtension() ) {
				continue;
			}
			$rel = substr( str_replace( '\\', '/', $file->getPathname() ), strlen( REVIEW_CAPTAIN_DIR ) );
			if ( in_array( $rel, self::NOT_READERS, true ) ) {
				continue;
			}
			$out[ $rel ] = (string) file_get_contents( $file->getPathname() );
		}
		return $out;
	}

	public function test_every_core_field_has_a_label(): void {
		$missing = array();
		foreach ( SettingsSchema::fields() as $tab => $fields ) {
			foreach ( $fields as $key => $field ) {
				if ( 'core' !== $field['owner'] ) {
					continue;
				}
				if ( SettingsFields::label( $tab, $key, $field ) === $key ) {
					$missing[] = $tab . '.' . $key;
				}
			}
		}
		$this->assertSame( array(), $missing, 'Core settings without a label (SettingsFields::texts())' );
	}

	public function test_every_core_field_has_a_reader_in_src(): void {
		$sources = self::sources();
		$unread  = array();
		foreach ( SettingsSchema::fields() as $tab => $fields ) {
			foreach ( $fields as $key => $field ) {
				if ( 'core' !== $field['owner'] || in_array( $tab . '.' . $key, self::PRO_ONLY, true ) ) {
					continue;
				}
				// A reader is either get( 'tab', 'key' ) or a file that names both the tab and the key
				// as literals (loops over keys, array access on a tab's values).
				$pattern = '/[\'"]' . preg_quote( $tab, '/' ) . '[\'"]\s*,\s*[\'"]' . preg_quote( $key, '/' ) . '[\'"]/';
				$found   = false;
				foreach ( $sources as $code ) {
					if ( preg_match( $pattern, $code ) || ( str_contains( $code, "'" . $tab . "'" ) && str_contains( $code, "'" . $key . "'" ) ) ) {
						$found = true;
						break;
					}
				}
				if ( ! $found ) {
					$unread[] = $tab . '.' . $key;
				}
			}
		}
		$this->assertSame( array(), $unread, 'Core settings nobody reads (dead settings, SET-RC-05)' );
	}

	public function test_removed_dead_settings_stay_harmless_when_stored(): void {
		update_option( \ReviewCaptain\Settings\Settings::OPTION, array( 'general' => array( 'default_language' => 'fr_FR' ) ) );
		$s = new \ReviewCaptain\Settings\Settings();
		$this->assertArrayNotHasKey( 'default_language', SettingsSchema::fields()['general'] );
		$this->assertSame( 'pending', $s->get( 'general', 'new_review_status' ), 'defaults still apply' );
		$this->assertSame( 'fr_FR', $s->get( 'general', 'default_language' ), 'unknown stored keys are kept, never interpreted' );
	}
}
