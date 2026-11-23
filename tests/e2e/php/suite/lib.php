<?php
/**
 * RC-106: gemeinsame Helfer der Suite-E2E-Skripte (nur Testinstanz). Wird von den Szenario-Dateien geladen.
 * Ergebnisse: Zeilen "PASS|FAIL|INFO <Szenario> <Text>" auf stdout.
 */
use ReviewCaptainPro\Requests\Campaigns;
use ReviewCaptainPro\Requests\Schema as ProSchema;

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'e2e_check' ) ) {
	/** Prüfung ausgeben. */
	function e2e_check( string $scenario, bool $ok, string $label ): bool {
		echo ( $ok ? 'PASS ' : 'FAIL ' ), $scenario, ' ', $label, "\n";
		return $ok;
	}

	/** Information ausgeben. */
	function e2e_info( string $scenario, string $text ): void {
		echo 'INFO ', $scenario, ' ', $text, "\n";
	}

	/** Grundeinstellungen für den Versand: Absender, Versandfenster immer offen, Pro-Tabellen vorhanden. */
	function e2e_prepare_requests(): void {
		ProSchema::maybe_install();
		$settings             = get_option( 'review_captain_settings', array() );
		$settings             = is_array( $settings ) ? $settings : array();
		$settings['requests'] = array_merge(
			(array) ( $settings['requests'] ?? array() ),
			array(
				'sender_name'       => 'E2E Shop',
				'sender_email'      => 'shop@example.test',
				'send_window_start' => '00:00',
				'send_window_end'   => '00:00',
				'daily_limit'       => 1000,
			)
		);
		update_option( 'review_captain_settings', $settings );
		\ReviewCaptain\Plugin::instance()->settings()->flush();
	}

	/** Aktive Kampagne einer Quelle (anlegen, falls nicht vorhanden). */
	function e2e_campaign( string $source, bool $legal_basis_confirmed = true ): int {
		$campaigns = new Campaigns();
		foreach ( $campaigns->active_for( Campaigns::event_source( $source ) ) as $c ) {
			if ( $source === $c['source'] ) {
				return (int) $c['id'];
			}
		}
		return (int) $campaigns->save(
			Campaigns::sanitize(
				array(
					'name'                  => 'E2E ' . $source,
					'source'                => $source,
					'status'                => 'active',
					'delay_days'            => 0,
					'target_type'           => 'local',
					'target_url'            => home_url( '/bewerten/' ),
					'allowed_bases'         => array( 'consent' ),
					'legal_basis_confirmed' => $legal_basis_confirmed ? 1 : 0,
				)
			)
		);
	}

	/** Anfragen (Pro-Tabelle) mit Bedingung, z. B. "experience_id LIKE 'voucher:%'". */
	function e2e_requests( string $where = '1=1', array $args = array() ): array {
		global $wpdb;
		$sql = 'SELECT id, uuid, experience_id, recipient_ref, source, state, reason_code, contact_hash, campaign_id FROM %i WHERE ' . $where . ' ORDER BY id';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- test helper, fixed fragments.
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, array_merge( array( ProSchema::table( 'requests' ) ), $args ) ), ARRAY_A );
	}

	/** Core-Jobs abarbeiten (wie `wp review-captain jobs run`), höchstens $rounds Läufe. */
	function e2e_run_jobs( int $rounds = 3 ): array {
		$total = array( 'processed' => 0, 'succeeded' => 0, 'failed' => 0 );
		for ( $i = 0; $i < $rounds; $i++ ) {
			$r = \ReviewCaptain\Plugin::instance()->get( 'jobs.runner' )->run( 20 );
			foreach ( $total as $k => $v ) {
				$total[ $k ] = $v + (int) ( $r[ $k ] ?? 0 );
			}
			if ( 0 === (int) ( $r['processed'] ?? 0 ) ) {
				break;
			}
		}
		return $total;
	}

	/** Core-Jobs eines Typs. */
	function e2e_jobs( string $type ): array {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT id, type, status, attempts, last_error_code, payload FROM %i WHERE type = %s ORDER BY id', $wpdb->prefix . 'review_captain_jobs', $type ), ARRAY_A );
	}

	/** Abgefangene Mails (mu-plugins/e2e-mail-capture.php), optional nur an eine Adresse. */
	function e2e_mails( string $to = '' ): array {
		$mails = get_option( 'e2e_mails', array() );
		$mails = is_array( $mails ) ? $mails : array();
		return '' === $to ? $mails : array_values( array_filter( $mails, static fn( $m ) => false !== stripos( (string) $m['to'], $to ) ) );
	}

	/** Einladungen (Mails mit Abmeldelink von Pro) an eine Adresse. */
	function e2e_invitations( string $to ): array {
		return array_values( array_filter( e2e_mails( $to ), static fn( $m ) => false !== strpos( (string) $m['message'], 'review_captain_pro_unsubscribe' ) || false !== strpos( wp_json_encode( $m['headers'] ), 'review_captain_pro_unsubscribe' ) ) );
	}

	/** Zustand speichern/lesen (Option e2e_suite_state). */
	function e2e_state( ?array $set = null ): array {
		$state = get_option( 'e2e_suite_state', array() );
		$state = is_array( $state ) ? $state : array();
		if ( null !== $set ) {
			$state = array_merge( $state, $set );
			update_option( 'e2e_suite_state', $state, false );
		}
		return $state;
	}

	/** Suite-Zähler eines Adapters (Statusseite). */
	function e2e_suite_counter( string $key ): array {
		$all = get_option( 'review_captain_pro_suite_events', array() );
		return is_array( $all ) && is_array( $all[ $key ] ?? null ) ? $all[ $key ] : array();
	}

	/** Einfaches virtuelles WooCommerce-Produkt. */
	function e2e_product( string $name, string $price = '50', array $meta = array() ): int {
		$product = new WC_Product_Simple();
		$product->set_name( $name );
		$product->set_regular_price( $price );
		$product->set_virtual( true );
		$product->set_status( 'publish' );
		foreach ( $meta as $k => $v ) {
			$product->update_meta_data( $k, $v );
		}
		return (int) $product->save();
	}
}
