<?php
/**
 * RC-131/RC-133: WooCommerce order → Pro experience → planned review request (Core + Pro + Woo).
 * Requires: Pro active and licensed (mu-plugins/e2e-license-fake.php), WooCommerce active.
 * Run: wp eval-file tests/e2e/php/woo-flow.php
 */
use ReviewCaptainPro\Requests\Campaigns;
use ReviewCaptainPro\Requests\Schema as ProSchema;

global $wpdb;
$out  = array();
$pass = static function ( bool $ok, string $label ) use ( &$out ) {
	$out[] = ( $ok ? 'PASS ' : 'FAIL ' ) . $label;
};
$pass( defined( 'WC_VERSION' ), 'WooCommerce aktiv (' . ( defined( 'WC_VERSION' ) ? WC_VERSION : '-' ) . ')' );
$pass( function_exists( '\ReviewCaptainPro\addon' ), 'Review Captain Pro geladen' );

// Campaign for WooCommerce, consent basis, no delay.
$campaigns = new Campaigns();
$cid       = $campaigns->save(
	Campaigns::sanitize(
		array(
			'name' => 'E2E Woo', 'source' => 'woocommerce', 'status' => 'active', 'delay_days' => 0,
			'target_type' => 'local', 'target_url' => home_url( '/rc-shortcodes/' ), 'allowed_bases' => array( 'consent' ),
			'legal_basis_confirmed' => 1,
		)
	)
);
$pass( $cid > 0, "Kampagne angelegt (#{$cid})" );

// Order with consent meta (WooAdapter consent_meta_key default checked below).
$settings = \ReviewCaptainPro\Woo\WooAdapter::settings();
$product  = get_page_by_path( 'seo-s05-woo', OBJECT, 'product' );
$order    = wc_create_order();
$order->set_billing_first_name( 'Erika' );
$order->set_billing_email( 'erika.e2e@example.org' );
if ( $product ) {
	$order->add_product( wc_get_product( $product->ID ), 1 );
}
$order->update_meta_data( $settings['consent_meta_key'], 'yes' );
$order->calculate_totals();
$order->save();
$before = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', ProSchema::table( 'requests' ) ) );
$order->update_status( $settings['trigger_status'], 'E2E' );
$after = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', ProSchema::table( 'requests' ) ) );
$pass( $after === $before + 1, "Bestellung #{$order->get_id()} → Status {$settings['trigger_status']} → Anfrage geplant ({$before} → {$after})" );

// Same status again must not plan a second request.
$order->update_status( 'processing', 'E2E' );
$order->update_status( $settings['trigger_status'], 'E2E again' );
$again = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', ProSchema::table( 'requests' ) ) );
$pass( $again === $after, "Statuswechsel hin und her → keine zweite Anfrage ({$again})" );

// Refund revokes.
$order->update_status( 'refunded', 'E2E' );
$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT 1', ProSchema::table( 'requests' ) ), ARRAY_A );
$pass( is_array( $row ) && in_array( 'cancelled', $row, true ) && in_array( 'rc.experience.revoked', $row, true ), 'Erstattung → Anfrage storniert (cancelled, rc.experience.revoked)' );
$pass( false === strpos( (string) wp_json_encode( $row ), 'erika.e2e@example.org' ), 'Anfragezeile enthält keine E-Mail-Adresse im Klartext' );
echo implode( "\n", $out ), "\n";
