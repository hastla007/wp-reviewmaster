<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Admin;

use ReviewCaptain\Admin\AdminModule;
use ReviewCaptain\Admin\DashboardPage;
use ReviewCaptain\Admin\DiagnosticsPage;
use ReviewCaptain\Admin\Profiles;
use ReviewCaptain\Admin\SetupChecklist;
use ReviewCaptain\Admin\SourcesPage;
use ReviewCaptain\Admin\WidgetsPage;
use ReviewCaptain\Domain\Platform;
use ReviewCaptain\Domain\Review;
use ReviewCaptain\Domain\ReviewStatus;
use ReviewCaptain\Domain\Verification;
use ReviewCaptain\Security\Capabilities;
use ReviewCaptain\Settings\Settings;

final class PagesTest extends AdminTestCase {

	public function test_module_is_inactive_outside_admin(): void {
		\RC_Test_State::$is_admin = false;
		( new AdminModule() )->register( $this->plugin() );
		$this->assertFalse( has_action( 'admin_menu' ) );
		$this->assertFalse( has_action( 'admin_post_review_captain_save_settings' ) );
	}

	public function test_menu_pages_and_capabilities(): void {
		$GLOBALS['rc_test_submenus'] = array();
		$module                      = new AdminModule();
		$module->register( $this->plugin() );
		$this->assertNotFalse( has_action( 'admin_post_review_captain_save_settings' ) );
		$module->menu();
		$this->assertSame( Capabilities::MODERATE, $GLOBALS['rc_test_menus']['review-captain']['cap'] );
		$expected = array(
			'review-captain'             => Capabilities::MODERATE,
			'review-captain-reviews'     => Capabilities::MODERATE,
			'review-captain-widgets'     => Capabilities::MODERATE,
			'review-captain-sources'     => Capabilities::MANAGE_SOURCES,
			'review-captain-settings'    => Capabilities::MANAGE_SETTINGS,
			'review-captain-diagnostics' => Capabilities::VIEW_REPORTS,
		);
		foreach ( $expected as $slug => $cap ) {
			$this->assertSame( $cap, $GLOBALS['rc_test_submenus'][ $slug ]['cap'], $slug );
			$this->assertSame( 'review-captain', $GLOBALS['rc_test_submenus'][ $slug ]['parent'] );
		}
		foreach ( array( 'review-captain-requests', 'review-captain-analytics', 'review-captain-integrations', 'review-captain-license' ) as $pro ) {
			$this->assertArrayNotHasKey( $pro, $GLOBALS['rc_test_submenus'], 'no empty Pro pages' );
		}
		$GLOBALS['rc_test_styles'] = array();
		$module->assets( 'edit.php' );
		$this->assertSame( array(), $GLOBALS['rc_test_styles'] );
		$module->assets( 'review-captain_page_review-captain-settings' );
		$this->assertSame( array( 'review-captain-admin' ), $GLOBALS['rc_test_styles'] );
	}

	public function test_checklist_states_detection_and_nonce(): void {
		$checklist = new SetupChecklist( $this->plugin() );
		$this->assertSame( array( 'brand' => 'open', 'profile' => 'open', 'form' => 'open', 'widget' => 'open', 'privacy' => 'open' ), $checklist->states() );

		( new Profiles() )->save( array( 'platform' => 'google', 'label' => 'Filiale', 'review_url' => 'https://g.example/review' ) );
		$this->assertSame( 'done', $checklist->states()['profile'], 'auto-detected' );

		$this->login_with( Capabilities::MODERATE );
		$this->post( 'checklist', array( 'step' => 'widget', 'state' => 'skipped' ) );
		$this->assertStringContainsString( 'checklist_updated', $this->run_handler( array( $checklist, 'handle' ) ) );
		$this->assertSame( 'skipped', $checklist->states()['widget'] );
		$this->assertSame( array( 'steps' => array( 'widget' => 'skipped' ), 'dismissed' => false ), get_option( SetupChecklist::OPTION ) );
		$this->assertFalse( get_option( Settings::OPTION ), 'skipping changes no settings' );

		$this->post( 'checklist', array( 'step' => 'brand', 'state' => 'done' ), false );
		$this->assertSame( 'nonce_failed', $this->run_handler( array( $checklist, 'handle' ) ) );
		$this->assertSame( 'open', $checklist->states()['brand'] );

		$this->assertFalse( $checklist->set( 'nope', 'done' ) );
		$checklist->set( 'widget', 'open' );
		$this->assertSame( 'open', $checklist->states()['widget'] );
		$checklist->dismiss( true );
		$this->assertStringContainsString( 'Show setup again', $checklist->render() );
	}

	public function test_dashboard_summary_is_per_platform_and_renders(): void {
		$repo = $this->plugin()->reviews();
		foreach ( array( array( Platform::LOCAL, 4.0, 5.0 ), array( Platform::LOCAL, 5.0, 5.0 ), array( Platform::GOOGLE, 9.0, 10.0 ) ) as [ $platform, $value, $max ] ) {
			$r                   = new Review();
			$r->platform         = $platform;
			$r->rating_value     = $value;
			$r->rating_scale_max = $max;
			$r->body             = 'Text';
			$r->author_name      = 'A';
			$r->status           = ReviewStatus::APPROVED;
			if ( $platform->is_external() ) {
				$r->verification = Verification::EXTERNAL_PLATFORM;
				$r->source_url   = 'https://g.example/1';
			}
			$repo->insert( $r );
		}
		$rows = ( new DashboardPage( $this->plugin() ) )->platform_rows();
		$this->assertCount( 2, $rows );
		$by = array_column( $rows, null, 'platform' );
		$this->assertSame( '4,5', $by['local']['average'] );
		$this->assertSame( '9', $by['google']['average'] );
		$this->assertSame( '10', $by['google']['scale'] );

		$this->login_with( Capabilities::MODERATE );
		ob_start();
		( new DashboardPage( $this->plugin() ) )->render();
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'In Review Captain Pro', $html );
		$this->assertStringContainsString( 'aria-disabled="true"', $html );
		$this->assertStringContainsString( 'Setup', $html );
		$this->assertStringContainsString( 'No cross-platform overall average is calculated', $html );
	}

	public function test_profiles_https_only_and_delete_keeps_reviews(): void {
		$profiles = new Profiles();
		$bad      = $profiles->save( array( 'platform' => 'google', 'label' => 'A', 'profile_url' => 'http://g.example' ) );
		$this->assertContains( 'profile_url_not_https', $bad['errors'] );
		$this->assertContains( 'invalid_platform', $profiles->save( array( 'platform' => 'local', 'label' => 'A', 'profile_url' => 'https://x.example' ) )['errors'] );
		$this->assertContains( 'missing_url', $profiles->save( array( 'platform' => 'google', 'label' => 'A' ) )['errors'] );

		$ok = $profiles->save( array( 'platform' => 'trustpilot', 'label' => 'Shop', 'profile_url' => 'https://tp.example/shop', 'review_url' => 'https://tp.example/evaluate/shop' ) );
		$this->assertSame( array(), $ok['errors'] );
		$row = $profiles->find( (int) $ok['id'] );
		$this->assertSame( 'manual', $row['status'] );

		$r               = new Review();
		$r->platform     = Platform::TRUSTPILOT;
		$r->verification = Verification::EXTERNAL_PLATFORM;
		$r->source_url   = 'https://tp.example/r/1';
		$r->profile_id   = (int) $ok['id'];
		$r->body         = 'Gut';
		$this->plugin()->reviews()->insert( $r );

		$this->login_with( Capabilities::MANAGE_SOURCES );
		$page = new SourcesPage( $this->plugin() );
		$this->post( 'delete_profile', array( 'id' => $ok['id'] ) );
		$this->assertStringContainsString( 'confirm_required', $this->run_handler( array( $page, 'handle_delete' ) ) );
		$this->post( 'delete_profile', array( 'id' => $ok['id'], 'confirm' => '1' ) );
		$this->assertStringContainsString( 'profile_deleted', $this->run_handler( array( $page, 'handle_delete' ) ) );
		$this->assertNull( $profiles->find( (int) $ok['id'] ) );
		$this->assertSame( 1, $this->rows( 'reviews' ) );

		$this->login_with( Capabilities::MODERATE );
		$this->post( 'save_profile', array( 'profile' => array( 'platform' => 'google', 'label' => 'X', 'profile_url' => 'https://g.example' ) ) );
		$this->assertStringNotContainsString( 'redirect:', $this->run_handler( array( $page, 'handle_save' ) ) );
		$this->assertSame( 0, $profiles->count() );

		$this->login_with( Capabilities::MANAGE_SOURCES );
		ob_start();
		$page->render();
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'API connection in Review Captain Pro', $html );
	}

	public function test_pro_hints_switch_hides_dashboard_and_sources_info(): void {
		update_option( Settings::OPTION, array( 'general' => array( 'show_pro_hints' => false ) ) );
		$this->login_with( Capabilities::MODERATE, Capabilities::MANAGE_SOURCES );
		ob_start();
		( new DashboardPage( $this->plugin() ) )->render();
		$html = (string) ob_get_clean();
		$this->assertStringNotContainsString( 'In Review Captain Pro', $html );
		ob_start();
		( new SourcesPage( $this->plugin() ) )->render();
		$html = (string) ob_get_clean();
		$this->assertStringNotContainsString( 'Review Captain Pro', $html );

		// The filter wins over the setting (in both directions).
		add_filter( 'review_captain_show_pro_info', '__return_true' );
		ob_start();
		( new DashboardPage( $this->plugin() ) )->render();
		$this->assertStringContainsString( 'In Review Captain Pro', (string) ob_get_clean() );

		update_option( Settings::OPTION, array( 'general' => array( 'show_pro_hints' => true ) ) );
		\RC_Test_State::$hooks['review_captain_show_pro_info'] = array();
		add_filter( 'review_captain_show_pro_info', '__return_false' );
		ob_start();
		$this->plugin()->settings()->flush();
		( new DashboardPage( $this->plugin() ) )->render();
		$this->assertStringNotContainsString( 'In Review Captain Pro', (string) ob_get_clean() );
	}

	public function test_widget_shortcode_builder(): void {
		$this->assertSame( '[review_captain v="1"]', WidgetsPage::build_shortcode( array() ) );
		$this->assertSame(
			'[review_captain v="1" layout="list" platform="google,trustpilot" kind="all" min_rating="4" limit="50" context_type="product" context_id="123" show_date="0" profile="3,7"]',
			WidgetsPage::build_shortcode(
				array(
					'layout'       => 'list',
					'platform'     => 'google, trustpilot, evil',
					'kind'         => 'all',
					'min_rating'   => '4',
					'limit'        => '500',
					'context_type' => 'product',
					'context_id'   => '12"3',
					'show_source'  => '1',
					'show_date'    => '0',
					'profile'      => '3,x,7',
				)
			)
		);
		$this->assertSame( '[review_captain v="1"]', WidgetsPage::build_shortcode( array( 'layout' => 'carousel', 'min_rating' => '9', 'context_id' => 'x' ) ) );

		$this->login_with( Capabilities::MODERATE );
		$_GET = array( 'page' => WidgetsPage::SLUG, 'rc_gen' => '1', 'layout' => 'list', 'show_source' => '1' );
		ob_start();
		( new WidgetsPage( $this->plugin() ) )->render();
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( '[review_captain v=&quot;1&quot; layout=&quot;list&quot;]', $html );
	}

	public function test_diagnostics_report_has_no_secrets_and_renders_sections(): void {
		update_option( Settings::OPTION, array( 'general' => array( 'brand_name' => 'Max Mustermann e.U.' ) ) );
		$r              = new Review();
		$r->body        = 'Privater Reviewtext';
		$r->author_name = 'Erika';
		$r->author_email = 'erika@example.test';
		$this->plugin()->reviews()->insert( $r );
		$this->plugin()->audit()->record( 'settings.changed', 'settings', 'general', array( 'fields' => array( 'general.brand_name' ) ) );

		add_filter(
			'review_captain_diagnostics_sections',
			static fn( $s ) => $s + array(
				'demo' => array(
					'title'  => 'Demo-Connector',
					'render' => static function () {
						echo '<p>demo-ok</p>';
					},
					'export' => static fn() => array( 'status' => 'ok', 'api_token' => 'tok_123', 'contact' => 'a@b.example' ),
				),
				'broken' => array( 'title' => 'Kaputt' ),
			)
		);
		$page   = new DiagnosticsPage( $this->plugin() );
		$report = $page->build_report();
		$json   = (string) wp_json_encode( $report );
		$this->assertSame( 'review_captain.diagnostics.v1', $report['schema'] );
		$this->assertSame( 1, $report['counts']['reviews_pending'] );
		$this->assertSame( 'ok', $report['sections']['demo']['status'] );
		foreach ( array( 'tok_123', 'a@b.example', 'erika@example.test', 'Privater Reviewtext', 'Erika', 'Max Mustermann' ) as $needle ) {
			$this->assertStringNotContainsString( $needle, $json, $needle );
		}

		$this->login_with( Capabilities::VIEW_REPORTS );
		ob_start();
		$page->render();
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'demo-ok', $html );
		$this->assertStringNotContainsString( 'Kaputt', $html );
		$this->assertStringContainsString( 'settings.changed', $html );
		$this->assertStringContainsString( 'review_captain_diagnostics_export', $html );

		$this->login_with( Capabilities::MODERATE );
		$this->post( 'diagnostics_export', array() );
		$this->assertStringNotContainsString( 'redirect:', $this->run_handler( array( $page, 'handle_export' ) ) );
	}
}
