<?php
/**
 * Smoke test: every admin page and setup step renders for an administrator without PHP notices or fatals.
 *
 * @package ReviewCaptain
 */

use ReviewCaptain\Admin;
use ReviewCaptain\Reviews\ReviewService;

class AdminRenderTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'dashboard' );
		$id = ReviewService::create_local( array( 'rating' => 4, 'content' => 'Smoke <b>test</b>', 'author_name' => 'Smoke Tester' ) );
		ReviewService::moderate( $id, 'approved' );
		$this->review_id = $id;
	}

	private int $review_id = 0;

	private function render( callable $cb, array $get = array() ): string {
		$_GET = $get;
		ob_start();
		$cb();
		$_GET = array();
		return (string) ob_get_clean();
	}

	public function test_pages_render(): void {
		$pages = array(
			array( array( Admin\DashboardPage::class, 'render' ), array() ),
			array( array( Admin\ReviewsPage::class, 'render' ), array( 'page' => 'review-captain-reviews' ) ),
			array( array( Admin\ReviewsPage::class, 'render' ), array( 'action' => 'view', 'id' => (string) $this->review_id ) ),
			array( array( Admin\ReviewsPage::class, 'render' ), array( 'action' => 'add' ) ),
			array( array( Admin\ImportExportPage::class, 'render' ), array() ),
			array( array( Admin\WidgetsPage::class, 'render' ), array() ),
			array( array( Admin\WidgetsPage::class, 'render' ), array( 'action' => 'add' ) ),
			array( array( Admin\TestimonialsPage::class, 'render' ), array() ),
			array( array( Admin\TestimonialsPage::class, 'render' ), array( 'action' => 'add' ) ),
			array( array( Admin\SourcesPage::class, 'render' ), array() ),
			array( array( Admin\IntegrationsPage::class, 'render' ), array() ),
			array( array( Admin\DiagnosticsPage::class, 'render' ), array() ),
		);
		foreach ( array_keys( \ReviewCaptain\Settings\Schema::group_labels() ) as $tab ) {
			$pages[] = array( array( Admin\SettingsPage::class, 'render' ), array( 'tab' => $tab ) );
		}
		foreach ( array_keys( Admin\SetupWizard::steps() ) as $step ) {
			$pages[] = array( array( Admin\SetupWizard::class, 'render' ), array( 'step' => $step ) );
		}
		foreach ( $pages as $p ) {
			$html = $this->render( $p[0], $p[1] );
			$this->assertStringContainsString( '<div class="wrap', $html, implode( '::', $p[0] ) . ' ' . wp_json_encode( $p[1] ) );
		}
		$list = $this->render( array( Admin\ReviewsPage::class, 'render' ), array() );
		$this->assertStringContainsString( 'Smoke Tester', $list );
		$this->assertStringNotContainsString( '<b>test</b>', $list );
	}

	public function test_settings_privacy_tab_shows_danger_zone_only_with_rights(): void {
		$html = $this->render( array( Admin\SettingsPage::class, 'render' ), array( 'tab' => 'privacy' ) );
		$this->assertStringContainsString( 'review_captain_erase_all', $html );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$html = $this->render( array( Admin\SettingsPage::class, 'render' ), array( 'tab' => 'privacy' ) );
		$this->assertStringNotContainsString( 'review_captain_erase_all', $html );
	}

	public function test_pro_tabs_are_disabled_without_entitlement(): void {
		$html = $this->render( array( Admin\SettingsPage::class, 'render' ), array( 'tab' => 'requests' ) );
		$this->assertStringContainsString( 'take effect with Review Captain Pro', $html );
		$this->assertMatchesRegularExpression( '/name="settings\[sender_email\]"[^>]*disabled/', $html );
	}
}
