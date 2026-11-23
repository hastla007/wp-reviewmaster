<?php
/**
 * RC-137: Testseite mit allen Pro-Widgets (Karussell, Plattformleiste, Testimonial-Wall, Trustmarks,
 * Provider-Embed-Platzhalter, schwebendes Badge) und Daten dafür. Idempotent über Slugs/Titel.
 * Seiten: /rc-pro-widgets/ (alle Widgets, kein Review-Formular → automatisches Badge erlaubt, dazu ein
 * gewöhnliches Kontaktformular für die Überdeckungsprüfung), /rc-pro-form/ (Widgets + Review-Formular → kein Badge).
 * Aufruf: wp eval-file tests/e2e/php/suite/widgets.php --user=admin
 */
use ReviewCaptainPro\SocialProof\Installer as SpInstaller;
use ReviewCaptainPro\SocialProof\TestimonialRepository;
use ReviewCaptainPro\SocialProof\TrustAssets;
use ReviewCaptainPro\Widgets\FloatingBadge;
use ReviewCaptainPro\Widgets\ProviderEmbed;

global $wpdb;
if ( class_exists( SpInstaller::class ) && method_exists( SpInstaller::class, 'maybe_install' ) ) {
	SpInstaller::maybe_install();
} else {
	do_action( 'admin_init' );
}
$out = array();

// Approved local reviews.
$reviews = $wpdb->prefix . 'review_captain_reviews';
if ( (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE status = 'approved' AND title LIKE %s", $reviews, 'E2E Widget%' ) ) < 6 ) {
	$texts = array( 'Freundlich und schnell, gerne wieder.', 'Sehr gute Beratung im Laden.', 'Lieferung kam einen Tag früher als angekündigt.', 'Preis-Leistung stimmt, kleine Mängel bei der Verpackung.', 'Kompetentes Team, klare Absprachen.', 'Würde ich weiterempfehlen.' );
	foreach ( $texts as $i => $t ) {
		$r = \ReviewCaptainPro\Core\CoreBridge::instance()->create_local_review( array( 'external_id' => 'e2e-widget-' . $i, 'rating' => 5 - ( $i % 2 ), 'title' => 'E2E Widget ' . ( $i + 1 ), 'body' => $t, 'author_name' => 'Kundin ' . ( $i + 1 ), 'author_email' => '', 'consent_publish' => true ) );
		$wpdb->update( $reviews, array( 'status' => 'approved', 'published_at' => gmdate( 'Y-m-d H:i:s' ) ), array( 'id' => (int) $r['id'] ) );
	}
}

// Platform profiles with stats; Trustpilot with TrustBox placeholder.
$profiles = new \ReviewCaptain\Admin\Profiles();
$find     = static function ( string $label ) use ( $profiles ) {
	foreach ( $profiles->all() as $row ) {
		if ( $label === ( $row['label'] ?? '' ) ) {
			return (int) $row['id'];
		}
	}
	return 0;
};
$google = $find( 'E2E Google' ) ?: (int) $profiles->save( array( 'platform' => 'google', 'label' => 'E2E Google', 'review_url' => 'https://g.page/r/e2e-test/review', 'profile_url' => 'https://maps.google.com/?cid=1234567890' ) )['id'];
$tp     = $find( 'E2E Trustpilot' ) ?: (int) $profiles->save( array( 'platform' => 'trustpilot', 'label' => 'E2E Trustpilot', 'profile_url' => 'https://www.trustpilot.com/review/example.test' ) )['id'];
$embed  = ProviderEmbed::validate( 'trustpilot', array( 'trustbox' => array( 'enabled' => '1', 'business_unit_id' => '0123456789abcdef01234567', 'template_id' => '5419b6a8b0d04a076446a9ad', 'locale' => 'de-DE', 'theme' => 'light', 'height' => 150 ) ), 'https://www.trustpilot.com/review/example.test' );
$wpdb->update( $wpdb->prefix . 'review_captain_profiles', array( 'settings' => wp_json_encode( array( 'stats' => array( 'rating' => 4.6, 'scale_min' => 1, 'scale_max' => 5, 'count' => 128, 'as_of' => gmdate( 'Y-m-d' ) ) ) ) ), array( 'id' => $google ) );
$wpdb->update( $wpdb->prefix . 'review_captain_profiles', array( 'settings' => wp_json_encode( array( 'stats' => array( 'rating' => 4.3, 'scale_min' => 1, 'scale_max' => 5, 'count' => 512, 'as_of' => gmdate( 'Y-m-d' ) ), 'provider_embed' => $embed['config'] ) ) ), array( 'id' => $tp ) );
$out['profiles'] = array( 'google' => $google, 'trustpilot' => $tp, 'embed_errors' => $embed['errors'] ?? array() );

// Testimonial (text) with documented rights.
$repo = new TestimonialRepository();
if ( ! $repo->visible() ) {
	$id = $repo->save( array( 'type' => 'text', 'title' => 'Endlich verstanden', 'quote' => 'Der Kurs hat mir die Angst vor dem Segeln genommen. Klare Erklärungen und viel Geduld.', 'author_name' => 'Petra S.', 'author_label' => 'Kursteilnehmerin', 'show_name' => '1', 'rights_name' => '1', 'rights_note' => 'Einwilligung schriftlich, Ordner Marketing 2026', 'rights_granted_at' => gmdate( 'Y-m-d' ), 'valid_until' => gmdate( 'Y-m-d', time() + YEAR_IN_SECONDS ) ) );
	$out['testimonial'] = is_wp_error( $id ) ? $id->get_error_code() : $repo->publish( (int) $id );
}

// Trustmark with an image from the media library.
$assets = new TrustAssets();
if ( ! $assets->visible() ) {
	$upload = wp_upload_dir();
	$file   = trailingslashit( $upload['path'] ) . 'e2e-siegel.png';
	$im     = imagecreatetruecolor( 160, 160 );
	imagefill( $im, 0, 0, imagecolorallocate( $im, 20, 90, 60 ) );
	imagestring( $im, 5, 30, 70, 'E2E SIEGEL', imagecolorallocate( $im, 255, 255, 255 ) );
	imagepng( $im, $file );
	$att = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => 'E2E Siegel', 'post_status' => 'inherit' ), $file );
	require_once ABSPATH . 'wp-admin/includes/image.php';
	wp_update_attachment_metadata( $att, wp_generate_attachment_metadata( $att, $file ) );
	update_post_meta( $att, '_wp_attachment_image_alt', 'Gütesiegel E2E Prüfstelle 2026' );
	$tid = $assets->save( array( 'title' => 'Geprüfter Kundenservice 2026', 'kind' => 'seal', 'issuer' => 'E2E Prüfstelle', 'valid_from' => gmdate( 'Y-m-d', time() - DAY_IN_SECONDS ), 'valid_until' => gmdate( 'Y-m-d', time() + YEAR_IN_SECONDS ), 'attachment_id' => $att, 'verify_url' => 'https://pruefstelle.example/zertifikat/e2e', 'trademark_ok' => '1', 'placements' => array( 'widget', 'floating_badge' ) ) );
	$out['trustmark'] = is_wp_error( $tid ) ? $tid->get_error_code() . ' ' . wp_json_encode( $tid->get_error_data() ) : $tid;
}

$widgets = "<!-- wp:heading --><h2>Das sagen unsere Kunden</h2><!-- /wp:heading -->\n"
	. "<!-- wp:shortcode -->[review_captain v=\"1\" type=\"carousel\" items=\"all\" limit=\"8\" columns=\"3\" title=\"Bewertungen im Karussell\"]<!-- /wp:shortcode -->\n"
	. "<!-- wp:shortcode -->[review_captain v=\"1\" type=\"platform_bar\" title=\"Unsere Plattformen\"]<!-- /wp:shortcode -->\n"
	. "<!-- wp:shortcode -->[review_captain v=\"1\" type=\"testimonial_wall\" title=\"Erfahrungsberichte\"]<!-- /wp:shortcode -->\n"
	. "<!-- wp:shortcode -->[review_captain v=\"1\" type=\"trustmarks\" title=\"Auszeichnungen\"]<!-- /wp:shortcode -->\n"
	. "<!-- wp:shortcode -->[review_captain v=\"1\" type=\"provider_embed\" profile=\"{$tp}\"]<!-- /wp:shortcode -->\n";
$contact = "<!-- wp:html --><form class=\"e2e-contact\" action=\"#\" method=\"post\"><h2>Kontakt</h2><p><label for=\"e2e-name\">Name</label><br><input id=\"e2e-name\" name=\"name\" type=\"text\" autocomplete=\"name\"></p><p><label for=\"e2e-msg\">Nachricht</label><br><textarea id=\"e2e-msg\" name=\"msg\" rows=\"4\"></textarea></p><p><button type=\"submit\">Senden</button></p></form><!-- /wp:html -->";
$pages   = array(
	'rc-pro-widgets' => array( 'Pro-Widgets', $widgets . $contact ),
	'rc-pro-form'    => array( 'Pro-Widgets mit Bewertungsformular', $widgets . "<!-- wp:shortcode -->[review_captain_form]<!-- /wp:shortcode -->" ),
);
foreach ( $pages as $slug => list( $title, $content ) ) {
	$p  = get_page_by_path( $slug );
	$id = $p ? $p->ID : wp_insert_post( array( 'post_type' => 'page', 'post_name' => $slug, 'post_title' => $title, 'post_status' => 'publish', 'post_content' => $content ) );
	if ( $p ) {
		wp_update_post( array( 'ID' => $id, 'post_content' => $content ) );
	}
	$out['pages'][ $slug ] = get_permalink( $id );
}
// Floating badge: automatic on all pages, settings as shipped (start minimised).
$out['floating'] = ( new FloatingBadge() )->save( array( 'enabled' => '1', 'position' => 'bottom-right', 'pages' => 'all', 'start_minimised' => '1', 'hide_on_commerce' => '1' ) );
echo wp_json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ), "\n";
