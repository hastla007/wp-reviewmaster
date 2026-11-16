<?php
/**
 * Reviews widget container (grid or list).
 *
 * Override: copy to {theme}/review-captain/reviews-grid.php.
 *
 * @package ReviewCaptain
 *
 * @var array<string, mixed> $rc Widget data: id, classes, layout, columns, cards (rendered card HTML),
 *                               summary (list of {platform, text}), empty_text, pagination {prev, next}.
 */

defined( 'ABSPATH' ) || exit;
?>
<div id="<?php echo esc_attr( $rc['id'] ); ?>" class="<?php echo esc_attr( $rc['classes'] ); ?>" style="<?php echo esc_attr( '--rc-columns: ' . (int) $rc['columns'] ); ?>">
	<?php if ( ! empty( $rc['summary'] ) ) : ?>
		<ul class="rc-summary" aria-label="<?php esc_attr_e( 'Zusammenfassung je Plattform', 'review-captain' ); ?>">
			<?php foreach ( $rc['summary'] as $rc_row ) : ?>
				<li class="rc-summary__item rc-source--<?php echo esc_attr( $rc_row['platform'] ); ?>"><?php echo esc_html( $rc_row['text'] ); ?></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
	<?php if ( ! empty( $rc['cards'] ) ) : ?>
		<div class="rc-widget__items">
			<?php
			foreach ( $rc['cards'] as $rc_card ) {
				echo $rc_card; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in review-card.php.
			}
			?>
		</div>
	<?php else : ?>
		<p class="rc-empty"><?php echo esc_html( $rc['empty_text'] ); ?></p>
	<?php endif; ?>
	<?php if ( ! empty( $rc['pagination']['prev'] ) || ! empty( $rc['pagination']['next'] ) ) : ?>
		<nav class="rc-pagination" aria-label="<?php esc_attr_e( 'Weitere Bewertungen', 'review-captain' ); ?>">
			<?php if ( ! empty( $rc['pagination']['prev'] ) ) : ?>
				<a class="rc-pagination__prev" href="<?php echo esc_url( $rc['pagination']['prev'] ); ?>"><?php esc_html_e( 'Vorherige Seite', 'review-captain' ); ?></a>
			<?php endif; ?>
			<?php if ( ! empty( $rc['pagination']['next'] ) ) : ?>
				<a class="rc-pagination__next" href="<?php echo esc_url( $rc['pagination']['next'] ); ?>"><?php esc_html_e( 'Nächste Seite', 'review-captain' ); ?></a>
			<?php endif; ?>
		</nav>
	<?php endif; ?>
</div>
