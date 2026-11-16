<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Connectors;

use Acme\RcTest\ConfigurableConnector;
use Acme\RcTest\ExperienceAndReviewConnector;
use Acme\RcTest\ExperienceConnector;
use ReviewCaptain\Connectors\ConnectorException;
use ReviewCaptain\Connectors\ConnectorsModule;
use ReviewCaptain\Connectors\Registry;
use ReviewCaptain\Plugin;
use ReviewCaptain\Support\ErrorCode;
use ReviewCaptain\Tests\TestCase;

require_once dirname( __DIR__, 2 ) . '/fixtures/connectors/TestConnectors.php';

final class RegistryTest extends TestCase {

	private function registry(): Registry {
		return new Registry();
	}

	/** @param array<string, mixed> $meta */
	private function connector( array $meta = array() ): ExperienceConnector {
		return new ExperienceConnector( $meta );
	}

	public function test_ct_r_01_valid_connector_via_hook_is_registered_and_inactive(): void {
		$c = $this->connector();
		add_action( 'review_captain_connectors_v1', static function ( Registry $r ) use ( $c ) { $r->register( $c ); } );
		$registry = $this->registry();
		$registry->fire();
		$this->assertSame( $c, $registry->get( 'acme-booking' ) );
		$status = $registry->status( 'acme-booking' );
		$this->assertSame( 'inactive', $status['state'] );
		$this->assertNull( $status['error_code'] );
		$this->assertNotSame( '', $status['next_action'] );
		$this->assertFalse( $registry->is_executable( 'acme-booking' ) );
		$this->assertSame( 0, $c->calls, 'registration must not call capability methods' );
	}

	public function test_ct_r_02_non_connector_is_rejected_without_fatal(): void {
		$registry = $this->registry();
		$this->assertFalse( $registry->register( new \stdClass() ) );
		$this->assertFalse( $registry->register( 'acme' ) );
		$this->assertFalse( $registry->register( null ) );
		$this->assertSame( array(), $registry->keys() );
		$this->assertCount( 3, $registry->rejected() );
		$this->assertSame( ErrorCode::CONTRACT_INCOMPATIBLE, $registry->rejected()[0]['error_code'] );
	}

	public function test_ct_r_03_invalid_keys_are_rejected(): void {
		$registry = $this->registry();
		foreach ( array( 'ab', 'Ab-c', '-abc', str_repeat( 'a', 65 ), 'ab c', '' ) as $key ) {
			$this->assertFalse( $registry->register( $this->connector( array( 'key' => $key ) ) ), $key );
		}
		$this->assertSame( array(), $registry->keys() );
		foreach ( $registry->rejected() as $item ) {
			$this->assertSame( ErrorCode::DATA_INVALID_PAYLOAD, $item['error_code'] );
		}
	}

	public function test_ct_r_04_valid_keys(): void {
		$registry = $this->registry();
		foreach ( array( 'abc', 'a_b-c', str_repeat( 'a', 64 ) ) as $key ) {
			$this->assertTrue( $registry->register( $this->connector( array( 'key' => $key ) ) ), $key );
		}
		$this->assertCount( 3, $registry->keys() );
	}

	public function test_ct_r_05_duplicate_key_keeps_first(): void {
		$registry = $this->registry();
		$first    = $this->connector();
		$this->assertTrue( $registry->register( $first ) );
		$this->assertFalse( $registry->register( $this->connector( array( 'label' => 'Zweiter' ) ) ) );
		$this->assertSame( $first, $registry->get( 'acme-booking' ) );
		$this->assertSame( ErrorCode::DATA_DUPLICATE, $registry->rejected()[0]['error_code'] );
	}

	public function test_ct_r_06_reserved_keys_are_rejected(): void {
		$registry = $this->registry();
		foreach ( array( 'woocommerce', 'rc-test', 'review-captain-x', 'pass-captain' ) as $key ) {
			$this->assertFalse( $registry->register( $this->connector( array( 'key' => $key ) ) ), $key );
		}
	}

	public function test_ct_r_07_09_compatible_contract_versions(): void {
		$registry = $this->registry();
		foreach ( array( '1.0', '1.7', '1.x', '1.99' ) as $i => $v ) {
			$registry->register( $this->connector( array( 'key' => 'acme-' . $i, 'contract_version' => $v ) ) );
			$this->assertNotSame( 'paused', $registry->status( 'acme-' . $i )['state'], $v );
		}
		$this->assertNotEmpty( $registry->details( 'acme-3' )['notices'], 'newer minor gives a notice' );
	}

	public function test_ct_r_08_incompatible_contract_is_paused_not_fatal(): void {
		$registry = $this->registry();
		foreach ( array( '2.0', '0.9', '1', 'v1.0', '' ) as $i => $v ) {
			$this->assertTrue( $registry->register( $this->connector( array( 'key' => 'acme-' . $i, 'contract_version' => $v ) ) ), $v );
			$status = $registry->status( 'acme-' . $i );
			$this->assertSame( 'paused', $status['state'], $v );
			$this->assertSame( ErrorCode::CONTRACT_INCOMPATIBLE, $status['error_code'] );
			$this->assertFalse( $registry->is_executable( 'acme-' . $i ) );
		}
	}

	public function test_ct_r_10_empty_capabilities_rejected(): void {
		$registry = $this->registry();
		$this->assertFalse( $registry->register( $this->connector( array( 'capabilities' => array() ) ) ) );
	}

	public function test_ct_r_11_unknown_capability_pauses(): void {
		$registry = $this->registry();
		$registry->register( $this->connector( array( 'capabilities' => array( 'experience_source', 'foo_bar' ) ) ) );
		$this->assertSame( 'paused', $registry->status( 'acme-booking' )['state'] );
	}

	public function test_ct_r_12_capability_without_interface_pauses(): void {
		$registry = $this->registry();
		$registry->register( new ConfigurableConnector( array( 'capabilities' => array( 'experience_source' ) ) ) );
		$status = $registry->status( 'acme-booking' );
		$this->assertSame( 'paused', $status['state'] );
		$this->assertSame( ErrorCode::CONTRACT_INCOMPATIBLE, $status['error_code'] );
		$this->assertFalse( $registry->set_state( 'acme-booking', 'active' ), 'incompatible connector cannot be activated' );
	}

	public function test_ct_r_13_undeclared_interface_is_ignored(): void {
		$registry = $this->registry();
		$registry->register( new ExperienceAndReviewConnector() );
		$this->assertTrue( $registry->supports( 'acme-booking', 'experience_source' ) );
		$this->assertFalse( $registry->supports( 'acme-booking', 'review_source' ) );
	}

	public function test_ct_r_14_duplicate_capabilities_are_deduplicated(): void {
		$registry = $this->registry();
		$registry->register( $this->connector( array( 'capabilities' => array( 'experience_source', 'experience_source' ) ) ) );
		$this->assertSame( array( 'experience_source' ), $registry->details( 'acme-booking' )['meta']['capabilities'] );
		$this->assertSame( 'inactive', $registry->status( 'acme-booking' )['state'] );
	}

	public function test_ct_r_15_invalid_metadata_rejected(): void {
		$registry = $this->registry();
		$cases    = array(
			array( 'label' => '' ),
			array( 'label' => str_repeat( 'x', 192 ) ),
			array( 'developer' => ' ' ),
			array( 'version' => '' ),
			array( 'version' => str_repeat( '1', 65 ) ),
		);
		foreach ( $cases as $meta ) {
			$this->assertFalse( $registry->register( $this->connector( $meta ) ) );
		}
		$this->assertSame( array(), $registry->keys() );
	}

	public function test_ct_r_16_exception_in_metadata_is_caught(): void {
		$registry = $this->registry();
		foreach ( array( 'key', 'label', 'capabilities' ) as $method ) {
			$this->assertFalse( $registry->register( $this->connector( array( 'throw' => $method ) ) ) );
		}
		$this->assertCount( 3, $registry->rejected() );
	}

	public function test_ct_r_17_label_is_escaped_in_diagnostics(): void {
		$module = new ConnectorsModule();
		$module->register( Plugin::instance() );
		add_action( 'review_captain_connectors_v1', fn( Registry $r ) => $r->register( $this->connector( array( 'label' => '<script>alert(1)</script>' ) ) ) );
		ob_start();
		$module->render_diagnostics();
		$html = (string) ob_get_clean();
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
		$this->assertStringContainsString( 'acme-booking', $html );
	}

	public function test_ct_r_18_hook_fires_once(): void {
		$registry = $this->registry();
		$registry->fire();
		$registry->fire();
		$this->assertSame( 1, did_action( 'review_captain_connectors_v1' ) );
	}

	public function test_callback_exception_does_not_break_request(): void {
		add_action( 'review_captain_connectors_v1', static function () { throw new \RuntimeException( 'x' ); } );
		$registry = $this->registry();
		$registry->fire();
		$this->assertNotEmpty( $registry->rejected() );
	}

	public function test_activation_state_and_pause(): void {
		$registry = $this->registry();
		$registry->register( $this->connector() );
		$this->assertTrue( $registry->set_state( 'acme-booking', 'active' ) );
		$this->assertTrue( $registry->is_executable( 'acme-booking' ) );
		$this->assertTrue( $registry->set_state( 'acme-booking', 'paused', ErrorCode::AUTH_INVALID ) );
		$status = $registry->status( 'acme-booking' );
		$this->assertSame( 'paused', $status['state'] );
		$this->assertSame( ErrorCode::AUTH_INVALID, $status['error_code'] );
		$this->assertSame( ErrorCode::admin_action( ErrorCode::AUTH_INVALID ), $status['next_action'] );
		$this->assertTrue( $registry->set_state( 'acme-booking', 'inactive' ) );
		$this->assertSame( 'inactive', $registry->status( 'acme-booking' )['state'] );
	}

	public function test_con_014_known_connector_missing_is_source_unavailable(): void {
		$c = $this->connector();
		add_action( 'review_captain_connectors_v1', static function ( Registry $r ) use ( $c ) { $r->register( $c ); } );
		$first = $this->registry();
		$first->fire();
		$this->assertArrayHasKey( 'acme-booking', get_option( Registry::OPTION_KNOWN ) );
		$first->set_state( 'acme-booking', 'active' );

		// Next request: source plugin deactivated, connector no longer registers.
		remove_all_filters( 'review_captain_connectors_v1' );
		$second = $this->registry();
		$second->fire();
		$status = $second->status( 'acme-booking' );
		$this->assertSame( 'source_unavailable', $status['state'] );
		$this->assertSame( ErrorCode::SOURCE_UNAVAILABLE, $status['error_code'] );
		$this->assertFalse( $second->is_executable( 'acme-booking' ) );
		$this->assertArrayHasKey( 'acme-booking', $second->unavailable() );

		// Unknown keys are just inactive.
		$this->assertSame( 'inactive', $second->status( 'never-seen' )['state'] );

		$second->forget( 'acme-booking' );
		$this->assertSame( 'inactive', $second->status( 'acme-booking' )['state'] );
	}

	public function test_known_list_is_not_rewritten_without_changes(): void {
		add_action( 'review_captain_connectors_v1', fn( Registry $r ) => $r->register( $this->connector() ) );
		( $this->registry() )->fire();
		$before = get_option( Registry::OPTION_KNOWN );
		( $this->registry() )->fire();
		$this->assertSame( $before, get_option( Registry::OPTION_KNOWN ) );
	}

	public function test_job_type_owner(): void {
		$registry = $this->registry();
		$registry->register( $this->connector() );
		$this->assertSame( 'acme-booking', $registry->owner_of_job_type( 'acme-booking.reconcile' ) );
		$this->assertNull( $registry->owner_of_job_type( 'other-plugin.reconcile' ) );
		$this->assertNull( $registry->owner_of_job_type( 'acme-booking' ) );
	}

	public function test_module_service_hooks_and_diagnostics(): void {
		$plugin = Plugin::instance();
		$module = new ConnectorsModule();
		$module->register( $plugin );
		$this->assertInstanceOf( Registry::class, $plugin->get( 'connectors' ) );
		$this->assertSame( $module->registry(), $plugin->get( 'connectors' ) );
		$this->assertSame( 20, has_action( 'init', array( $module, 'fire' ) ) );
		$sections = apply_filters( 'review_captain_diagnostics_sections', array() );
		$this->assertArrayHasKey( 'connectors', $sections );
		$this->assertIsCallable( $sections['connectors']['render'] );
		do_action( 'init' );
		do_action( 'init' );
		$this->assertSame( 1, did_action( 'review_captain_connectors_v1' ) );
	}

	public function test_connector_exception_codes_and_sanitizing(): void {
		$e = new ConnectorException( 'rc.foo.bar', 'Fehler für max@example.com mit Token abcdefghijklmnopqrstuvwxyz0123456789' );
		$this->assertSame( ErrorCode::TEMP_UNAVAILABLE, $e->error_code() );
		$this->assertSame( 'rc.foo.bar', $e->raw_error_code() );
		$this->assertStringNotContainsString( 'max@example.com', $e->getMessage() );
		$this->assertStringNotContainsString( 'abcdefghijklmnopqrstuvwxyz0123456789', $e->getMessage() );
		$this->assertSame( ErrorCode::AUTH_INVALID, ( new ConnectorException( ErrorCode::AUTH_INVALID, str_repeat( 'x', 900 ) ) )->error_code() );
		$this->assertSame( 500, mb_strlen( ( new ConnectorException( ErrorCode::AUTH_INVALID, str_repeat( 'x y ', 300 ) ) )->getMessage() ) );
	}
}
