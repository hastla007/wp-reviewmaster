<?php
/**
 * Verification notice "So prüfen wir Bewertungen" (SET-RC-01) as a disclosure without JavaScript.
 *
 * Override: copy to {theme}/review-captain/verification-notice.php.
 *
 * @package ReviewCaptain
 *
 * @var array<string, mixed> $rc Data: title (plain text), html (escaped paragraphs, only p/br).
 */

defined( 'ABSPATH' ) || exit;
?>
<details class="rc-verification">
	<summary class="rc-verification__toggle"><?php echo esc_html( $rc['title'] ); ?></summary>
	<div class="rc-verification__text">
	<?php
	echo wp_kses(
		(string) $rc['html'],
		array(
			'p'  => array(),
			'br' => array(),
		)
	);
	?>
	</div>
</details>
