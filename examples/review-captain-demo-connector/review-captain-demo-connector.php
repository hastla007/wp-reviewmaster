<?php
/**
 * Plugin Name:       Review Captain Demo Connector
 * Description:       Referenz-Connector (CON-015): meldet ein abgeschlossenes Kurs-Erlebnis aus lokalen Beispieldaten an Review Captain.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  review-captain
 * Author:            Captain Suite
 * License:           GPL-2.0-or-later
 * Text Domain:       review-captain-demo-connector
 *
 * @package CaptainDemo\BookingConnector
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'RC_DEMO_CONNECTOR_DIR' ) ) {
	define( 'RC_DEMO_CONNECTOR_DIR', __DIR__ );
}

/*
 * Registrierung über den öffentlichen Vertrag. Die Klassen werden erst im Callback geladen,
 * damit `implements` ohne aktives Review Captain keinen Fatal Error auslöst.
 */
add_action(
	'review_captain_connectors_v1',
	static function ( $registry ): void {
		require_once RC_DEMO_CONNECTOR_DIR . '/src/Connector.php';
		$registry->register( new \CaptainDemo\BookingConnector\Connector( RC_DEMO_CONNECTOR_DIR . '/fixtures/enrollments.json' ) );
	}
);

/*
 * Eigener Jobtyp im Review-Captain-Jobrunner: {connector_key}.{name}.
 */
add_filter(
	'review_captain_process_job_v1',
	static function ( $result, string $type, array $payload ) {
		if ( null !== $result || ! str_starts_with( $type, 'demo-booking.' ) ) {
			return $result;
		}
		require_once RC_DEMO_CONNECTOR_DIR . '/src/ReconcileJob.php';
		return ( new \CaptainDemo\BookingConnector\ReconcileJob( RC_DEMO_CONNECTOR_DIR . '/fixtures/enrollments.json' ) )->handle( $type, $payload );
	},
	10,
	3
);

/**
 * Reiht einen Abgleichjob über die Core-Job-API ein (kein direkter Tabellenzugriff).
 *
 * @param string $course_id Kurs-ID.
 * @return int|false Job-ID oder false.
 */
function rc_demo_connector_enqueue_reconcile( string $course_id ) {
	if ( ! class_exists( '\ReviewCaptain\Plugin' ) || ! \ReviewCaptain\Plugin::instance()->has( 'jobs' ) ) {
		return false;
	}
	$jobs = \ReviewCaptain\Plugin::instance()->get( 'jobs' );
	return $jobs->enqueue(
		'demo-booking.reconcile',
		array(
			'v'         => 1,
			'course_id' => $course_id,
		),
		'demo-booking:reconcile:' . $course_id
	);
}
