<?php
/**
 * Integrations (read-only in Core): Captain Suite detection and custom connector status (CON-008/CON-010 basis).
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Admin;

use ReviewCaptain\Connectors\Registry;

defined( 'ABSPATH' ) || exit;

final class IntegrationsPage {

	public static function render(): void {
		echo '<div class="wrap"><h1>' . esc_html__( 'Integrations', 'review-captain' ) . '</h1>';
		Menu::notices();

		echo '<h2>' . esc_html__( 'Captain Suite', 'review-captain' ) . '</h2><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Plugin', 'review-captain' ) . '</th><th>' . esc_html__( 'Status', 'review-captain' ) . '</th></tr></thead><tbody>';
		$suite = array(
			'WooCommerce'       => defined( 'WC_VERSION' ) ? WC_VERSION : null,
			'Voucher Captain'   => defined( 'VOUCHER_CAPTAIN_VERSION' ) ? constant( 'VOUCHER_CAPTAIN_VERSION' ) : null,
			'Pass Captain'      => defined( 'PASS_CAPTAIN_VERSION' ) ? constant( 'PASS_CAPTAIN_VERSION' ) : null,
			'Affiliate Captain' => defined( 'AFFILIATE_CAPTAIN_VERSION' ) ? constant( 'AFFILIATE_CAPTAIN_VERSION' ) : null,
			'Form Captain'      => defined( 'FORM_CAPTAIN_VERSION' ) ? constant( 'FORM_CAPTAIN_VERSION' ) : null,
			'Workflow Captain'  => defined( 'WORKFLOW_CAPTAIN_VERSION' ) ? constant( 'WORKFLOW_CAPTAIN_VERSION' ) : null,
		);
		foreach ( $suite as $name => $version ) {
			/* translators: %s: version */
			$status = $version ? sprintf( __( 'Installed (version %s)', 'review-captain' ), $version ) : __( 'Not active', 'review-captain' );
			echo '<tr><td>' . esc_html( $name ) . '</td><td>' . esc_html( $status ) . '</td></tr>';
		}
		echo '</tbody></table>';
		if ( ! has_action( 'review_captain_integrations_page' ) ) {
			echo '<p class="description">' . esc_html__( 'Automatic review invitations after purchases, voucher redemptions or event check-ins are available with Review Captain Pro.', 'review-captain' ) . '</p>';
		}
		do_action( 'review_captain_integrations_page' );

		echo '<h2>' . esc_html__( 'Custom connectors', 'review-captain' ) . '</h2>';
		$entries = Registry::instance()->all();
		if ( ! $entries ) {
			echo '<p>' . esc_html__( 'No custom connectors are installed. Developers can register connectors with the public contract wp_review_captain_register_connectors_v1.', 'review-captain' ) . '</p>';
		} else {
			echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Connector', 'review-captain' ) . '</th><th>' . esc_html__( 'Developer', 'review-captain' ) . '</th><th>' . esc_html__( 'Version', 'review-captain' ) . '</th><th>' . esc_html__( 'Capabilities', 'review-captain' ) . '</th><th>' . esc_html__( 'Status', 'review-captain' ) . '</th></tr></thead><tbody>';
			foreach ( $entries as $e ) {
				$m = $e['metadata'];
				echo '<tr><td>' . esc_html( $m['label'] ) . '<br><code>' . esc_html( $m['key'] ) . '</code></td><td>' . esc_html( $m['developer'] ) . '</td><td>' . esc_html( $m['version'] ) . ' / ' . esc_html__( 'contract', 'review-captain' ) . ' ' . esc_html( $m['contract'] ) . '</td><td>' . esc_html( implode( ', ', $e['capabilities'] ) ) . '</td><td><span class="rc-pill rc-pill--' . esc_attr( $e['status'] ) . '">' . esc_html( $e['status'] ) . '</span> <code>' . esc_html( $e['code'] ) . '</code>';
				foreach ( $e['notices'] as $n ) {
					echo '<br><small>' . esc_html( $n ) . '</small>';
				}
				echo '</td></tr>';
			}
			echo '</tbody></table>';
		}
		foreach ( Registry::instance()->rejected() as $r ) {
			/* translators: 1: connector key, 2: reason */
			echo '<div class="notice notice-warning inline"><p>' . esc_html( sprintf( __( 'Connector “%1$s” was rejected: %2$s', 'review-captain' ), $r['key'], $r['reason'] ) ) . '</p></div>';
		}
		echo '<p class="description">' . esc_html__( 'Connectors run as PHP code inside your WordPress installation. Install only connectors from developers you trust.', 'review-captain' ) . '</p>';
		echo '</div>';
	}
}
