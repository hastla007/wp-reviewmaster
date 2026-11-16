<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\ImportExport;

use ReviewCaptain\ImportExport\ImportExportModule;
use ReviewCaptain\Plugin;
use ReviewCaptain\Security\Capabilities;
use ReviewCaptain\Tests\TestCase;

final class ImportExportModuleTest extends TestCase {

	private ImportExportModule $module;

	protected function setUp(): void {
		parent::setUp();
		$this->module = new ImportExportModule();
		$this->module->register( Plugin::instance() );
	}

	private function nonce( string $action ): void {
		$_REQUEST['_rc_nonce'] = wp_create_nonce( Capabilities::nonce_action( $action ) );
		$_POST['_rc_nonce']    = $_REQUEST['_rc_nonce'];
	}

	public function test_submenu_registered_with_privacy_capability(): void {
		do_action( 'admin_menu' );
		$menu = $GLOBALS['rc_test_submenus'][ ImportExportModule::PAGE ] ?? null;
		$this->assertNotNull( $menu );
		$this->assertSame( 'review-captain', $menu['parent'] );
		$this->assertSame( Capabilities::MANAGE_PRIVACY, $menu['cap'] );
		$this->assertNotFalse( has_action( 'admin_post_review_captain_import_rollback' ) );
	}

	public function test_handlers_require_capability_and_nonce(): void {
		$this->login_with( Capabilities::MODERATE );
		$this->nonce( 'export' );
		try {
			$this->module->handle_export();
			$this->fail( 'expected wp_die' );
		} catch ( \RC_Test_Die $e ) {
			$this->assertStringContainsString( 'Berechtigung', $e->getMessage() );
		}

		$this->login_with( Capabilities::MANAGE_PRIVACY );
		$_REQUEST['_rc_nonce'] = 'wrong';
		try {
			$this->module->handle_rollback();
			$this->fail( 'expected nonce failure' );
		} catch ( \RC_Test_Die $e ) {
			$this->assertSame( 'nonce_failed', $e->getMessage() );
		}

		// Upload additionally needs the moderate capability.
		$this->nonce( 'import_upload' );
		try {
			$this->module->handle_upload();
			$this->fail( 'expected wp_die' );
		} catch ( \RC_Test_Die $e ) {
			$this->assertStringContainsString( 'Berechtigung', $e->getMessage() );
		}
	}

	public function test_rollback_handler(): void {
		$path = tempnam( sys_get_temp_dir(), 'rc-csv' );
		file_put_contents( $path, "kind,platform,rating_value,body,author_name,published_at\nreview,local,5,Text,Anna,2026-11-01\n" );
		$batch = $this->module->importer()->import( $path, 1 )['batch_id'];
		unlink( $path );

		$this->login_with( Capabilities::MANAGE_PRIVACY );
		$this->nonce( 'import_rollback' );
		$_POST['batch_id'] = (string) $batch;
		try {
			$this->module->handle_rollback();
		} catch ( \RC_Test_Die $e ) {
			$this->assertStringContainsString( 'redirect:', $e->getMessage() );
			$this->assertStringContainsString( 'rc_notice=rolled_back', $e->getMessage() );
			$this->assertStringContainsString( 'rc_deleted=1', $e->getMessage() );
		}
		$this->assertSame( 0, $this->rows( 'reviews' ) );
	}

	public function test_render_shows_forms_and_batches(): void {
		$path = tempnam( sys_get_temp_dir(), 'rc-csv' );
		file_put_contents( $path, "kind,platform,rating_value,body,author_name,published_at\nreview,local,5,Text,Anna,2026-11-01\n" );
		$this->module->importer()->import( $path, 1, '<b>x</b>.csv' );
		unlink( $path );
		$this->login_with( Capabilities::MANAGE_PRIVACY );
		ob_start();
		$this->module->render();
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'review_captain_export', $html );
		$this->assertStringContainsString( 'include_personal', $html );
		$this->assertStringContainsString( 'review_captain_import_upload', $html );
		$this->assertStringContainsString( 'review_captain_import_rollback', $html );
		$this->assertStringNotContainsString( '<b>x</b>', $html );
	}

	public function test_export_filters_are_whitelisted(): void {
		$this->assertSame(
			array( 'kind' => 'review', 'status' => 'approved', 'date_from' => '2026-01-01' ),
			ImportExportModule::export_filters( array( 'kind' => 'review', 'platform' => 'mars', 'status' => 'approved', 'date_from' => '2026-01-01', 'date_to' => "2026-01-01' OR 1=1" ) )
		);
	}
}
