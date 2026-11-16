<?php
/**
 * Pro-Testseite (Karussell, Masonry, Badge) und schwebendes Trust-Badge einschalten. Nur mit gültiger (Test-)Lizenz sinnvoll.
 * Aufruf: wp eval-file tests/e2e/php/content-pro.php
 */
$slug    = 'rc-pro';
$content = "<!-- wp:shortcode -->[review_captain type=\"carousel\" limit=\"6\"]<!-- /wp:shortcode -->\n<!-- wp:shortcode -->[review_captain type=\"badge\"]<!-- /wp:shortcode -->\n<!-- wp:shortcode -->[review_captain type=\"masonry\" limit=\"6\"]<!-- /wp:shortcode -->\n<!-- wp:shortcode -->[review_captain_cta profile=\"1\"]<!-- /wp:shortcode -->\n<!-- wp:shortcode -->[review_captain_form]<!-- /wp:shortcode -->";
$p       = get_page_by_path( $slug );
$id      = $p ? $p->ID : wp_insert_post( array( 'post_type' => 'page', 'post_name' => $slug, 'post_title' => 'Pro-Widgets', 'post_status' => 'publish', 'post_content' => $content ) );
if ( class_exists( '\ReviewCaptainPro\Widgets\FloatingBadge' ) ) {
	( new \ReviewCaptainPro\Widgets\FloatingBadge() )->save( array( 'enabled' => '1', 'position' => 'bottom-right' ) );
}
echo wp_json_encode( array( 'id' => $id, 'url' => get_permalink( $id ), 'floating' => get_option( 'review_captain_pro_floating_badge' ) ) );
