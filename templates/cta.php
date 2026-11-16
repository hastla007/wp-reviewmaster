<?php
/**
 * Neutral review call-to-action (RC-054): a plain link to the official profile.
 * No star preselection, no gating.
 *
 * Override: copy to {theme}/review-captain/cta.php.
 *
 * @package ReviewCaptain
 *
 * @var array<string, mixed> $rc CTA data: platform, url, text, new_tab, rel.
 */

defined( 'ABSPATH' ) || exit;
?>
<p class="rc-cta rc-cta--<?php echo esc_attr( $rc['platform'] ); ?>">
	<a class="rc-cta__button" href="<?php echo esc_url( $rc['url'] ); ?>" rel="<?php echo esc_attr( $rc['rel'] ); ?>"<?php echo $rc['new_tab'] ? ' target="_blank"' : ''; ?>><?php echo esc_html( $rc['text'] ); ?><?php if ( $rc['new_tab'] ) : ?><span class="rc-visually-hidden"> <?php esc_html_e( '(öffnet in neuem Tab)', 'review-captain' ); ?></span><?php endif; ?></a>
</p>
