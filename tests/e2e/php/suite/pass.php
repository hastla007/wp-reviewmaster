<?php
/**
 * RC-106 Szenario 2: Ticket (Pass Captain) → Check-in → Review-Teilnahmevertrag v1 → Anfrage (Review Captain Pro).
 * Dazu: Kontaktabruf über den ContactResolver mit Dienstkonto (ohne Capability pausiert der Versand),
 * Check-in rückgängig storniert, erneuter Check-in plant genau eine neue Anfrage, Erstattung sperrt das Erlebnis,
 * Replay der Outbox (doppelte Ereignisse).
 * Fixture: Event/Termin/Tickettyp werden wie in den Pass-Captain-Integrationstests direkt angelegt (kein Admin-UI).
 * Aufruf: wp eval-file tests/e2e/php/suite/pass.php --user=admin
 */
use PassCaptain\Checkin;
use PassCaptain\Orders;
use PassCaptain\Workflow\Outbox;
use ReviewCaptainPro\Suite\Adapters\PassContacts;

require __DIR__ . '/lib.php';

global $wpdb;
$s = 'S2-pass';
$p = $wpdb->prefix;
e2e_prepare_requests();
$cid = e2e_campaign( 'suite:pass' );
e2e_check( $s, $cid > 0, "Kampagne suite:pass aktiv (#{$cid})" );
$adapter = new \ReviewCaptainPro\Suite\Adapters\PassAdapter();
e2e_check( $s, 'contract' === $adapter->mode(), 'Pass-Adapter im Modus contract (PassCaptain\Review\Contract::VERSION ' . ( class_exists( '\PassCaptain\Review\Contract' ) ? \PassCaptain\Review\Contract::VERSION : '-' ) . ')' );
$provider = apply_filters( 'wp_pass_captain_review_provider_v1', null, 1 );
e2e_check( $s, is_object( $provider ) && method_exists( $provider, 'receive' ), 'Provider auf wp_pass_captain_review_provider_v1 registriert (' . ( is_object( $provider ) ? get_class( $provider ) : '-' ) . ')' );
update_option( PassContacts::SERVICE_USER_OPTION, 0, false );

$now = gmdate( 'Y-m-d H:i:s' );
$wpdb->insert( "{$p}wpcap_events", array( 'name' => 'E2E Konzert', 'slug' => 'e2e-' . uniqid(), 'description' => '', 'status' => 'published', 'created_at' => $now, 'updated_at' => $now ) );
$event = (int) $wpdb->insert_id;
$wpdb->insert( "{$p}wpcap_occurrences", array( 'event_id' => $event, 'starts_at' => gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS ), 'timezone' => 'Europe/Vienna', 'location' => 'Wien', 'capacity' => 0, 'status' => 'scheduled' ) );
$occ = (int) $wpdb->insert_id;
$wpdb->insert( "{$p}wpcap_ticket_types", array( 'occurrence_id' => $occ, 'name' => 'Standard', 'price_minor' => 2500, 'currency' => 'EUR', 'capacity' => 50, 'max_per_order' => 10, 'status' => 'active' ) );
$type = (int) $wpdb->insert_id;

$attendees = array();
foreach ( array( 'anna', 'bert', 'carla' ) as $n ) {
	$attendees[] = array( 'ticket_type_id' => $type, 'name' => ucfirst( $n ) . ' Gast', 'email' => $n . '.pass@example.org' );
}
$o = Orders::create( array( 'email' => 'kaeufer.pass@example.org', 'items' => array( array( 'ticket_type_id' => $type, 'quantity' => 3 ) ), 'attendees' => $attendees, 'idempotency_key' => 'e2e-' . uniqid() ) );
if ( is_wp_error( $o ) ) {
	e2e_check( $s, false, 'Ticketkauf: ' . $o->get_error_code() . ' ' . $o->get_error_message() );
	return;
}
Orders::confirm_manual_payment( $o['order_id'] );
$tickets = Orders::tickets_for_order( $o['order_id'] );
e2e_check( $s, 3 === count( $tickets ), 'Ticketkauf: Bestellung ' . $o['order_id'] . ' bezahlt, 3 Tickets (' . count( $tickets ) . ')' );
list( $ta, $tb, $tc ) = $tickets;
$reqs = static fn() => e2e_requests( "source = 'pass-captain'" );
$deliver = static function () {
	return Outbox::deliver();
};
$deliver();
e2e_check( $s, 0 === count( $reqs() ), 'Kauf und Ticketversand erzeugen keine Anfrage' );

$checkin = static fn( $t ) => Checkin::perform_ticket( (int) $t->ticket_id, array( 'source' => 'admin', 'idempotency_key' => 'e2e-' . uniqid( '', true ) ) );

// Ticket A: check-in → request; contact lookup without capability → paused; with service account → sent.
$r = $checkin( $ta );
e2e_check( $s, ! is_wp_error( $r ) && 'checked_in' === ( $r['status'] ?? '' ), 'Ticket A eingecheckt (' . ( is_wp_error( $r ) ? $r->get_error_code() : ( $r['status'] ?? '?' ) ) . ')' );
$n = $deliver();
$rows = $reqs();
e2e_check( $s, 1 === count( $rows ) && 'planned' === $rows[0]['state'], "Outbox zugestellt ({$n}) → genau eine geplante Anfrage (" . count( $rows ) . ')' );
$a_id = (int) ( $rows[0]['id'] ?? 0 );
e2e_info( $s, 'experience_id ' . ( $rows[0]['experience_id'] ?? '' ) . ' recipient_ref ' . ( $rows[0]['recipient_ref'] ?? '' ) );
$checkin( $ta );
do_action( 'wpcap_ticket_checked_in', (int) $ta->ticket_id, array( 'source' => 'admin' ) );
$deliver();
e2e_check( $s, 1 === count( $reqs() ), 'S8 zweiter Scan + wiederholter Hook → weiterhin eine Anfrage' );

e2e_run_jobs();
$row  = e2e_requests( 'id = %d', array( $a_id ) )[0];
$job  = array_values( array_filter( e2e_jobs( 'review_captain_pro.request.send' ), static fn( $j ) => false !== strpos( (string) $j['payload'], '"request_id":' . $a_id ) ) )[0] ?? array();
e2e_check( $s, 'eligible' === $row['state'] && 'paused' === ( $job['status'] ?? '' ) && 'rc.source.unavailable' === ( $job['last_error_code'] ?? '' ), 'Ohne Dienstkonto: Versandjob pausiert (' . ( $job['status'] ?? '-' ) . ' ' . ( $job['last_error_code'] ?? '' ) . '), Anfrage bleibt ' . $row['state'] );
e2e_check( $s, 0 === count( e2e_invitations( 'anna.pass@example.org' ) ), 'Ohne Capability keine Mail' );
$counter = e2e_suite_counter( 'pass' );
e2e_info( $s, 'Suite-Zähler pass: ' . wp_json_encode( $counter ) );

// Service account WITHOUT the capability.
$bot = username_exists( 'review-bot' ) ? (int) username_exists( 'review-bot' ) : (int) wp_insert_user( array( 'user_login' => 'review-bot', 'user_pass' => wp_generate_password(), 'user_email' => 'review-bot@example.test', 'role' => 'subscriber' ) );
e2e_check( $s, 0 === ( new PassContacts() )->save_service_user( $bot ), 'Konto ohne wpcap_resolve_review_contacts wird als Dienstkonto abgelehnt' );
update_option( PassContacts::SERVICE_USER_OPTION, $bot, false ); // Capability later removed (stale choice).
e2e_check( $s, 'capability_missing' === ( new PassContacts() )->state(), 'Statusseite meldet: Dienstkonto ohne Capability' );

// With the capability.
( new WP_User( $bot ) )->add_cap( PassContacts::CAPABILITY );
e2e_check( $s, $bot === ( new PassContacts() )->save_service_user( $bot ), 'Dienstkonto mit wpcap_resolve_review_contacts gespeichert' );
$wpdb->query( $wpdb->prepare( "UPDATE %i SET status = 'queued', available_at = %s WHERE id = %d", "{$p}review_captain_jobs", gmdate( 'Y-m-d H:i:s', time() - 1 ), (int) ( $job['id'] ?? 0 ) ) ); // = stündliche Wiederaufnahme vorziehen
e2e_run_jobs();
$row  = e2e_requests( 'id = %d', array( $a_id ) )[0];
$mail = e2e_invitations( 'anna.pass@example.org' );
e2e_check( $s, 'sent' === $row['state'] && 1 === count( $mail ), 'Mit Dienstkonto: Kontakt über ContactResolver, Einladung an die Teilnehmerin (' . $row['state'] . ', Mails ' . count( $mail ) . ')' );
e2e_check( $s, 0 === count( e2e_invitations( 'kaeufer.pass@example.org' ) ), 'Käufer bekommt keine Einladung (Empfänger = Teilnehmer)' );
e2e_check( $s, 0 === get_current_user_id() || 1 === get_current_user_id(), 'Benutzerwechsel für den Abruf zurückgesetzt (aktuell ' . get_current_user_id() . ')' );
$pa = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}wpcap_audit_logs WHERE action='review_contact_resolved'" );
$pl = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}wpcap_audit_logs WHERE action='review_contact_resolved' AND details LIKE '%@%'" );
e2e_check( $s, $pa >= 1 && 0 === $pl, "Pass Captain protokolliert review_contact_resolved ({$pa}) ohne Adresse" );

// Ticket B: check-in → undo → cancelled → check-in again → exactly one new request.
$checkin( $tb );
$deliver();
$b_rows = e2e_requests( "source = 'pass-captain' AND id > %d", array( $a_id ) );
e2e_check( $s, 1 === count( $b_rows ) && 'planned' === $b_rows[0]['state'], 'Ticket B eingecheckt → Anfrage geplant' );
$undo = \PassCaptain\Admin\TicketsPage::undo_checkin( array( 'ticket_id' => (int) $tb->ticket_id, 'reason' => 'E2E falscher Scan' ), array( 'source' => 'admin' ) );
e2e_info( $s, 'undo_checkin: ' . ( is_wp_error( $undo ) ? $undo->get_error_code() . ' ' . $undo->get_error_message() : wp_json_encode( $undo ) ) );
$deliver();
$b1 = e2e_requests( 'id = %d', array( (int) $b_rows[0]['id'] ) )[0];
e2e_check( $s, 'cancelled' === $b1['state'] && 'rc.experience.check_in_undone' === $b1['reason_code'], "Check-in rückgängig → Anfrage storniert ({$b1['state']} {$b1['reason_code']})" );
$checkin( $tb );
$deliver();
$deliver();
$b_all = e2e_requests( "source = 'pass-captain' AND id > %d", array( $a_id ) );
$open  = array_values( array_filter( $b_all, static fn( $r ) => in_array( $r['state'], array( 'planned', 'eligible', 'sent' ), true ) ) );
e2e_check( $s, 2 === count( $b_all ) && 1 === count( $open ), 'Erneuter Check-in → genau eine neue Anfrage (gesamt ' . count( $b_all ) . ', offen ' . count( $open ) . ')' );
$before_replay = $reqs();
Outbox::replay( 0, 'review' );
$deliver();
$after_replay = $reqs();
$open_b       = array_values( array_filter( $after_replay, static fn( $r ) => false !== strpos( $r['experience_id'], ':' . $tb->public_id . ':' ) && in_array( $r['state'], array( 'planned', 'eligible', 'sent' ), true ) ) );
e2e_check( $s, 1 === count( $open_b ), 'S8 Replay aller Review-Ereignisse → weiterhin genau eine offene Anfrage für Ticket B (' . count( $open_b ) . ')' );
e2e_check( $s, count( $before_replay ) === count( $after_replay ), 'S8 Replay legt keine neue Anfragezeile an (' . count( $before_replay ) . ' → ' . count( $after_replay ) . ')' );
foreach ( array_slice( $after_replay, count( $before_replay ) ) as $r ) {
	e2e_info( $s, "nach Replay neu: #{$r['id']} {$r['state']} " . (string) $r['reason_code'] );
}
foreach ( $before_replay as $r ) {
	$now_row = e2e_requests( 'id = %d', array( (int) $r['id'] ) )[0];
	if ( $now_row['state'] !== $r['state'] ) {
		e2e_info( $s, "durch Replay geändert: #{$r['id']} {$r['state']} → {$now_row['state']} " . (string) $now_row['reason_code'] );
	}
}
e2e_run_jobs();
e2e_check( $s, 1 === count( e2e_invitations( 'bert.pass@example.org' ) ), 'Ticket B: genau eine Einladung versendet' );

// Ticket C: check-in → planned → refund → cancelled and locked.
$checkin( $tc );
$deliver();
$c_rows = array_values( array_filter( $reqs(), static fn( $r ) => false !== strpos( $r['experience_id'], ':' . $tc->public_id . ':' ) ) );
e2e_check( $s, 1 === count( $c_rows ) && 'planned' === $c_rows[0]['state'], 'Ticket C eingecheckt → Anfrage geplant' );
PassCaptain\Settings::update( array( 'refund_approval_threshold' => 0 ) );
$refund = PassCaptain\Refunds::request( (int) $o['order_id'], array( (int) $tc->ticket_id ), 'E2E Erstattung', 'e2e-refund-' . uniqid(), array( 'used_override' => 'E2E Kulanz' ) );
e2e_info( $s, 'Refunds::request: ' . ( is_wp_error( $refund ) ? $refund->get_error_code() . ' ' . $refund->get_error_message() : wp_json_encode( array_intersect_key( (array) $refund, array_flip( array( 'status', 'refund_id' ) ) ) ) ) );
if ( ! is_wp_error( $refund ) && 'pending_approval' === ( $refund['status'] ?? '' ) ) {
	$approver = (int) wp_insert_user( array( 'user_login' => 'approver-' . wp_rand(), 'user_pass' => wp_generate_password(), 'user_email' => 'approver' . wp_rand() . '@example.test', 'role' => 'administrator' ) );
	$prev     = get_current_user_id();
	wp_set_current_user( $approver );
	$ap = PassCaptain\Refunds::approve( (int) $refund['refund_id'] );
	wp_set_current_user( $prev );
	e2e_info( $s, 'Refunds::approve (zweite Person): ' . ( is_wp_error( $ap ) ? $ap->get_error_code() : 'ok' ) );
}
if ( ! is_wp_error( $refund ) && 1 !== (int) $wpdb->get_var( $wpdb->prepare( "SELECT refunded FROM {$p}wpcap_tickets WHERE ticket_id = %d", (int) $tc->ticket_id ) ) ) {
	// SQLite-Grenze: Pass Captain bucht die Erstattung mit UPDATE … INNER JOIN, das die SQLite-Emulation nicht kann
	// (wpcap_db_busy / refund_bookkeeping_failed). Nachgestellt wird nur der gebuchte Endzustand + der öffentliche Hook.
	$wpdb->update( "{$p}wpcap_tickets", array( 'refunded' => 1, 'capacity_released' => 1 ), array( 'ticket_id' => (int) $tc->ticket_id ) );
	$wpdb->update( "{$p}wpcap_refunds", array( 'status' => 'completed' ), array( 'refund_id' => (int) $refund['refund_id'] ) );
	do_action( 'wpcap_refund_completed', (int) $refund['refund_id'], (int) $o['order_id'] );
	e2e_info( $s, 'Erstattung auf SQLite nachgestellt (Ticket refunded=1, Hook wpcap_refund_completed)' );
}
$refunded = (int) $wpdb->get_var( $wpdb->prepare( "SELECT refunded FROM {$p}wpcap_tickets WHERE ticket_id = %d", (int) $tc->ticket_id ) );
$deliver();
$c1 = e2e_requests( 'id = %d', array( (int) $c_rows[0]['id'] ) )[0];
e2e_check( $s, 1 === $refunded && 'cancelled' === $c1['state'] && 'rc.experience.refunded' === $c1['reason_code'], "Erstattung (Ticket refunded={$refunded}) → Anfrage storniert ({$c1['state']} {$c1['reason_code']})" );
Outbox::replay( 0, 'review' );
$deliver();
$after = e2e_requests( "source = 'pass-captain' AND experience_id = %s", array( $c1['experience_id'] ) );
e2e_check( $s, 1 === count( $after ), 'Erstattung sperrt das Erlebnis: Replay des Abschlusses plant nichts Neues (' . count( $after ) . ')' );
$recheck = $checkin( $tc );
e2e_info( $s, 'Check-in des erstatteten Tickets: ' . ( is_wp_error( $recheck ) ? $recheck->get_error_code() : ( $recheck['status'] ?? '?' ) ) );
e2e_run_jobs();
e2e_check( $s, 0 === count( e2e_invitations( 'carla.pass@example.org' ) ), 'Ticket C: keine Einladung' );
e2e_info( $s, 'Suite-Zähler pass: ' . wp_json_encode( e2e_suite_counter( 'pass' ) ) );
$fail = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Outbox::table() . " WHERE contract='review' AND status='failed'" );
e2e_check( $s, 0 === $fail, "Keine endgültig gescheiterten Review-Ereignisse in der Outbox ({$fail})" );
