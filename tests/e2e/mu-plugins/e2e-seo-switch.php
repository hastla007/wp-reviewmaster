<?php
/**
 * Plugin Name: E2E SEO switch (test instance only)
 * Description: SEO-016 scenarios: simulate a foreign schema plugin or an old Yoast by constant, and toggle the
 * eligibility filter. Driven by the option e2e_seo_switch (array). Never ship.
 */
$e2e_seo = get_option( 'e2e_seo_switch', array() );
if ( ! empty( $e2e_seo['define'] ) && is_array( $e2e_seo['define'] ) ) {
	foreach ( $e2e_seo['define'] as $const => $value ) {
		if ( ! defined( $const ) ) {
			define( $const, $value );
		}
	}
}
if ( isset( $e2e_seo['filter'] ) ) {
	add_filter( 'review_captain_schema_eligible', static fn() => (bool) $e2e_seo['filter'], 10, 0 );
}
