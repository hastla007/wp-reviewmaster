<?php
/**
 * Local review submission form (RC-039). Works without JavaScript.
 *
 * Override: copy to {theme}/review-captain/review-form.php. Keep the nonce, the hidden fields
 * and the honeypot unchanged – the submission handler requires them.
 *
 * @package ReviewCaptain
 *
 * @var array<string, mixed> $rc Form data (see Renderer::render_form()).
 */

defined( 'ABSPATH' ) || exit;

if ( ! empty( $rc['disabled'] ) ) :
	?>
	<div class="rc-form rc-form--disabled" id="<?php echo esc_attr( $rc['id'] ); ?>">
		<p class="rc-form__notice"><?php echo esc_html( $rc['message'] ); ?></p>
	</div>
	<?php
	return;
endif;

$rc_id      = (string) $rc['id'];
$rc_errors  = (array) $rc['errors'];
$rc_values  = (array) $rc['values'];
$rc_field   = array(
	'rating'     => 'rating',
	'title_long' => 'title',
	'body_short' => 'body',
	'body_long'  => 'body',
	'name'       => 'name',
	'email'      => 'email',
	'consent'    => 'consent',
);
$rc_err_for = static function ( string $field ) use ( $rc_errors, $rc_field ): string {
	foreach ( $rc_errors as $code => $message ) {
		if ( ( $rc_field[ $code ] ?? '' ) === $field ) {
			return (string) $message;
		}
	}
	return '';
};
$rc_described = static function ( string $field, string $extra = '' ) use ( $rc_id, $rc_err_for ): string {
	$ids = array_filter( array( $extra, '' !== $rc_err_for( $field ) ? $rc_id . '-' . $field . '-error' : '' ) );
	return $ids ? ' aria-describedby="' . esc_attr( implode( ' ', $ids ) ) . '" aria-invalid="' . ( '' !== $rc_err_for( $field ) ? 'true' : 'false' ) . '"' : '';
};
?>
<div class="rc-form" id="<?php echo esc_attr( $rc_id ); ?>">
	<?php if ( '' !== $rc['success'] ) : ?>
		<p class="rc-form__notice rc-form__notice--success" role="status"><?php echo esc_html( $rc['success'] ); ?></p>
	<?php endif; ?>
	<?php if ( $rc_errors ) : ?>
		<div class="rc-form__error rc-form__errors" role="alert" tabindex="-1">
			<p><?php esc_html_e( 'Bitte prüfen Sie Ihre Angaben:', 'review-captain' ); ?></p>
			<ul>
				<?php foreach ( $rc_errors as $rc_message ) : ?>
					<li><?php echo esc_html( $rc_message ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>
	<form class="rc-form__form" method="post" action="<?php echo esc_url( $rc['action'] ); ?>"<?php echo ! empty( $rc['photos'] ) ? ' enctype="multipart/form-data"' : ''; ?> novalidate>
		<?php wp_nonce_field( $rc['nonce_action'], $rc['nonce_name'], false ); ?>
		<?php foreach ( $rc['hidden'] as $rc_name => $rc_value ) : ?>
			<input type="hidden" name="<?php echo esc_attr( $rc_name ); ?>" value="<?php echo esc_attr( $rc_value ); ?>" />
		<?php endforeach; ?>

		<?php if ( empty( $rc['items'] ) ) : ?>
		<fieldset class="rc-form__field rc-form__field--rating"<?php echo $rc_described( 'rating' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in closure. ?>>
			<legend><?php esc_html_e( 'Ihre Bewertung', 'review-captain' ); ?> <span class="rc-form__required"><?php esc_html_e( '(Pflichtfeld)', 'review-captain' ); ?></span></legend>
			<?php for ( $rc_i = 5; $rc_i >= 1; $rc_i-- ) : ?>
				<label class="rc-form__radio">
					<input type="radio" name="rc_rating" value="<?php echo esc_attr( (string) $rc_i ); ?>" required<?php checked( $rc_values['rating'], (string) $rc_i ); ?> />
					<?php
					/* translators: %d: number of stars */
					echo esc_html( sprintf( _n( '%d Stern', '%d Sterne', $rc_i, 'review-captain' ), $rc_i ) );
					?>
				</label>
			<?php endfor; ?>
			<?php if ( '' !== $rc_err_for( 'rating' ) ) : ?>
				<p class="rc-form__error" id="<?php echo esc_attr( $rc_id . '-rating-error' ); ?>"><?php echo esc_html( $rc_err_for( 'rating' ) ); ?></p>
			<?php endif; ?>
		</fieldset>
		<?php else : ?>
			<?php foreach ( $rc['items'] as $rc_item ) : ?>
				<?php $rc_item_key = 'rc_items[' . $rc_item['id'] . ']'; ?>
				<fieldset class="rc-form__field rc-form__field--item">
					<legend><?php echo esc_html( $rc_item['label'] ); ?></legend>
					<div class="rc-form__field--rating" role="radiogroup" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: product name */ __( 'Bewertung für %s', 'review-captain' ), $rc_item['label'] ) ); ?>">
						<?php for ( $rc_i = 5; $rc_i >= 1; $rc_i-- ) : ?>
							<label class="rc-form__radio">
								<input type="radio" name="<?php echo esc_attr( $rc_item_key . '[rating]' ); ?>" value="<?php echo esc_attr( (string) $rc_i ); ?>" />
								<?php
								/* translators: %d: number of stars */
								echo esc_html( sprintf( _n( '%d Stern', '%d Sterne', $rc_i, 'review-captain' ), $rc_i ) );
								?>
							</label>
						<?php endfor; ?>
					</div>
					<label for="<?php echo esc_attr( $rc_id . '-item-' . $rc_item['id'] ); ?>"><?php esc_html_e( 'Ihre Erfahrung mit diesem Produkt', 'review-captain' ); ?></label>
					<textarea id="<?php echo esc_attr( $rc_id . '-item-' . $rc_item['id'] ); ?>" name="<?php echo esc_attr( $rc_item_key . '[body]' ); ?>" rows="4" maxlength="<?php echo esc_attr( (string) $rc['max_length'] ); ?>"></textarea>
				</fieldset>
			<?php endforeach; ?>
			<p class="rc-form__hint"><?php esc_html_e( 'Sie können auch nur einzelne Produkte bewerten. Je Produkt ist eine Bewertung möglich.', 'review-captain' ); ?></p>
		<?php endif; ?>

		<?php if ( $rc['allow_title'] ) : ?>
			<p class="rc-form__field">
				<label for="<?php echo esc_attr( $rc_id . '-title' ); ?>"><?php esc_html_e( 'Titel (optional)', 'review-captain' ); ?></label>
				<input type="text" id="<?php echo esc_attr( $rc_id . '-title' ); ?>" name="rc_title" maxlength="150" value="<?php echo esc_attr( $rc_values['title'] ); ?>"<?php echo $rc_described( 'title' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
				<?php if ( '' !== $rc_err_for( 'title' ) ) : ?>
					<span class="rc-form__error" id="<?php echo esc_attr( $rc_id . '-title-error' ); ?>"><?php echo esc_html( $rc_err_for( 'title' ) ); ?></span>
				<?php endif; ?>
			</p>
		<?php endif; ?>

		<?php if ( empty( $rc['items'] ) ) : ?>
		<p class="rc-form__field">
			<label for="<?php echo esc_attr( $rc_id . '-body' ); ?>"><?php esc_html_e( 'Ihre Erfahrung', 'review-captain' ); ?> <span class="rc-form__required"><?php esc_html_e( '(Pflichtfeld)', 'review-captain' ); ?></span></label>
			<textarea id="<?php echo esc_attr( $rc_id . '-body' ); ?>" name="rc_body" rows="6" required minlength="<?php echo esc_attr( (string) $rc['min_length'] ); ?>" maxlength="<?php echo esc_attr( (string) $rc['max_length'] ); ?>"<?php echo $rc_described( 'body', $rc_id . '-body-hint' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_textarea( $rc_values['body'] ); ?></textarea>
			<span class="rc-form__hint" id="<?php echo esc_attr( $rc_id . '-body-hint' ); ?>">
				<?php
				/* translators: 1: minimum length, 2: maximum length */
				echo esc_html( sprintf( __( 'Zwischen %1$d und %2$d Zeichen.', 'review-captain' ), (int) $rc['min_length'], (int) $rc['max_length'] ) );
				?>
			</span>
			<?php if ( '' !== $rc_err_for( 'body' ) ) : ?>
				<span class="rc-form__error" id="<?php echo esc_attr( $rc_id . '-body-error' ); ?>"><?php echo esc_html( $rc_err_for( 'body' ) ); ?></span>
			<?php endif; ?>
		</p>
		<?php endif; ?>

		<?php if ( ! empty( $rc['photos'] ) ) : ?>
			<p class="rc-form__field rc-form__field--photo">
				<label for="<?php echo esc_attr( $rc_id . '-photo' ); ?>"><?php esc_html_e( 'Foto (optional)', 'review-captain' ); ?></label>
				<input type="file" id="<?php echo esc_attr( $rc_id . '-photo' ); ?>" name="rc_photo" accept="image/jpeg,image/png,image/webp" aria-describedby="<?php echo esc_attr( $rc_id . '-photo-hint' ); ?>" />
				<span class="rc-form__hint" id="<?php echo esc_attr( $rc_id . '-photo-hint' ); ?>">
					<?php
					/* translators: %d: maximum size in KB */
					echo esc_html( sprintf( __( 'JPG, PNG oder WebP, höchstens %d KB. Das Foto wird erst nach Prüfung angezeigt; Bildmetadaten (z. B. Standort) werden entfernt.', 'review-captain' ), (int) $rc['photo_max_kb'] ) );
					?>
				</span>
			</p>
		<?php endif; ?>

		<p class="rc-form__field">
			<label for="<?php echo esc_attr( $rc_id . '-name' ); ?>"><?php esc_html_e( 'Name (wird veröffentlicht)', 'review-captain' ); ?> <span class="rc-form__required"><?php esc_html_e( '(Pflichtfeld)', 'review-captain' ); ?></span></label>
			<input type="text" id="<?php echo esc_attr( $rc_id . '-name' ); ?>" name="rc_name" required maxlength="100" autocomplete="name" value="<?php echo esc_attr( $rc_values['name'] ); ?>"<?php echo $rc_described( 'name' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
			<?php if ( '' !== $rc_err_for( 'name' ) ) : ?>
				<span class="rc-form__error" id="<?php echo esc_attr( $rc_id . '-name-error' ); ?>"><?php echo esc_html( $rc_err_for( 'name' ) ); ?></span>
			<?php endif; ?>
		</p>

		<p class="rc-form__field">
			<label for="<?php echo esc_attr( $rc_id . '-email' ); ?>"><?php esc_html_e( 'E-Mail-Adresse', 'review-captain' ); ?> <span class="rc-form__required"><?php esc_html_e( '(Pflichtfeld)', 'review-captain' ); ?></span></label>
			<input type="email" id="<?php echo esc_attr( $rc_id . '-email' ); ?>" name="rc_email" required maxlength="191" autocomplete="email" value="<?php echo esc_attr( $rc_values['email'] ); ?>"<?php echo $rc_described( 'email', $rc_id . '-email-hint' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
			<span class="rc-form__hint" id="<?php echo esc_attr( $rc_id . '-email-hint' ); ?>"><?php esc_html_e( 'Wird nie veröffentlicht. Wir nutzen sie nur für Rückfragen und Datenschutzauskünfte.', 'review-captain' ); ?></span>
			<?php if ( '' !== $rc_err_for( 'email' ) ) : ?>
				<span class="rc-form__error" id="<?php echo esc_attr( $rc_id . '-email-error' ); ?>"><?php echo esc_html( $rc_err_for( 'email' ) ); ?></span>
			<?php endif; ?>
		</p>

		<?php if ( $rc['honeypot'] ) : ?>
			<p class="rc-form__hp" aria-hidden="true">
				<label for="<?php echo esc_attr( $rc_id . '-hp' ); ?>"><?php esc_html_e( 'Dieses Feld bitte leer lassen', 'review-captain' ); ?></label>
				<input type="text" id="<?php echo esc_attr( $rc_id . '-hp' ); ?>" name="<?php echo esc_attr( $rc['honeypot_name'] ); ?>" value="" tabindex="-1" autocomplete="off" />
			</p>
		<?php endif; ?>

		<?php if ( '' !== trim( (string) $rc['privacy_notice'] ) ) : ?>
			<div class="rc-form__privacy"><?php echo wp_kses( wpautop( esc_html( (string) $rc['privacy_notice'] ) ), array( 'p' => array(), 'br' => array() ) ); ?></div>
		<?php endif; ?>

		<p class="rc-form__field rc-form__field--consent">
			<label>
				<input type="checkbox" name="rc_consent" value="1"<?php echo $rc['require_consent'] ? ' required' : ''; ?><?php echo $rc_described( 'consent' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
				<?php esc_html_e( 'Ich willige ein, dass meine Bewertung mit meinem Namen auf dieser Website veröffentlicht wird. Meine E-Mail-Adresse wird nicht veröffentlicht. Die Einwilligung kann ich jederzeit widerrufen.', 'review-captain' ); ?>
				<?php if ( $rc['require_consent'] ) : ?>
					<span class="rc-form__required"><?php esc_html_e( '(Pflichtfeld)', 'review-captain' ); ?></span>
				<?php endif; ?>
			</label>
			<?php if ( '' !== $rc_err_for( 'consent' ) ) : ?>
				<span class="rc-form__error" id="<?php echo esc_attr( $rc_id . '-consent-error' ); ?>"><?php echo esc_html( $rc_err_for( 'consent' ) ); ?></span>
			<?php endif; ?>
		</p>

		<p class="rc-form__actions">
			<button type="submit" class="rc-form__submit"><?php esc_html_e( 'Bewertung senden', 'review-captain' ); ?></button>
		</p>
	</form>
</div>
