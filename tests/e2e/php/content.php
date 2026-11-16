<?php
/**
 * Legt die Testseiten an (idempotent über Slug) und gibt sie als JSON aus.
 * Aufruf: wp eval-file tests/e2e/php/content.php
 */
$profiles = new \ReviewCaptain\Admin\Profiles();
$cta_id   = 0;
foreach ( $profiles->all() as $row ) {
	if ( 'E2E Google' === ( $row['label'] ?? '' ) ) {
		$cta_id = (int) $row['id'];
	}
}
if ( 0 === $cta_id ) {
	$res    = $profiles->save( array( 'platform' => 'google', 'label' => 'E2E Google', 'review_url' => 'https://g.page/r/e2e-test/review' ) );
	$cta_id = (int) $res['id'];
}
$pages = array(
	'rc-shortcodes' => array( 'page', 'Bewertungen (Shortcodes)', "<!-- wp:paragraph --><p>Shortcode-Seite.</p><!-- /wp:paragraph -->\n<!-- wp:shortcode -->[review_captain limit=\"12\" columns=\"3\"]<!-- /wp:shortcode -->\n<!-- wp:shortcode -->[review_captain_cta profile=\"{$cta_id}\"]<!-- /wp:shortcode -->\n<!-- wp:shortcode -->[review_captain_form]<!-- /wp:shortcode -->" ),
	'rc-blocks'     => array( 'page', 'Bewertungen (Blöcke)', "<!-- wp:review-captain/reviews {\"limit\":6,\"columns\":2} /-->\n<!-- wp:review-captain/form /-->" ),
	'rc-produkt'    => array( 'post', 'Testprodukt Kaffeemühle', "<!-- wp:paragraph --><p>Lokales Produkt ohne WooCommerce.</p><!-- /wp:paragraph -->\n<!-- wp:shortcode -->[review_captain context_type=\"product\" context_id=\"current\" limit=\"5\"]<!-- /wp:shortcode -->\n<!-- wp:shortcode -->[review_captain_form context_type=\"product\" context_id=\"current\"]<!-- /wp:shortcode -->" ),
	'rc-perf'       => array( 'page', 'Performance', "<!-- wp:shortcode -->[review_captain limit=\"24\" paginate=\"1\"]<!-- /wp:shortcode -->" ),
);
$out = array();
foreach ( $pages as $slug => [ $type, $title, $content ] ) {
	$existing = get_page_by_path( $slug, OBJECT, $type );
	$id       = $existing ? $existing->ID : wp_insert_post( array( 'post_type' => $type, 'post_name' => $slug, 'post_title' => $title, 'post_status' => 'publish', 'post_content' => $content ) );
	$out[ $slug ] = array( 'id' => (int) $id, 'url' => get_permalink( $id ) );
}
$out['cta_profile'] = $cta_id;
echo wp_json_encode( $out );
