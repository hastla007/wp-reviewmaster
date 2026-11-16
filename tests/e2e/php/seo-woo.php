<?php
/**
 * SEO-016 Szenario 5: WooCommerce-Produkt mit lokaler Review-Captain-Bewertung (idempotent). Ausgabe: Produkt-URL.
 */
$p = get_page_by_path( 'seo-s05-woo', OBJECT, 'product' );
if ( ! $p ) {
	$product = new WC_Product_Simple();
	$product->set_name( 'S05 Woo-Produkt' );
	$product->set_slug( 'seo-s05-woo' );
	$product->set_regular_price( '19.90' );
	$product->set_status( 'publish' );
	$product->set_description( '[review_captain context_type="product" context_id="current"]' );
	$id = $product->save();
	$r = new \ReviewCaptain\Domain\Review();
	$r->rating_value = 5.0; $r->body = 'S05 lokale Produktbewertung'; $r->author_name = 'E2E';
	$r->status = \ReviewCaptain\Domain\ReviewStatus::APPROVED; $r->consent_publish = true;
	$r->published_at = gmdate( 'Y-m-d H:i:s', time() - 3600 ); $r->context_type = 'product'; $r->context_id = (string) $id;
	\ReviewCaptain\Plugin::instance()->reviews()->insert( $r );
	$p = get_post( $id );
}
echo get_permalink( $p ), "\n";
