<?php
/**
 * RC-106 Szenario 3: WooCommerce-Bestellung abgeschlossen → Anfrage; Erstattung storniert die offene Anfrage.
 * Außerdem: Statuswechsel hin und her (S8), Versand einer Einladung (Grundlage für S6 Opt-out).
 * Aufruf: wp eval-file tests/e2e/php/suite/woo.php --user=admin
 */
use ReviewCaptainPro\Woo\WooAdapter;

require __DIR__ . '/lib.php';

$s = 'S3-woo';
e2e_prepare_requests();
WooAdapter::save_settings( array( 'trigger_status' => 'completed', 'legal_basis' => 'consent_meta', 'consent_meta_key' => '_e2e_review_optin' ) );
$cid = e2e_campaign( 'woocommerce', false );
e2e_check( $s, $cid > 0, "Kampagne woocommerce aktiv (#{$cid}, nur mit Einwilligung)" );
$product = e2e_product( 'E2E Kaffeemühle', '89' );

$make = static function ( string $email, bool $consent = true ) use ( $product ) {
	$order = wc_create_order();
	$order->set_billing_first_name( 'Test' );
	$order->set_billing_email( $email );
	$order->add_product( wc_get_product( $product ), 1 );
	$order->set_payment_method( 'bacs' );
	if ( $consent ) {
		$order->update_meta_data( '_e2e_review_optin', 'yes' );
	}
	$order->calculate_totals();
	$order->save();
	return $order;
};
$woo = static fn( $order ) => e2e_requests( 'experience_id = %s', array( WooAdapter::experience_id( (int) $order->get_id() ) ) );

// A: completed → planned; back and forth → still one; refund → cancelled.
$a = $make( 'wanda.woo@example.org' );
$a->update_status( 'processing', 'E2E' );
e2e_check( $s, 0 === count( $woo( $a ) ), 'Bestellung in Bearbeitung → noch keine Anfrage' );
$a->update_status( 'completed', 'E2E' );
$rows = $woo( $a );
e2e_check( $s, 1 === count( $rows ) && 'planned' === $rows[0]['state'], 'Bestellung #' . $a->get_id() . ' abgeschlossen → Anfrage geplant (' . count( $rows ) . ')' );
$a->update_status( 'processing', 'E2E' );
$a->update_status( 'completed', 'E2E again' );
e2e_check( $s, 1 === count( $woo( $a ) ), 'S8 Statuswechsel hin und her → keine zweite Anfrage' );
$refund = wc_create_refund( array( 'order_id' => $a->get_id(), 'amount' => $a->get_total(), 'reason' => 'E2E' ) );
e2e_info( $s, 'wc_create_refund: ' . ( is_wp_error( $refund ) ? $refund->get_error_code() : 'ok, Status ' . wc_get_order( $a->get_id() )->get_status() ) );
$rows = $woo( $a );
e2e_check( $s, 1 === count( $rows ) && 'cancelled' === $rows[0]['state'], 'Vollständige Erstattung → Anfrage storniert (' . $rows[0]['state'] . ' ' . (string) $rows[0]['reason_code'] . ')' );
wc_get_order( $a->get_id() )->update_status( 'completed', 'E2E nach Erstattung' );
e2e_check( $s, 1 === count( $woo( $a ) ) && 'cancelled' === $woo( $a )[0]['state'], 'Nach Erstattung erneut abgeschlossen → keine neue Anfrage (Erlebnis gesperrt)' );
e2e_run_jobs();
e2e_check( $s, 0 === count( e2e_invitations( 'wanda.woo@example.org' ) ), 'Erstattete Bestellung: keine Einladung' );

// Without consent: nothing is planned.
$n = $make( 'nora.ohne@example.org', false );
$n->update_status( 'completed', 'E2E' );
$nr = $woo( $n );
e2e_check( $s, ! $nr || 'skipped' === $nr[0]['state'], 'Ohne Einwilligung → keine geplante Anfrage (' . ( $nr ? $nr[0]['state'] . ' ' . $nr[0]['reason_code'] : 'keine Zeile' ) . ')' );

// B: completed → sent (invitation with unsubscribe link, used by optout.php).
$b = $make( 'olaf.optout@example.org' );
$b->update_status( 'completed', 'E2E' );
e2e_run_jobs();
$rows = $woo( $b );
$mail = e2e_invitations( 'olaf.optout@example.org' );
e2e_check( $s, 'sent' === ( $rows[0]['state'] ?? '' ) && 1 === count( $mail ), 'Bestellung #' . $b->get_id() . ' → Einladung versendet (' . ( $rows[0]['state'] ?? '-' ) . ', Mails ' . count( $mail ) . ')' );
$url = '';
if ( $mail ) {
	$hay = $mail[0]['message'] . ' ' . wp_json_encode( $mail[0]['headers'], JSON_UNESCAPED_SLASHES );
	if ( preg_match( '#https?://[^\s"<>]+review_captain_pro_unsubscribe[^\s"<>]*#', $hay, $m ) ) {
		$url = html_entity_decode( $m[0] );
	}
	e2e_check( $s, '' !== $url && false !== strpos( wp_json_encode( $mail[0]['headers'] ), 'List-Unsubscribe' ), 'Einladung enthält Abmeldelink und List-Unsubscribe-Header' );
	e2e_check( $s, false !== strpos( $mail[0]['message'], home_url( '/bewerten/' ) ), 'Einladung enthält den Bewertungslink der Kampagne' );
}
e2e_state( array( 'woo_unsubscribe' => $url, 'woo_optout_request' => (int) ( $rows[0]['id'] ?? 0 ), 'woo_product' => $product ) );

// Interplay with Voucher Captain: buying a service voucher is an ordinary WooCommerce order.
$vp = (int) ( get_posts( array( 'post_type' => 'product', 'title' => 'E2E Massage-Gutschein', 'fields' => 'ids', 'numberposts' => 1 ) )[0] ?? 0 );
if ( $vp ) {
	$vo = wc_create_order();
	$vo->set_billing_email( 'gina.gutschein@example.org' );
	$vo->add_product( wc_get_product( $vp ), 1 );
	$vo->update_meta_data( '_e2e_review_optin', 'yes' );
	$vo->set_payment_method( 'bacs' );
	$vo->calculate_totals();
	$vo->save();
	$vo->payment_complete();
	$vo->update_status( 'completed', 'E2E' );
	$vr = $woo( $vo );
	e2e_info( $s, 'Kauf eines Leistungsgutscheins mit aktiver Woo-Kampagne: ' . count( $vr ) . ' Woo-Anfrage(n) ' . ( $vr ? $vr[0]['state'] : '' ) . ' – die spätere Einlösung plant eine zweite (Voucher); die 90-Tage-Sperre (cooldown) verhindert dann die zweite Mail' );
}
