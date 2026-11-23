<?php
/**
 * RC-106 Szenario 7: Suite-Plugin fehlt oder ist deaktiviert → sauberer Status, kein Fatal Error; wartende
 * Anfragen der Quelle werden pausiert (rc.source.unavailable), nicht verworfen (Core: docs/connectors/
 * developer-guide.md „Quell-Plugin deaktiviert/fehlt“).
 * Phasen (E2E_PHASE): prepare (Suite aktiv: je eine offene Pass- und Voucher-Anfrage anlegen),
 * check (nach Deaktivieren/Entfernen durch suite-e2e.sh), resume (nach Reaktivierung).
 * Aufruf: E2E_PHASE=… wp eval-file tests/e2e/php/suite/missing.php --user=admin
 */
use ReviewCaptainPro\Core\CoreBridge;
use ReviewCaptainPro\Suite\Integrations;
use ReviewCaptainPro\Suite\StatusPage;

require __DIR__ . '/lib.php';

global $wpdb;
$s     = 'S7-missing';
$phase = (string) getenv( 'E2E_PHASE' );
$label = (string) getenv( 'E2E_LABEL' );
$p     = $wpdb->prefix;

if ( 'prepare' === $phase ) {
	e2e_prepare_requests();
	// Pass: one more attendee checked in, request planned but not sent (no job run here).
	$now = gmdate( 'Y-m-d H:i:s' );
	$wpdb->insert( "{$p}wpcap_events", array( 'name' => 'E2E Lesung', 'slug' => 'e2e-' . uniqid(), 'description' => '', 'status' => 'published', 'created_at' => $now, 'updated_at' => $now ) );
	$wpdb->insert( "{$p}wpcap_occurrences", array( 'event_id' => (int) $wpdb->insert_id, 'starts_at' => gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS ), 'timezone' => 'Europe/Vienna', 'location' => 'Graz', 'capacity' => 0, 'status' => 'scheduled' ) );
	$wpdb->insert( "{$p}wpcap_ticket_types", array( 'occurrence_id' => (int) $wpdb->insert_id, 'name' => 'Standard', 'price_minor' => 1500, 'currency' => 'EUR', 'capacity' => 10, 'max_per_order' => 5, 'status' => 'active' ) );
	$type = (int) $wpdb->insert_id;
	$o    = PassCaptain\Orders::create( array( 'email' => 'dora.missing@example.org', 'items' => array( array( 'ticket_type_id' => $type, 'quantity' => 1 ) ), 'attendees' => array( array( 'ticket_type_id' => $type, 'name' => 'Dora', 'email' => 'dora.missing@example.org' ) ), 'idempotency_key' => 'e2e-' . uniqid() ) );
	PassCaptain\Orders::confirm_manual_payment( $o['order_id'] );
	$t = PassCaptain\Orders::tickets_for_order( $o['order_id'] )[0];
	PassCaptain\Checkin::perform_ticket( (int) $t->ticket_id, array( 'source' => 'admin', 'idempotency_key' => 'e2e-' . uniqid() ) );
	PassCaptain\Workflow\Outbox::deliver();
	$pr = array_values( array_filter( e2e_requests( "source = 'pass-captain' AND state = 'planned'" ), static fn( $r ) => false !== strpos( $r['experience_id'], ':' . $t->public_id . ':' ) ) );
	// Voucher: one more redemption.
	$vp = (int) ( get_posts( array( 'post_type' => 'product', 'title' => 'E2E Massage-Gutschein', 'fields' => 'ids', 'numberposts' => 1 ) )[0] ?? 0 );
	$vo = wc_create_order();
	$vo->set_billing_email( 'mia.missing@example.org' );
	$vo->add_product( wc_get_product( $vp ), 1 );
	$vo->set_payment_method( 'bacs' );
	$vo->calculate_totals();
	$vo->save();
	$vo->payment_complete();
	$vo->update_status( 'completed', 'E2E' );
	$v = vcap()->vouchers->for_order( $vo->get_id() )[0];
	vcap()->tickets->redeem_single( $v, 'E2E counter missing' );
	$vr = e2e_requests( "source = 'voucher-captain' AND state = 'planned'" );
	// WooCommerce: one more consented order.
	$wo = wc_create_order();
	$wo->set_billing_email( 'wim.missing@example.org' );
	$wo->add_product( wc_get_product( (int) e2e_state()['woo_product'] ), 1 );
	$wo->update_meta_data( '_e2e_review_optin', 'yes' );
	$wo->calculate_totals();
	$wo->save();
	$wo->update_status( 'completed', 'E2E' );
	$wr = e2e_requests( 'experience_id = %s', array( \ReviewCaptainPro\Woo\WooAdapter::experience_id( (int) $wo->get_id() ) ) );
	e2e_check( $s, 1 === count( $pr ) && 1 === count( $vr ) && 1 === count( $wr ), 'Vorbereitung: je eine offene Pass-, Voucher- und Woo-Anfrage (' . count( $pr ) . '/' . count( $vr ) . '/' . count( $wr ) . ')' );
	e2e_state( array( 'missing_pass' => (int) ( $pr[0]['id'] ?? 0 ), 'missing_voucher' => (int) ( end( $vr )['id'] ?? 0 ), 'missing_woo' => (int) ( $wr[0]['id'] ?? 0 ) ) );
	return;
}

$state = e2e_state();
if ( 'check' === $phase ) {
	$active = array_values( array_filter( array( 'VCAP_VERSION', 'WPCAP_VERSION', 'FORM_CAPTAIN_VERSION', 'WPAC_VERSION' ), 'defined' ) );
	e2e_info( $s, "[{$label}] WooCommerce " . ( class_exists( 'WooCommerce' ) ? 'aktiv' : 'inaktiv' ) );
	e2e_check( $s, ! $active, "[{$label}] Suite-Plugins nicht geladen (" . implode( ',', $active ) . ')' );
	e2e_check( $s, function_exists( '\ReviewCaptainPro\addon' ) && \ReviewCaptainPro\addon()->is_entitled( 'review.suite_integrations' ), "[{$label}] Review Captain Pro geladen und lizenziert" );
	$int  = new Integrations( CoreBridge::instance(), static fn() => true );
	$page = new StatusPage( $int );
	$rows = array();
	foreach ( $page->rows() as $r ) {
		$rows[ $r['key'] ] = $r['contract'];
	}
	e2e_check( $s, 'not_installed' === ( $rows['voucher'] ?? '' ) && 'not_installed' === ( $rows['pass'] ?? '' ) && 'not_installed' === ( $rows['form'] ?? '' ) && 'not_installed' === ( $rows['affiliate'] ?? '' ), "[{$label}] Statusseite: " . wp_json_encode( $rows ) );
	ob_start();
	$page->render();
	$html = (string) ob_get_clean();
	e2e_check( $s, strlen( $html ) > 500 && false === stripos( $html, 'fatal' ), "[{$label}] Statusseite rendert (" . strlen( $html ) . ' Bytes)' );
	$front = wp_remote_get( home_url( '/' ), array( 'timeout' => 20 ) );
	e2e_check( $s, 200 === wp_remote_retrieve_response_code( $front ), "[{$label}] Startseite HTTP " . wp_remote_retrieve_response_code( $front ) );
	e2e_run_jobs();
	$sources = array( 'missing_pass' => 'Pass', 'missing_voucher' => 'Voucher' );
	if ( ! class_exists( 'WooCommerce' ) ) {
		$sources['missing_woo'] = 'WooCommerce';
	}
	foreach ( $sources as $k => $name ) {
		$row = e2e_requests( 'id = %d', array( (int) ( $state[ $k ] ?? 0 ) ) )[0] ?? array();
		$job = array_values( array_filter( e2e_jobs( 'review_captain_pro.request.send' ), static fn( $j ) => false !== strpos( (string) $j['payload'], '"request_id":' . (int) ( $state[ $k ] ?? 0 ) . '}' ) ) )[0] ?? array();
		e2e_check( $s, 'eligible' === ( $row['state'] ?? '' ) && 'paused' === ( $job['status'] ?? '' ) && 'rc.source.unavailable' === ( $job['last_error_code'] ?? '' ), "[{$label}] {$name}-Anfrage wartet (Anfrage " . ( $row['state'] ?? '-' ) . ' ' . (string) ( $row['reason_code'] ?? '' ) . ', Job ' . ( $job['status'] ?? '-' ) . ' ' . (string) ( $job['last_error_code'] ?? '' ) . ')' );
	}
	return;
}

if ( 'resume' === $phase ) {
	$ids = array_filter( array( (int) ( $state['missing_pass'] ?? 0 ), (int) ( $state['missing_voucher'] ?? 0 ), (int) ( $state['missing_woo'] ?? 0 ) ) );
	foreach ( e2e_jobs( 'review_captain_pro.request.send' ) as $j ) {
		if ( 'paused' === $j['status'] ) {
			// Stündliche Wiederaufnahme des Core vorziehen.
			$wpdb->query( $wpdb->prepare( "UPDATE %i SET status = 'queued', available_at = %s WHERE id = %d", "{$p}review_captain_jobs", gmdate( 'Y-m-d H:i:s', time() - 1 ), (int) $j['id'] ) );
		}
	}
	e2e_run_jobs();
	foreach ( $ids as $id ) {
		$row = e2e_requests( 'id = %d', array( $id ) )[0] ?? array();
		e2e_check( $s, 'sent' === ( $row['state'] ?? '' ), "Nach Reaktivierung: Anfrage #{$id} versendet (" . ( $row['state'] ?? '-' ) . ' ' . (string) ( $row['reason_code'] ?? '' ) . ')' );
	}
}
