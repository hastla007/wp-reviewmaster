<?php
/**
 * E2E-Hilfe (nur Testinstanz!): simulierter Lizenzserver für Review Captain Pro.
 *
 * Nutzt den dokumentierten Dev-Weg von captain-pro: Konstante REVIEW_CAPTAIN_PRO_LICENSE_DEV
 * erlaubt die Filter `review_captain_pro_license_server_url` und `…_license_public_keys`.
 * Antworten werden wie in packages/license-client/testing/client-test-kit.php (WPLC_Test_Server)
 * signiert (HMAC mit Installationsgeheimnis + Ed25519 über "wplc-response-v2\n{id}\n{payload}").
 * Das beweist NICHT, dass der echte Server (wp-license) gleich antwortet.
 *
 * Rechte steuerbar über Option `e2e_fake_wplc_entitlements` (Liste von Schlüsseln; Standard alle).
 */
defined( 'ABSPATH' ) || exit;
const E2E_FAKE_WPLC_HOST = 'https://license.e2e.test';
defined( 'REVIEW_CAPTAIN_PRO_LICENSE_DEV' ) || define( 'REVIEW_CAPTAIN_PRO_LICENSE_DEV', true );

function e2e_fake_wplc_keypair(): array {
	$pair = sodium_crypto_sign_seed_keypair( str_repeat( "\x07", 32 ) );
	return array( sodium_crypto_sign_secretkey( $pair ), base64_encode( sodium_crypto_sign_publickey( $pair ) ) );
}
add_filter( 'review_captain_pro_license_server_url', static fn() => E2E_FAKE_WPLC_HOST );
add_filter( 'review_captain_pro_license_public_keys', static fn() => array( e2e_fake_wplc_keypair()[1] ) );

add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) {
		if ( 0 !== strpos( $url, E2E_FAKE_WPLC_HOST ) ) {
			return $pre;
		}
		$state   = get_option( 'e2e_fake_wplc', array( 'secrets' => array(), 'log' => array() ) );
		$route   = (string) preg_replace( '#^https?://[^/]+/wp-json#', '', $url );
		$headers = array_change_key_case( (array) $args['headers'], CASE_LOWER );
		$body    = json_decode( (string) $args['body'], true ) ?: array();
		$method  = $headers['x-http-method-override'] ?? $args['method'];
		$inst    = (string) ( $headers['x-wplc-installation-id'] ?? ( $body['installation_id'] ?? '' ) );
		$nonce   = (string) ( $headers['x-wplc-nonce'] ?? $inst );
		if ( '/wplc/v1/activations' === $route ) {
			$state['secrets'][ $inst ] = (string) $body['installation_secret'];
		}
		$secret = (string) ( $state['secrets'][ $inst ] ?? '' );
		$keys   = get_option( 'e2e_fake_wplc_entitlements', array( 'review.platform_sync', 'review.review_requests', 'review.advanced_widgets', 'review.analytics', 'review.suite_integrations', 'review.custom_connectors' ) );
		$now    = time();
		$ent    = array();
		foreach ( (array) $keys as $k ) {
			$ent[ $k ] = true;
		}
		if ( 'DELETE' === $method ) {
			$data = array( 'activation_id' => 41, 'status' => 'deactivated' );
		} elseif ( '/wplc/v1/updates/check' === $route ) {
			$data = array( 'update_available' => false, 'current_version' => $body['current_version'] ?? '', 'product_key' => 'review-captain-pro', 'license_active' => true, 'reason' => 'up_to_date' );
		} else {
			$data = array(
				'activation_id' => 41, 'installation_id' => $inst, 'activation_status' => 'active', 'product_key' => 'review-captain-pro',
				'license_status' => 'active', 'access' => true,
				'paid_until' => gmdate( 'Y-m-d H:i:s', $now + 30 * DAY_IN_SECONDS ), 'access_until' => gmdate( 'Y-m-d H:i:s', $now + 44 * DAY_IN_SECONDS ),
				'grace_until' => gmdate( 'Y-m-d H:i:s', $now + 44 * DAY_IN_SECONDS ), 'site_limit' => 1, 'site_count' => 1,
				'environment' => $body['environment'] ?? 'production', 'staging_parent_id' => null, 'entitlements' => $ent,
				'server_time' => gmdate( 'Y-m-d H:i:s', $now ), 'cache_expires_at' => gmdate( 'Y-m-d H:i:s', $now + 14 * DAY_IN_SECONDS ),
			);
		}
		[ $sk ]  = e2e_fake_wplc_keypair();
		$payload = wp_json_encode( array( 'data' => $data, 'nonce' => $nonce ), JSON_UNESCAPED_SLASHES );
		$env     = array(
			'data'                      => $data,
			'signature'                 => hash_hmac( 'sha256', (string) wp_json_encode( $data, JSON_UNESCAPED_SLASHES ), $secret ),
			'payload'                   => $payload,
			'payload_signature'         => hash_hmac( 'sha256', $payload, $secret ),
			'signature_ed25519'         => base64_encode( sodium_crypto_sign_detached( "wplc-response-v1\n" . strtolower( $inst ) . "\n" . wp_json_encode( $data, JSON_UNESCAPED_SLASHES ), $sk ) ),
			'payload_signature_ed25519' => base64_encode( sodium_crypto_sign_detached( "wplc-response-v2\n" . strtolower( $inst ) . "\n" . $payload, $sk ) ),
			'key_id'                    => 'e2e',
		);
		$state['log'][] = gmdate( 'H:i:s' ) . " $method $route";
		$state['log']   = array_slice( $state['log'], -50 );
		update_option( 'e2e_fake_wplc', $state, false );
		return array( 'headers' => array( 'content-type' => 'application/json; charset=UTF-8' ), 'body' => wp_json_encode( $env, JSON_UNESCAPED_SLASHES ), 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
	},
	10,
	3
);
