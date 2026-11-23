<?php
/**
 * RC-106 Szenario 4: Form-Captain-Formular → echte Einsendung über den öffentlichen REST-Endpunkt
 * (Token, Mindestzeit, JSON) → Aktion review.create_local_review (Review Captain Pro) → Review-Entwurf im Core
 * (pending/unverified, origin connector). Keine Einladung.
 * Aufruf: wp eval-file tests/e2e/php/suite/form.php --user=admin   (Webserver muss laufen)
 */
use FormCaptain\Integrations\ConnectorRegistry;
use FormCaptain\Jobs\JobRunner;
use FormCaptain\Plugin as FormPlugin;
use FormCaptain\Repository\FormRepository;

require __DIR__ . '/lib.php';

global $wpdb;
$s      = 'S4-form';
$plugin = FormPlugin::instance();
$reg    = $plugin->get( ConnectorRegistry::class );
$reg->reload();
e2e_check( $s, isset( $reg->actions()['review.create_local_review'] ), 'Form Captain kennt die Aktion review.create_local_review (Connector von Review Captain Pro)' );
e2e_info( $s, 'Connector-Zustand vor Aktivierung: ' . $reg->state( 'review' ) );
$act = $reg->activate( 'review' );
e2e_check( $s, true === $act && ConnectorRegistry::STATE_ACTIVE === $reg->state( 'review' ), 'Connector review aktiviert (' . ( is_wp_error( $act ) ? $act->get_error_code() : $reg->state( 'review' ) ) . ')' );

$fields = array(
	array( 'id' => 'f_rating1', 'type' => 'number', 'key' => 'rating', 'label' => 'Bewertung (1–5)', 'required' => true, 'min' => 1, 'max' => 5 ),
	array( 'id' => 'f_title01', 'type' => 'text', 'key' => 'title', 'label' => 'Titel', 'required' => false ),
	array( 'id' => 'f_body001', 'type' => 'textarea', 'key' => 'body', 'label' => 'Ihre Erfahrung', 'required' => true ),
	array( 'id' => 'f_name001', 'type' => 'text', 'key' => 'name', 'label' => 'Name', 'required' => true ),
	array( 'id' => 'f_mail001', 'type' => 'email', 'key' => 'email', 'label' => 'E-Mail', 'required' => true ),
	array( 'id' => 'f_cons001', 'type' => 'consent', 'key' => 'consent', 'label' => 'Veröffentlichung', 'required' => true, 'consent_text' => 'Meine Bewertung darf veröffentlicht werden.' ),
);
$repo   = $plugin->get( FormRepository::class );
$schema = array( 'schema_version' => 1, 'fields' => $fields );
$fid    = $repo->create(
	'E2E Feedback',
	$schema,
	array(
		'notification' => array( 'enabled' => false ),
		'confirmation' => array( 'type' => 'message', 'message' => 'Danke' ),
		'actions'      => array(
			array(
				'instance_id' => 'a_0e2e000001',
				'action_key'  => 'review.create_local_review',
				'enabled'     => true,
				'settings'    => array( 'require_consent' => '1' ),
				'mapping'     => array( 'rating' => 'f_rating1', 'title' => 'f_title01', 'body' => 'f_body001', 'name' => 'f_name001', 'email' => 'f_mail001', 'consent' => 'f_cons001' ),
			),
		),
	)
);
$repo->publish_version( $fid, $schema );
$form = $repo->find( $fid );
e2e_check( $s, is_array( $form ) && 'published' === ( $form['status'] ?? '' ), "Formular #{$fid} veröffentlicht (public_id " . ( $form['public_id'] ?? '-' ) . ')' );
$page = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'E2E Feedback', 'post_name' => 'e2e-feedback', 'post_content' => '[form_captain id="' . $fid . '"]' ) );
e2e_state( array( 'form_page' => get_permalink( $page ), 'form_id' => $fid ) );

$base  = rest_url( 'form-captain/v1/forms/' . $form['public_id'] );
$token = wp_remote_get( $base . '/token', array( 'timeout' => 20 ) );
$tok   = json_decode( wp_remote_retrieve_body( $token ), true );
e2e_check( $s, 200 === wp_remote_retrieve_response_code( $token ) && ! empty( $tok['token'] ), 'Token über REST geholt (HTTP ' . wp_remote_retrieve_response_code( $token ) . ')' );
sleep( max( 0, (int) ( $tok['min_submit_at'] ?? 0 ) - (int) ( $tok['issued_at'] ?? 0 ) ) + 1 );
$send = static function ( array $values ) use ( $base, $tok ) {
	$r = wp_remote_post(
		$base . '/submissions',
		array(
			'timeout' => 30,
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( array( '_fc_token' => $tok['token'], 'fields' => $values ) ),
		)
	);
	return array( wp_remote_retrieve_response_code( $r ), json_decode( wp_remote_retrieve_body( $r ), true ) );
};
list( $code, $body ) = $send( array( 'f_rating1' => '5', 'f_title01' => 'Toller Kurs', 'f_body001' => 'Sehr gut erklärt, gerne wieder.', 'f_name001' => 'Fritzi Form', 'f_mail001' => 'fritzi.form@example.org', 'f_cons001' => '1' ) );
e2e_check( $s, in_array( $code, array( 200, 201 ), true ) && ! empty( $body['success'] ), "Einsendung angenommen (HTTP {$code}, " . (string) ( $body['code'] ?? '' ) . ')' );
$entry = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(id) FROM %i WHERE form_id = %d', $wpdb->prefix . 'fc_entries', $fid ) );

$runner = $plugin->get( JobRunner::class );
$stats  = $runner->run();
$stats2 = $runner->run();
e2e_info( $s, 'Form-Captain-Jobs: ' . wp_json_encode( $stats ) . ' / ' . wp_json_encode( $stats2 ) );
$job = $wpdb->get_row( $wpdb->prepare( 'SELECT id, type, status, last_error_code FROM %i WHERE entry_id = %d AND type = %s ORDER BY id DESC LIMIT 1', $wpdb->prefix . 'fc_jobs', $entry, ConnectorRegistry::JOB_TYPE ), ARRAY_A );
e2e_check( $s, is_array( $job ) && 'succeeded' === $job['status'], 'Aktionsjob erfolgreich (' . ( $job['status'] ?? 'kein Job' ) . ' ' . (string) ( $job['last_error_code'] ?? '' ) . ')' );

$ext    = \ReviewCaptainPro\Suite\Form\ReviewAction::external_id( $entry );
$review = $wpdb->get_row( $wpdb->prepare( 'SELECT id, status, verification, origin, platform, kind, rating_value, author_email, context_type, context_id FROM %i WHERE external_id = %s', $wpdb->prefix . 'review_captain_reviews', $ext ), ARRAY_A );
e2e_check( $s, is_array( $review ) && 'pending' === $review['status'] && 'unverified' === $review['verification'] && 'connector' === $review['origin'] && 'local' === $review['platform'], 'Review-Entwurf im Core: ' . ( $review ? "#{$review['id']} {$review['status']}/{$review['verification']} origin {$review['origin']} rating {$review['rating_value']}" : 'fehlt' ) );
$count = static fn() => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE external_id = %s', $wpdb->prefix . 'review_captain_reviews', $ext ) );
$again = is_array( $job ) ? $reg->handle_job( $plugin->get( \FormCaptain\Repository\JobRepository::class )->find( (int) $job['id'] ) ) : null;
e2e_check( $s, 1 === $count() && 'success' === ( $again->status ?? '' ), 'S8 Aktion erneut ausgeführt (Duplikat) → weiterhin eine Review (' . $count() . ', ' . ( $again->code ?? '' ) . ')' );
e2e_check( $s, 0 === count( e2e_requests( "source LIKE 'form%'" ) ) && 0 === count( e2e_invitations( 'fritzi.form@example.org' ) ), 'Formular erzeugt keine Bewertungsanfrage und keine Mail' );
$public = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE external_id = %s AND status = 'approved'", $wpdb->prefix . 'review_captain_reviews', $ext ) );
e2e_check( $s, 0 === (int) $public, 'Entwurf ist nicht veröffentlicht (Moderation nötig)' );

// Without consent (require_consent): the action fails, no review.
list( $code2 ) = $send( array( 'f_rating1' => '2', 'f_body001' => 'Ohne Einwilligung.', 'f_name001' => 'Ohne', 'f_mail001' => 'ohne.form@example.org' ) );
e2e_info( $s, "Einsendung ohne Pflicht-Einwilligung: HTTP {$code2} (Form Captain prüft das Pflichtfeld selbst)" );
