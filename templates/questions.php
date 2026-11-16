<?php
/**
 * Product questions and answers (RC-122). No structured data is printed here on purpose.
 *
 * Override: copy to {theme}/review-captain/questions.php. Keep the nonce, hidden fields and the
 * honeypot unchanged.
 *
 * @package ReviewCaptain
 *
 * @var array<string, mixed> $rc Data (see QuestionHandler::shortcode()).
 */

defined( 'ABSPATH' ) || exit;

$rc_id = (string) $rc['id'];
?>
<section class="rc-qa" id="<?php echo esc_attr( $rc_id ); ?>" aria-labelledby="<?php echo esc_attr( $rc_id . '-title' ); ?>">
	<h2 class="rc-qa__title" id="<?php echo esc_attr( $rc_id . '-title' ); ?>"><?php esc_html_e( 'Fragen und Antworten', 'review-captain' ); ?></h2>
	<?php if ( '' !== $rc['success'] ) : ?>
		<p class="rc-form__notice rc-form__notice--success" role="status"><?php echo esc_html( $rc['success'] ); ?></p>
	<?php endif; ?>
	<?php if ( ! empty( $rc['items'] ) ) : ?>
		<ul class="rc-qa__list">
			<?php foreach ( $rc['items'] as $rc_item ) : ?>
				<li class="rc-qa__item">
					<p class="rc-qa__question"><span class="rc-qa__label"><?php esc_html_e( 'Frage:', 'review-captain' ); ?></span> <?php echo esc_html( $rc_item['question'] ); ?></p>
					<p class="rc-qa__author"><?php echo esc_html( $rc_item['author'] ); ?></p>
					<?php if ( '' !== $rc_item['answer'] ) : ?>
						<p class="rc-qa__answer"><span class="rc-qa__label"><?php esc_html_e( 'Antwort des Anbieters:', 'review-captain' ); ?></span> <?php echo esc_html( $rc_item['answer'] ); ?></p>
					<?php else : ?>
						<p class="rc-qa__answer rc-qa__answer--open"><?php esc_html_e( 'Noch nicht beantwortet.', 'review-captain' ); ?></p>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php else : ?>
		<p class="rc-empty"><?php esc_html_e( 'Zu diesem Produkt wurden noch keine Fragen gestellt.', 'review-captain' ); ?></p>
	<?php endif; ?>

	<?php if ( ! empty( $rc['errors'] ) ) : ?>
		<div class="rc-form__error rc-form__errors" role="alert">
			<ul>
				<?php foreach ( $rc['errors'] as $rc_message ) : ?>
					<li><?php echo esc_html( $rc_message ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>
	<form class="rc-form__form rc-qa__form" method="post" action="<?php echo esc_url( $rc['action'] ); ?>" novalidate>
		<?php wp_nonce_field( $rc['nonce_action'], $rc['nonce_name'], false ); ?>
		<?php foreach ( $rc['hidden'] as $rc_name => $rc_value ) : ?>
			<input type="hidden" name="<?php echo esc_attr( $rc_name ); ?>" value="<?php echo esc_attr( $rc_value ); ?>" />
		<?php endforeach; ?>
		<p class="rc-form__field">
			<label for="<?php echo esc_attr( $rc_id . '-question' ); ?>"><?php esc_html_e( 'Ihre Frage zum Produkt', 'review-captain' ); ?> <span class="rc-form__required"><?php esc_html_e( '(Pflichtfeld)', 'review-captain' ); ?></span></label>
			<textarea id="<?php echo esc_attr( $rc_id . '-question' ); ?>" name="rc_question" rows="3" required maxlength="<?php echo esc_attr( (string) $rc['max_length'] ); ?>"></textarea>
		</p>
		<p class="rc-form__field">
			<label for="<?php echo esc_attr( $rc_id . '-name' ); ?>"><?php esc_html_e( 'Name (wird veröffentlicht)', 'review-captain' ); ?> <span class="rc-form__required"><?php esc_html_e( '(Pflichtfeld)', 'review-captain' ); ?></span></label>
			<input type="text" id="<?php echo esc_attr( $rc_id . '-name' ); ?>" name="rc_name" required maxlength="100" autocomplete="name" />
		</p>
		<p class="rc-form__field">
			<label for="<?php echo esc_attr( $rc_id . '-email' ); ?>"><?php esc_html_e( 'E-Mail-Adresse (optional, wird nie veröffentlicht)', 'review-captain' ); ?></label>
			<input type="email" id="<?php echo esc_attr( $rc_id . '-email' ); ?>" name="rc_email" maxlength="191" autocomplete="email" />
		</p>
		<?php if ( $rc['honeypot'] ) : ?>
			<p class="rc-form__hp" aria-hidden="true">
				<label for="<?php echo esc_attr( $rc_id . '-hp' ); ?>"><?php esc_html_e( 'Dieses Feld bitte leer lassen', 'review-captain' ); ?></label>
				<input type="text" id="<?php echo esc_attr( $rc_id . '-hp' ); ?>" name="<?php echo esc_attr( $rc['honeypot_name'] ); ?>" value="" tabindex="-1" autocomplete="off" />
			</p>
		<?php endif; ?>
		<p class="rc-form__field rc-form__field--consent">
			<label>
				<input type="checkbox" name="rc_consent" value="1"<?php echo $rc['require_consent'] ? ' required' : ''; ?> />
				<?php esc_html_e( 'Ich willige ein, dass meine Frage mit meinem Namen auf dieser Website veröffentlicht wird. Die Einwilligung kann ich jederzeit widerrufen.', 'review-captain' ); ?>
			</label>
		</p>
		<p class="rc-form__actions">
			<button type="submit" class="rc-form__submit"><?php esc_html_e( 'Frage senden', 'review-captain' ); ?></button>
		</p>
	</form>
</section>
