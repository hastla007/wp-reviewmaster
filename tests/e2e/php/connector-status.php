<?php
/**
 * CON-016: Registry-Status des Demo-Connectors als JSON (wp eval-file).
 * Optional: E2E_CONNECTOR_ACTION=enqueue|poll
 */
$plugin = \ReviewCaptain\Plugin::instance();
$reg    = $plugin->get( 'connectors' );
$out    = array(
	'fired'    => $reg->fired(),
	'keys'     => $reg->keys(),
	'rejected' => $reg->rejected(),
	'status'   => $reg->status( 'demo-booking' ),
	'details'  => $reg->details( 'demo-booking' ),
	'known'    => array_keys( (array) get_option( 'review_captain_connectors_known', array() ) ),
	'executable' => $reg->is_executable( 'demo-booking' ),
);
$action = getenv( 'E2E_CONNECTOR_ACTION' );
if ( 'enqueue' === $action && function_exists( 'rc_demo_connector_enqueue_reconcile' ) ) {
	$out['enqueue'] = rc_demo_connector_enqueue_reconcile( 'C-101' );
	$out['enqueue_again'] = rc_demo_connector_enqueue_reconcile( 'C-101' );
}
if ( 'poll' === $action && $reg->get( 'demo-booking' ) ) {
	try {
		$c = $reg->get( 'demo-booking' );
		$res = method_exists( $c, 'poll_experiences' ) ? $c->poll_experiences( '', 10 ) : null;
		$out['poll'] = is_object( $res ) ? get_object_vars( $res ) : $res;
	} catch ( \Throwable $e ) {
		$out['poll_error'] = get_class( $e ) . ': ' . $e->getMessage();
	}
}
echo wp_json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
