<?php
/**
 * Review card.
 *
 * Override: copy to {theme}/review-captain/review-card.php. All values in $rc are raw and
 * must be escaped here. Keep the source label and original link for external reviews (SET-014).
 *
 * @package ReviewCaptain
 *
 * @var array<string, mixed> $rc Card data (see Renderer::card_data()).
 */

defined( 'ABSPATH' ) || exit;

$rc_star_path = 'M12 2.6l2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.5l-5.9 3.1 1.2-6.5L2.5 9.5l6.6-.9z';
?>
<article class="<?php echo esc_attr( $rc['classes'] ); ?>" data-rc-review="<?php echo esc_attr( $rc['uuid'] ); ?>">
	<header class="rc-card__header">
		<?php if ( ! empty( $rc['rating'] ) ) : ?>
			<div class="rc-rating" role="img" aria-label="<?php echo esc_attr( $rc['rating']['label'] ); ?>">
				<?php if ( ! empty( $rc['rating']['stars'] ) ) : ?>
					<span class="rc-stars" aria-hidden="true">
						<?php foreach ( $rc['rating']['stars'] as $rc_star ) : ?>
							<svg class="rc-star rc-star--<?php echo esc_attr( $rc_star ); ?>" viewBox="0 0 24 24" width="16" height="16" focusable="false"><path class="rc-star__outline" d="<?php echo esc_attr( $rc_star_path ); ?>"/><path class="rc-star__fill" d="<?php echo esc_attr( $rc_star_path ); ?>"/></svg>
						<?php endforeach; ?>
					</span>
				<?php endif; ?>
				<?php if ( ! isset( $rc['rating']['show_value'] ) || $rc['rating']['show_value'] ) : ?>
					<span class="rc-rating__value" aria-hidden="true"><?php echo esc_html( $rc['rating']['value'] ); ?></span>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<?php foreach ( $rc['badges'] as $rc_badge ) : ?>
			<span class="<?php echo esc_attr( $rc_badge['class'] ); ?>"><?php echo esc_html( $rc_badge['text'] ); ?></span>
		<?php endforeach; ?>
		<?php if ( '' !== $rc['title'] ) : ?>
			<?php $rc_heading = in_array( $rc['heading'] ?? 'h3', array( 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ? $rc['heading'] : 'h3'; ?>
			<<?php echo esc_attr( $rc_heading ); ?> class="rc-card__title"><?php echo esc_html( $rc['title'] ); ?></<?php echo esc_attr( $rc_heading ); ?>>
		<?php endif; ?>
	</header>
	<?php if ( '' !== $rc['body_html'] ) : ?>
		<div class="rc-card__body"><?php echo wp_kses( $rc['body_html'], $rc['allowed'] ); ?></div>
	<?php endif; ?>
	<?php if ( ! empty( $rc['photo'] ) ) : ?>
		<figure class="rc-card__photo">
			<img src="<?php echo esc_url( $rc['photo']['url'] ); ?>" alt="<?php echo esc_attr( $rc['photo']['alt'] ); ?>" loading="lazy" decoding="async"<?php echo $rc['photo']['width'] > 0 ? ' width="' . esc_attr( (string) $rc['photo']['width'] ) . '" height="' . esc_attr( (string) $rc['photo']['height'] ) . '"' : ''; ?> />
		</figure>
	<?php endif; ?>
	<footer class="rc-card__footer rc-card__meta">
		<?php if ( '' !== $rc['author'] ) : ?>
			<?php if ( '' !== $rc['avatar'] ) : ?>
				<span class="rc-avatar" aria-hidden="true"><?php echo esc_html( $rc['avatar'] ); ?></span>
			<?php endif; ?>
			<span class="rc-author"><?php echo esc_html( $rc['author'] ); ?></span>
		<?php endif; ?>
		<?php if ( ! empty( $rc['date'] ) ) : ?>
			<time class="rc-date" datetime="<?php echo esc_attr( $rc['date']['iso'] ); ?>"><?php echo esc_html( $rc['date']['text'] ); ?></time>
		<?php endif; ?>
		<?php if ( ! empty( $rc['edited'] ) ) : ?>
			<span class="rc-edited">(<time datetime="<?php echo esc_attr( $rc['edited']['iso'] ); ?>"><?php echo esc_html( $rc['edited']['text'] ); ?></time>)</span>
		<?php endif; ?>
		<?php if ( ! empty( $rc['source'] ) ) : ?>
			<span class="rc-source rc-source--<?php echo esc_attr( $rc['source']['platform'] ); ?>"><?php echo esc_html( $rc['source']['label'] ); ?></span>
			<?php if ( '' !== $rc['source']['url'] ) : ?>
				<a class="rc-source__link" href="<?php echo esc_url( $rc['source']['url'] ); ?>" <?php echo $rc['source']['link_attrs']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attribute string from EmbedPolicy. ?>><?php echo esc_html( $rc['source']['link_text'] ); ?></a>
			<?php endif; ?>
		<?php endif; ?>
	</footer>
	<?php if ( ! empty( $rc['report'] ) ) : ?>
		<details class="rc-report">
			<summary class="rc-report__toggle"><?php esc_html_e( 'Melden', 'review-captain' ); ?></summary>
			<form class="rc-report__form" method="post" action="<?php echo esc_url( $rc['report']['action'] ); ?>">
				<?php wp_nonce_field( $rc['report']['nonce_action'], $rc['report']['nonce_name'], false ); ?>
				<?php foreach ( $rc['report']['hidden'] as $rc_name => $rc_value ) : ?>
					<input type="hidden" name="<?php echo esc_attr( $rc_name ); ?>" value="<?php echo esc_attr( $rc_value ); ?>" />
				<?php endforeach; ?>
				<label for="<?php echo esc_attr( 'rc-report-' . $rc['uuid'] ); ?>"><?php esc_html_e( 'Warum soll diese Bewertung geprüft werden?', 'review-captain' ); ?></label>
				<select id="<?php echo esc_attr( 'rc-report-' . $rc['uuid'] ); ?>" name="rc_reason" required>
					<?php foreach ( $rc['report']['reasons'] as $rc_key => $rc_label ) : ?>
						<option value="<?php echo esc_attr( $rc_key ); ?>"><?php echo esc_html( $rc_label ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php if ( ! empty( $rc['report']['pow'] ) ) : ?>
					<?php echo $rc['report']['pow']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in PowCaptcha::field() (SET-RC-11). ?>
				<?php endif; ?>
				<button type="submit" class="rc-report__submit"><?php esc_html_e( 'Meldung senden', 'review-captain' ); ?></button>
			</form>
		</details>
	<?php endif; ?>
</article>
