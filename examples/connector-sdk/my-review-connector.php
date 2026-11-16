<?php
/**
 * Plugin Name:       My Review Connector
 * Description:       TODO: Kurzbeschreibung – welches Quellsystem meldet welche Erlebnisse an Review Captain?
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  review-captain
 * Author:            TODO Ihr Name / Ihre Firma
 * License:           GPL-2.0-or-later
 * Text Domain:       my-review-connector
 *
 * Kopiervorlage (CON-017). Ersetzen Sie überall:
 *   my-connector           → Ihren Connector-Schlüssel (^[a-z0-9][a-z0-9_-]{2,63}$, nie mehr ändern)
 *   MyVendor\MyConnector   → Ihren PHP-Namespace
 *   my-review-connector    → Ihre Textdomain / Ihren Plugin-Slug
 *   my_source_*            → die Funktionen Ihres Quellsystems
 *
 * @package MyVendor\MyConnector
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'MY_CONNECTOR_DIR' ) ) {
	define( 'MY_CONNECTOR_DIR', __DIR__ );
}

add_action(
	'review_captain_connectors_v1',
	static function ( $registry ): void {
		// Quellsystem nicht aktiv → nicht registrieren (Review Captain zeigt dann source_unavailable).
		if ( ! apply_filters( 'my_connector_source_active', function_exists( 'my_source_get_experiences' ) ) ) {
			return;
		}
		require_once MY_CONNECTOR_DIR . '/src/Connector.php';
		$config = json_decode( (string) file_get_contents( MY_CONNECTOR_DIR . '/config.example.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$registry->register( new \MyVendor\MyConnector\Connector( is_array( $config ) ? $config : array() ) );
	}
);

add_filter(
	'review_captain_process_job_v1',
	static function ( $result, string $type, array $payload ) {
		if ( null !== $result || ! str_starts_with( $type, 'my-connector.' ) ) {
			return $result; // Nur eigene Jobtypen behandeln.
		}
		require_once MY_CONNECTOR_DIR . '/src/JobHandler.php';
		return ( new \MyVendor\MyConnector\JobHandler() )->handle( $type, $payload );
	},
	10,
	3
);
