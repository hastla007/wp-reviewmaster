<?php
/**
 * SET-RC-P6: the hint ids are a contract with Review Captain Pro (CoreBridge::HINT_*, 'replaces_hint').
 * Renaming one would show the grey line next to the real add-on field, without any error.
 */

declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Admin;

use ReviewCaptain\Admin\SettingsHints;
use ReviewCaptain\Tests\TestCase;

final class SettingsHintsContractTest extends TestCase {

	/**
	 * Published ids and their tabs (docs/settings/pro-matrix.md). Changing this list is a contract change.
	 */
	private const CONTRACT = array(
		'REQUESTS'    => array( 'requests.invitations', 'requests' ),
		'SENDER'      => array( 'requests.sender', 'requests' ),
		'SEND_WINDOW' => array( 'requests.send_window', 'requests' ),
		'LIMITS'      => array( 'requests.limits', 'requests' ),
		'REMINDERS'   => array( 'requests.reminders', 'requests' ),
		'PLATFORM'    => array( 'sources.platform_import', 'sources' ),
		'WIDGETS'     => array( 'display.advanced_widgets', 'display' ),
		'REPORTS'     => array( 'general.reports', 'general' ),
	);

	public function test_hint_ids_and_tabs_are_stable(): void {
		$constants = ( new \ReflectionClass( SettingsHints::class ) )->getConstants();
		$this->assertSame( array_combine( array_keys( self::CONTRACT ), array_column( self::CONTRACT, 0 ) ), $constants );

		$expected = array_column( self::CONTRACT, 1, 0 );
		$tabs     = array_column( SettingsHints::defaults(), 'tab', 'id' );
		ksort( $expected );
		ksort( $tabs );
		$this->assertSame( $expected, $tabs, 'every core line has a published id and keeps its tab' );
	}

	public function test_every_hint_id_is_documented_in_the_pro_matrix(): void {
		$doc = (string) file_get_contents( dirname( __DIR__, 3 ) . '/docs/settings/pro-matrix.md' );
		foreach ( self::CONTRACT as $name => [ $id, $tab ] ) {
			$this->assertStringContainsString( '| `' . $id . '` (`' . $name . '`) | ' . $tab . ' |', $doc, $id );
		}
	}
}
