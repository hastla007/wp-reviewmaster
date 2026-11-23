<?php
/**
 * RC-106 Szenario 5: Affiliate Captain – nur der lesende Bericht (Anfragen je vermittelter Bestellung), keine
 * Einladungen aus Affiliate-Ereignissen.
 * Fixture: Partner über AffiliateRepository::create(), Verkäufe über die öffentliche Schreib-API wpac_record_sale()
 * mit expliziter Zuordnung (affiliate_id); die Bestellungen selbst sind echte WooCommerce-Bestellungen.
 * Aufruf: wp eval-file tests/e2e/php/suite/affiliate.php --user=admin
 */
use ReviewCaptainPro\Core\CoreBridge;
use ReviewCaptainPro\Suite\Adapters\AffiliateAdapter;
use ReviewCaptainPro\Suite\AffiliateReport;
use ReviewCaptainPro\Suite\Integrations;
use ReviewCaptainPro\Suite\StatusPage;
use ReviewCaptainPro\Woo\WooAdapter;

require __DIR__ . '/lib.php';

$s = 'S5-affiliate';
e2e_prepare_requests();
$adapter = new AffiliateAdapter();
e2e_check( $s, $adapter->is_contract_available(), 'Affiliate Captain erkannt, Vertrag ' . ( defined( 'WPAC_CONTRACT_VERSION' ) ? WPAC_CONTRACT_VERSION : '-' ) . ' (benötigt ' . $adapter->contract_version_required() . ')' );
$create = static function ( string $email, string $name ) {
	$found = \WPAffiliateCaptain\Affiliates\AffiliateRepository::find_by_email( $email );
	if ( $found ) {
		return (int) ( is_object( $found ) ? $found->id : $found['id'] );
	}
	$id = \WPAffiliateCaptain\Affiliates\AffiliateRepository::create( array( 'email' => $email, 'name' => $name, 'status' => 'active' ) );
	return is_wp_error( $id ) ? 0 : (int) $id;
};
$big   = $create( 'partner.gross@example.org', 'Partner Groß' );
$small = $create( 'partner.klein@example.org', 'Partner Klein' );
e2e_check( $s, $big > 0 && $small > 0, "Partner angelegt (#{$big}, #{$small})" );

$product  = e2e_product( 'E2E Affiliate-Produkt', '40' );
$requests = count( e2e_requests() );
$hooks    = array( 'wpac_commission_created', 'wpac_affiliate_attributed', 'wpac_commission_approved', 'wpac_commission_pending' );
$pro_listens = array();
foreach ( $hooks as $h ) {
	global $wp_filter;
	foreach ( (array) ( isset( $wp_filter[ $h ] ) ? $wp_filter[ $h ]->callbacks : array() ) as $cbs ) {
		foreach ( $cbs as $cb ) {
			$fn = $cb['function'];
			$cl = is_array( $fn ) && is_object( $fn[0] ) ? get_class( $fn[0] ) : ( is_array( $fn ) ? (string) $fn[0] : ( is_string( $fn ) ? $fn : 'closure' ) );
			if ( 0 === strpos( $cl, 'ReviewCaptain' ) ) {
				$pro_listens[] = "$h:$cl";
			}
		}
	}
}
e2e_check( $s, ! $pro_listens, 'Review Captain (Pro) hängt an keinem Affiliate-Ereignis (' . implode( ',', $pro_listens ) . ')' );

$sales = 0;
foreach ( array( $big => 6, $small => 2 ) as $aff => $n ) {
	for ( $i = 0; $i < $n; $i++ ) {
		$email = "aff{$aff}k{$i}." . wp_rand( 1000, 9999 ) . '@example.org';
		$order = wc_create_order();
		$order->set_billing_email( $email );
		$order->add_product( wc_get_product( $product ), 1 );
		$order->update_meta_data( '_e2e_review_optin', 'yes' );
		$order->set_payment_method( 'bacs' );
		$order->calculate_totals();
		$order->save();
		$before = count( e2e_requests() );
		$r      = wpac_record_sale(
			array(
				'contract'     => '1.0.0',
				'source'       => 'woocommerce',
				'order_id'     => (string) $order->get_id(),
				'currency'     => 'EUR',
				'paid_at'      => gmdate( 'Y-m-d H:i:s' ),
				'affiliate_id' => $aff,
				'customer'     => array( 'user_id' => 0, 'email' => $email ),
				'items'        => array( array( 'item_id' => '1', 'product_id' => $product, 'name' => 'E2E', 'quantity' => 1, 'net_minor' => 4000 ) ),
			)
		);
		$sales += is_wp_error( $r ) ? 0 : 1;
		if ( is_wp_error( $r ) ) {
			e2e_info( $s, 'wpac_record_sale: ' . $r->get_error_code() . ' ' . $r->get_error_message() );
		}
		if ( count( e2e_requests() ) !== $before ) {
			e2e_check( $s, false, 'Provision gebucht → es entstand eine Anfrage (darf nicht sein)' );
		}
		$order->update_status( 'completed', 'E2E' );
	}
}
e2e_check( $s, 8 === $sales, "8 vermittelte Verkäufe gebucht ({$sales})" );
e2e_check( $s, count( e2e_requests() ) === $requests + 8, 'Anfragen entstehen nur aus den abgeschlossenen Bestellungen (+' . ( count( e2e_requests() ) - $requests ) . ')' );
e2e_run_jobs();

$report = ( new AffiliateReport( $adapter ) )->build( 90 );
$rows   = array();
foreach ( (array) $report['rows'] as $row ) {
	$rows[ (int) ( $row['affiliate_id'] ?? 0 ) ] = $row;
}
e2e_info( $s, 'Bericht: ' . wp_json_encode( $report ) );
e2e_check( $s, 'ok' === $report['status'] && isset( $rows[ $big ] ) && 6 === (int) ( $rows[ $big ]['orders'] ?? 0 ) && 6 === (int) ( $rows[ $big ]['requests_sent'] ?? 0 ), 'Partner Groß: 6 Bestellungen, 6 gesendete Anfragen' );
e2e_check( $s, isset( $rows[ $small ] ) && false === $rows[ $small ]['sufficient'] && null === $rows[ $small ]['requests_sent'], 'Partner Klein (2 Bestellungen): „Zu kleine Stichprobe“ ohne Zahlen' );
e2e_check( $s, false === strpos( wp_json_encode( $report ), '@' ), 'Bericht ohne E-Mail-Adressen' );

// Status page (admin with wpac_manage_affiliates).
$int = new Integrations( CoreBridge::instance(), static fn() => \ReviewCaptainPro\addon()->is_entitled( 'review.suite_integrations' ) );
ob_start();
( new StatusPage( $int ) )->render();
$html = (string) ob_get_clean();
e2e_check( $s, false !== stripos( $html, 'Affiliate Captain' ) && false === strpos( $html, '@example.org' ), 'Statusseite zeigt den Affiliate-Abschnitt ohne Adressen (' . strlen( $html ) . ' Bytes)' );
e2e_check( $s, current_user_can( 'wpac_manage_affiliates' ), 'Administrator hat wpac_manage_affiliates (Sichtbarkeitsbedingung)' );
e2e_info( $s, 'Affiliate Captain bietet seit Vertrag 1.6.0 den Filter wpac_review_aggregates; Callbacks: ' . ( has_filter( 'wpac_review_aggregates' ) ? 'ja' : 'keine' ) );
