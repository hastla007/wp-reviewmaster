<?php
/**
 * E2E-Hilfe (nur Testinstanz!): fängt wp_mail() ab, statt zu versenden.
 *
 * Jede Mail landet in der Option `e2e_mails` (höchstens 200, neueste zuletzt) und gilt als angenommen.
 * Damit lassen sich Einladungen, Abmeldelinks und Ticketcodes prüfen; ein echter Versand (SMTP, Zustellung,
 * Spamfilter) wird dadurch NICHT geprüft.
 */
defined( 'ABSPATH' ) || exit;

add_filter(
	'pre_wp_mail',
	static function ( $short, $atts ) {
		if ( null !== $short ) {
			return $short;
		}
		$mails   = get_option( 'e2e_mails', array() );
		$mails   = is_array( $mails ) ? $mails : array();
		$mails[] = array(
			'to'      => is_array( $atts['to'] ?? null ) ? implode( ',', $atts['to'] ) : (string) ( $atts['to'] ?? '' ),
			'subject' => (string) ( $atts['subject'] ?? '' ),
			'message' => (string) ( $atts['message'] ?? '' ),
			'headers' => $atts['headers'] ?? array(),
			'at'      => gmdate( 'Y-m-d H:i:s' ),
		);
		update_option( 'e2e_mails', array_slice( $mails, -200 ), false );
		return true;
	},
	10,
	2
);
