<?php
/**
 * RC-106 Szenario 1: Kauf eines Leistungsgutscheins (WooCommerce) → Einlösung am Schalter (Voucher Captain)
 * → geplante und versendete Bewertungsanfrage (Review Captain Pro). Dazu: doppeltes Ereignis, Widerruf.
 * Aufruf: wp eval-file tests/e2e/php/suite/voucher.php
 */
require __DIR__ . '/lib.php';

$s = 'S1-voucher';
e2e_prepare_requests();
$cid = e2e_campaign( 'suite:voucher' );
e2e_check( $s, $cid > 0, "Kampagne suite:voucher aktiv (#{$cid})" );
e2e_check( $s, true === (bool) apply_filters( 'vcap_review_source_active', false ), 'Voucher Captain sieht eine Review-Quelle (vcap_review_source_active)' );

$email   = 'vera.voucher@example.org';
$product = e2e_product(
	'E2E Massage-Gutschein',
	'60',
	array(
		'_vcap_enabled'        => 'yes',
		'_vcap_type'           => 'voucher',
		'_vcap_usage'          => 'single',
		'_vcap_amount_source'  => 'price',
		'_vcap_review_service' => 'yes',
	)
);
$order = wc_create_order();
$order->set_billing_first_name( 'Vera' );
$order->set_billing_last_name( 'Voucher' );
$order->set_billing_email( $email );
$order->add_product( wc_get_product( $product ), 1 );
$order->set_payment_method( 'bacs' );
$order->calculate_totals();
$order->save();
$order->payment_complete();
$order->update_status( 'completed', 'E2E' );
$vouchers = vcap()->vouchers->for_order( $order->get_id() );
e2e_check( $s, 1 === count( $vouchers ), 'Kauf: Bestellung #' . $order->get_id() . ' abgeschlossen, Gutschein ausgestellt (' . count( $vouchers ) . ')' );
if ( ! $vouchers ) {
	return;
}
$voucher = $vouchers[0];
e2e_info( $s, 'Gutschein #' . $voucher->id . ' Typ ' . $voucher->type . ' Status ' . $voucher->status . ' Empfänger ' . ( $voucher->recipient_email ?: '-' ) . ' Käufer ' . ( $voucher->buyer_email ? 'gesetzt' : '-' ) );

$before_voucher = count( e2e_requests( "source = 'voucher-captain'" ) );
$woo_req        = count( e2e_requests( 'experience_id = %s', array( 'woo:order:' . $order->get_id() ) ) );
e2e_info( $s, "Anfragen nach dem Kauf: voucher={$before_voucher}, woo:order:{$order->get_id()}={$woo_req} (Kauf allein plant keine Voucher-Anfrage)" );

// Redemption at the counter ("Mark as redeemed (store)" path).
$ok = vcap()->tickets->redeem_single( $voucher, 'E2E counter' );
e2e_check( $s, true === $ok, 'Einlösung am Schalter (redeem_single, Kanal counter)' );
$rows = e2e_requests( "source = 'voucher-captain'" );
e2e_check( $s, count( $rows ) === $before_voucher + 1 && 'planned' === ( end( $rows )['state'] ?? '' ), 'Einlösung → genau eine geplante Anfrage (' . count( $rows ) . ', ' . ( end( $rows )['state'] ?? '-' ) . ')' );
$row = end( $rows );
e2e_check( $s, false === strpos( (string) wp_json_encode( $rows ), $email ), 'Anfragezeile ohne E-Mail im Klartext (' . ( $row['recipient_ref'] ?? '' ) . ')' );

// Duplicate event: same redemption payload again (replay of vcap_review_redemption_eligible).
$ref = substr( (string) $row['recipient_ref'], strlen( 'voucher:' ) );
$svc = apply_filters( 'vcap_review_source_v1', null );
$pl  = $svc ? $svc->get( $ref ) : null;
e2e_check( $s, is_array( $pl ) && 'eligible' === $pl['event'], 'Voucher Captain liefert den Zustand der Referenz über get()' );
do_action( 'vcap_review_redemption_eligible', $pl );
do_action( 'vcap_review_redemption_eligible', $pl );
e2e_check( $s, count( e2e_requests( "source = 'voucher-captain'" ) ) === count( $rows ), 'S8 doppeltes Ereignis (2× erneut gefeuert) → keine weitere Anfrage' );

// Send job.
e2e_run_jobs();
$row  = e2e_requests( 'id = %d', array( (int) $row['id'] ) )[0];
$mail = e2e_invitations( $email );
e2e_check( $s, 'sent' === $row['state'] && 1 === count( $mail ), 'Versandjob: Kontakt über vcap recipient() aufgelöst, Einladung an die Käuferin (state ' . $row['state'] . ', ' . (string) $row['reason_code'] . ', Mails ' . count( $mail ) . ')' );
if ( $mail ) {
	e2e_info( $s, 'Betreff: ' . $mail[0]['subject'] );
	preg_match( '#https?://[^\s"<>]+review_captain_pro_unsubscribe[^\s"<>]*#', $mail[0]['message'] . ' ' . wp_json_encode( $mail[0]['headers'] ), $m );
	e2e_state( array( 'voucher_unsubscribe' => isset( $m[0] ) ? html_entity_decode( str_replace( '\/', '/', $m[0] ) ) : '', 'voucher_email' => $email, 'voucher_request' => (int) $row['id'] ) );
}
global $wpdb;
$audit = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE event = 'suite.recipient_resolved' AND object_type = 'voucher_redemption'", $wpdb->prefix . 'review_captain_audit_log' ) );
e2e_check( $s, $audit >= 1, "Audit suite.recipient_resolved für den Abruf ({$audit})" );

// Second redemption of the same voucher is impossible (single use); a second voucher is redeemed and reversed.
$order2 = wc_create_order();
$order2->set_billing_email( 'vince.voucher@example.org' );
$order2->add_product( wc_get_product( $product ), 1 );
$order2->set_payment_method( 'bacs' );
$order2->calculate_totals();
$order2->save();
$order2->payment_complete();
$order2->update_status( 'completed', 'E2E' );
$v2 = vcap()->vouchers->for_order( $order2->get_id() )[0] ?? null;
if ( $v2 ) {
	vcap()->tickets->redeem_single( $v2, 'E2E counter 2' );
	$r2 = e2e_requests( "source = 'voucher-captain' AND id > %d", array( (int) $row['id'] ) );
	$history = vcap()->vouchers->events_of( (int) $v2->id, 'redeemed', 1 );
	$event   = (int) ( $history[0]['id'] ?? 0 );
	$undo    = vcap()->tickets->reverse_redemption( vcap()->vouchers->get( (int) $v2->id ), $event, 'E2E Storno' );
	e2e_info( $s, 'reverse_redemption: ' . ( is_wp_error( $undo ) ? $undo->get_error_code() : wp_json_encode( $undo ) ) );
	$r2b = e2e_requests( "source = 'voucher-captain' AND id > %d", array( (int) $row['id'] ) );
	e2e_check( $s, 1 === count( $r2 ) && 'cancelled' === ( $r2b[0]['state'] ?? '' ), 'Einlösung storniert (Tickets::reverse_redemption) → Anfrage storniert (' . ( $r2b[0]['state'] ?? '-' ) . ' ' . ( $r2b[0]['reason_code'] ?? '' ) . ')' );
}
echo wp_json_encode( e2e_suite_counter( 'voucher' ) ), "\n";
