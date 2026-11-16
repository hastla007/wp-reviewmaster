<?php
/**
 * E2E-Hilfe: schreibt bei _doing_it_wrong / Deprecations einen kompakten Backtrace ins debug.log,
 * damit Notices einer Datei:Zeile im Plugin zugeordnet werden können. Nur Testinstanz.
 */
$e2e_trace = static function ( ...$args ) {
	$frames = array();
	foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ) as $f ) {
		if ( isset( $f['file'] ) && preg_match( '#/plugins/(review-captain[^/]*/.*)$#', $f['file'], $m ) ) {
			$frames[] = $m[1] . ':' . $f['line'];
		}
	}
	error_log( 'E2E-TRACE ' . ( is_string( $args[0] ?? null ) ? $args[0] : '' ) . ' <= ' . implode( ' <= ', array_slice( $frames, 0, 6 ) ) );
};
add_action( 'doing_it_wrong_run', $e2e_trace, 10, 3 );
add_action( 'deprecated_function_run', $e2e_trace, 10, 3 );
add_action( 'deprecated_argument_run', $e2e_trace, 10, 3 );
add_action( 'deprecated_hook_run', $e2e_trace, 10, 4 );
