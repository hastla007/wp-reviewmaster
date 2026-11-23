<?php
/**
 * RC-106 Szenario 6: Opt-out über den Abmeldelink (echter HTTP-Aufruf gegen die laufende Instanz, GET + POST)
 * → keine weitere Einladung, auch nicht über eine andere Quelle (Voucher Captain).
 * Voraussetzung: woo.php (Einladung an olaf.optout@example.org) und voucher.php (Gutscheinprodukt) liefen vorher,
 * Webserver läuft (server_start).
 * Aufruf: wp eval-file tests/e2e/php/suite/optout.php --user=admin
 */
use ReviewCaptainPro\Requests\Contact;
use ReviewCaptainPro\Requests\RequestStore;
use ReviewCaptainPro\Woo\WooAdapter;

require __DIR__ . '/lib.php';

$s     = 'S6-optout';
$email = 'olaf.optout@example.org';
$state = e2e_state();
$url   = (string) ( $state['woo_unsubscribe'] ?? '' );
if ( ! e2e_check( $s, '' !== $url, 'Abmeldelink aus der Einladung vorhanden' ) ) {
	return;
}
$get = wp_remote_get( $url, array( 'timeout' => 20 ) );
e2e_check( $s, 200 === wp_remote_retrieve_response_code( $get ) && false !== strpos( wp_remote_retrieve_body( $get ), '<form method="post"' ), 'GET zeigt nur die Bestätigung (kein Abmelden durch Linkvorschau), HTTP ' . wp_remote_retrieve_response_code( $get ) );
$req = e2e_requests( 'id = %d', array( (int) $state['woo_optout_request'] ) )[0];
e2e_check( $s, 'sent' === $req['state'], 'Nach GET noch nicht abgemeldet (' . $req['state'] . ')' );
$post = wp_remote_post( $url, array( 'timeout' => 20 ) );
e2e_check( $s, 200 === wp_remote_retrieve_response_code( $post ) && false !== stripos( wp_remote_retrieve_body( $post ), 'unsubscribed' ), 'POST (Ein-Klick nach RFC 8058) meldet ab, HTTP ' . wp_remote_retrieve_response_code( $post ) );
$req   = e2e_requests( 'id = %d', array( (int) $state['woo_optout_request'] ) )[0];
$store = new RequestStore();
e2e_check( $s, 'unsubscribed' === $req['state'] && $store->is_suppressed( Contact::hash( $email ) ), 'Anfrage unsubscribed, Kontakt-Hash auf der Sperrliste' );
$bad = wp_remote_post( add_query_arg( 't', str_repeat( 'a', 64 ), $url ), array( 'timeout' => 20 ) );
e2e_check( $s, 400 === wp_remote_retrieve_response_code( $bad ), 'Manipulierter Token → 400' );

// New WooCommerce order of the same person.
$mails_before = count( e2e_invitations( $email ) );
$order        = wc_create_order();
$order->set_billing_email( $email );
$order->add_product( wc_get_product( (int) $state['woo_product'] ), 1 );
$order->update_meta_data( '_e2e_review_optin', 'yes' );
$order->calculate_totals();
$order->save();
$order->update_status( 'completed', 'E2E' );
e2e_run_jobs();
$rows = e2e_requests( 'experience_id = %s', array( WooAdapter::experience_id( (int) $order->get_id() ) ) );
e2e_check( $s, $rows && 'skipped' === $rows[0]['state'] && 'rc.recipient.suppressed' === $rows[0]['reason_code'], 'Neue Bestellung derselben Person → Versand übersprungen (' . ( $rows ? $rows[0]['state'] . ' ' . $rows[0]['reason_code'] : 'keine Zeile' ) . ')' );
e2e_check( $s, count( e2e_invitations( $email ) ) === $mails_before, 'Keine weitere Einladung an die abgemeldete Adresse' );

// Same person redeems a service voucher (other source).
$vp = (int) ( get_posts( array( 'post_type' => 'product', 'title' => 'E2E Massage-Gutschein', 'fields' => 'ids', 'numberposts' => 1 ) )[0] ?? 0 );
if ( $vp ) {
	$vo = wc_create_order();
	$vo->set_billing_email( $email );
	$vo->add_product( wc_get_product( $vp ), 1 );
	$vo->set_payment_method( 'bacs' );
	$vo->calculate_totals();
	$vo->save();
	$vo->payment_complete();
	$vo->update_status( 'completed', 'E2E' );
	$v = vcap()->vouchers->for_order( $vo->get_id() )[0] ?? null;
	if ( $v ) {
		vcap()->tickets->redeem_single( $v, 'E2E counter optout' );
		$vr  = e2e_requests( "source = 'voucher-captain'" );
		$ref = substr( (string) ( end( $vr )['recipient_ref'] ?? '' ), strlen( 'voucher:' ) );
		$svc = apply_filters( 'vcap_review_source_v1', null );
		$ans = $svc ? $svc->recipient( $ref ) : null;
		e2e_check( $s, is_wp_error( $ans ) && 'vcap_review_opted_out' === $ans->get_error_code(), 'Voucher Captain gibt die abgemeldete Adresse nicht heraus (vcap_review_opted_out): ' . ( is_wp_error( $ans ) ? $ans->get_error_code() : ( is_array( $ans ) ? 'Kontakt geliefert' : '-' ) ) );
		e2e_run_jobs();
		$vr = e2e_requests( 'recipient_ref = %s', array( 'voucher:' . $ref ) );
		e2e_check( $s, $vr && in_array( $vr[0]['state'], array( 'skipped', 'cancelled' ), true ), 'Gutschein-Einlösung derselben Person → keine Einladung (' . ( $vr ? $vr[0]['state'] . ' ' . $vr[0]['reason_code'] : '-' ) . ')' );
		e2e_check( $s, count( e2e_invitations( $email ) ) === $mails_before, 'Weiterhin keine weitere Einladung' );
	}
}
