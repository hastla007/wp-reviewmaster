<?php
/**
 * SEO-016: scenario posts with dedicated reviews (idempotent by slug). Output: JSON slug => URL.
 * Run: wp eval-file tests/e2e/php/seo-content.php
 */
use ReviewCaptain\Domain\Kind;
use ReviewCaptain\Domain\Platform;
use ReviewCaptain\Domain\Review;
use ReviewCaptain\Domain\ReviewStatus;
use ReviewCaptain\Plugin;

$repo = Plugin::instance()->reviews();
$post = static function ( string $slug, string $title, string $content, string $type = 'post' ): int {
	$p = get_page_by_path( $slug, OBJECT, $type );
	return $p ? (int) $p->ID : (int) wp_insert_post( array( 'post_type' => $type, 'post_name' => $slug, 'post_title' => $title, 'post_status' => 'publish', 'post_content' => $content ) );
};
$review = static function ( array $f ) use ( $repo ): void {
	$r                  = new Review();
	$r->rating_value    = $f['rating'] ?? 5.0;
	$r->title           = $f['title'] ?? '';
	$r->body            = $f['body'];
	$r->author_name     = $f['author'] ?? 'E2E Kundin';
	$r->status          = ReviewStatus::APPROVED;
	$r->consent_publish = true;
	$r->published_at    = gmdate( 'Y-m-d H:i:s', time() - 86400 );
	$r->kind            = $f['kind'] ?? Kind::REVIEW;
	$r->context_type    = $f['ctx'] ?? null;
	$r->context_id      = isset( $f['id'] ) ? (string) $f['id'] : null;
	if ( isset( $f['platform'] ) ) {
		$r->platform    = $f['platform'];
		$r->source_url  = 'https://example.org/review/' . md5( $f['body'] );
		$r->external_id = 'e2e-seo-' . md5( $f['body'] );
	}
	$repo->insert( $r );
};
$marker = 'e2e_seo_seeded';
$sc     = static fn( string $atts ) => "<!-- wp:shortcode -->[review_captain {$atts}]<!-- /wp:shortcode -->";

$ids = array(
	's01' => $post( 'seo-s01-post', 'S01 Beitrag mit lokalen Reviews', $sc( 'context_type="post" context_id="current"' ) ),
	's06' => $post( 'seo-s06-extern', 'S06 Nur externe Reviews', $sc( 'context_type="post" context_id="current"' ) ),
	's07' => $post( 'seo-s07-unternehmen', 'S07 Unternehmensreviews', $sc( 'context_type="organization"' ), 'page' ),
	's08' => $post( 'seo-s08-gemischt', 'S08 Lokal und extern', $sc( 'context_type="post" context_id="current"' ) ),
	's09' => $post( 'seo-s09-testimonials', 'S09 Testimonials', $sc( 'type="testimonials" context_type="post" context_id="current"' ) . $sc( 'kind="testimonial" context_type="post" context_id="current"' ) ),
	's11' => $post( 'seo-s11-event', 'S11 Event', $sc( 'context_type="event" context_id="current"' ) ),
	's12' => $post( 'seo-s12-doppelt', 'S12 Zwei Widgets', $sc( 'context_type="post" context_id="current"' ) . $sc( 'context_type="post" context_id="current" layout="list"' ) ),
	's13' => $post( 'seo-s13-leer', 'S13 Leeres Widget', $sc( 'context_type="post" context_id="current" min_rating="5"' ) ),
);
if ( ! get_option( $marker ) ) {
	$review( array( 'ctx' => 'post', 'id' => $ids['s01'], 'body' => 'S01 lokale Bewertung eins', 'rating' => 5.0 ) );
	$review( array( 'ctx' => 'post', 'id' => $ids['s01'], 'body' => 'S01 lokale Bewertung zwei', 'rating' => 4.0 ) );
	$review( array( 'ctx' => 'post', 'id' => $ids['s06'], 'body' => 'S06 Google-Bewertung', 'platform' => Platform::GOOGLE ) );
	$review( array( 'ctx' => 'post', 'id' => $ids['s06'], 'body' => 'S06 Trustpilot-Bewertung', 'platform' => Platform::TRUSTPILOT ) );
	$review( array( 'ctx' => 'organization', 'body' => 'S07 Unternehmen lokal', 'rating' => 5.0 ) );
	$review( array( 'ctx' => 'post', 'id' => $ids['s08'], 'body' => 'S08 lokal', 'rating' => 5.0 ) );
	$review( array( 'ctx' => 'post', 'id' => $ids['s08'], 'body' => 'S08 Google', 'platform' => Platform::GOOGLE ) );
	$review( array( 'ctx' => 'post', 'id' => $ids['s09'], 'body' => 'S09 Testimonial', 'kind' => Kind::TESTIMONIAL ) );
	$review( array( 'ctx' => 'event', 'id' => $ids['s11'], 'body' => 'S11 Event-Bewertung', 'rating' => 5.0 ) );
	$review( array( 'ctx' => 'post', 'id' => $ids['s12'], 'body' => 'S12 einmalige Bewertung', 'rating' => 5.0 ) );
	$review( array( 'ctx' => 'post', 'id' => $ids['s13'], 'body' => 'S13 nur vier Sterne', 'rating' => 4.0 ) );
	update_option( $marker, 1, false );
}
echo wp_json_encode( array_map( static fn( $id ) => get_permalink( $id ), $ids ) );
