<?php
/**
 * Dashboard with KPIs per source (never a cross-platform average) and setup checklist (UX-003, UX-008).
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Admin;

use ReviewCaptain\Frontend\Renderer;
use ReviewCaptain\Jobs\Queue;
use ReviewCaptain\Reviews\ReviewRepository;
use ReviewCaptain\Reviews\ReviewService;

defined( 'ABSPATH' ) || exit;

final class DashboardPage {

	public static function render(): void {
		$counts  = ReviewRepository::status_counts();
		$summary = ReviewRepository::summary_by_provider();
		$recent  = ReviewRepository::count( array( 'date_from' => gmdate( 'Y-m-d', time() - 30 * DAY_IN_SECONDS ) ) );
		$jobs    = Queue::stats();

		echo '<div class="wrap"><h1>' . esc_html__( 'Review Captain', 'review-captain' ) . '</h1>';
		Menu::notices();
		SetupWizard::checklist_notice();

		echo '<div class="rc-kpis">';
		self::kpi( __( 'Visible reviews', 'review-captain' ), number_format_i18n( $counts['approved'] ) );
		self::kpi( __( 'Awaiting moderation', 'review-captain' ), number_format_i18n( $counts['pending'] ), admin_url( 'admin.php?page=review-captain-reviews&status=pending' ) );
		self::kpi( __( 'New in 30 days', 'review-captain' ), number_format_i18n( $recent ) );
		$per_source = array();
		foreach ( $summary as $key => $row ) {
			$per_source[] = ReviewService::provider_label( explode( ':', $key )[0] ) . ' ' . ( null !== $row['average'] ? number_format_i18n( $row['average'], 1 ) . '/' . $row['scale_max'] : '–' );
		}
		self::kpi( __( 'Average per source', 'review-captain' ), $per_source ? implode( ' · ', $per_source ) : '–' );
		echo '</div>';

		if ( $jobs['delayed'] ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Background tasks are running late. WordPress only runs scheduled tasks when someone visits the site.', 'review-captain' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=review-captain-diagnostics' ) ) . '">' . esc_html__( 'Details', 'review-captain' ) . '</a></p></div>';
		}

		echo '<div class="rc-columns"><div class="rc-main postbox"><div class="inside"><h2>' . esc_html__( 'Latest reviews', 'review-captain' ) . '</h2>';
		$latest = ReviewRepository::query( array(), 5 );
		if ( ! $latest ) {
			echo '<div class="rc-empty"><h3>' . esc_html__( 'No reviews yet', 'review-captain' ) . '</h3><p>' . esc_html__( 'Collect your own reviews with a form or add existing reviews from other platforms.', 'review-captain' ) . '</p></div>';
		} else {
			echo '<table class="widefat striped"><tbody>';
			foreach ( $latest as $r ) {
				echo '<tr><td>' . esc_html( ReviewService::provider_label( (string) $r['provider'] ) ) . '</td><td>' . ( null !== $r['rating_value'] ? Renderer::stars( (float) $r['rating_value'], (int) $r['rating_min'], (int) $r['rating_max'] ) : '' ) . '</td><td><a href="' . esc_url( admin_url( 'admin.php?page=review-captain-reviews&action=view&id=' . (int) $r['id'] ) ) . '">' . esc_html( wp_trim_words( (string) ( $r['title'] ? $r['title'] : $r['content'] ), 10, '…' ) ) . '</a></td><td>' . esc_html( $r['status'] ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- stars() escapes.
			}
			echo '</tbody></table>';
		}
		echo '</div></div><div class="rc-side postbox"><div class="inside"><h2>' . esc_html__( 'Quick actions', 'review-captain' ) . '</h2><ul>';
		if ( current_user_can( 'rc_manage_reviews' ) ) {
			echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=review-captain-reviews&action=add' ) ) . '">' . esc_html__( 'Add review from another platform', 'review-captain' ) . '</a></li>';
		}
		if ( current_user_can( 'rc_manage_widgets' ) ) {
			echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=review-captain-widgets&action=add' ) ) . '">' . esc_html__( 'Create widget', 'review-captain' ) . '</a></li>';
		}
		if ( current_user_can( 'rc_manage_reviews' ) ) {
			echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=review-captain-sources' ) ) . '">' . esc_html__( 'Add profile link', 'review-captain' ) . '</a></li>';
		}
		echo '</ul>';
		do_action( 'review_captain_dashboard_side' );
		echo '</div></div></div></div>';
	}

	private static function kpi( string $label, string $value, string $link = '' ): void {
		echo '<div class="rc-kpi"><span class="rc-kpi__label">' . esc_html( $label ) . '</span><span class="rc-kpi__value">' . ( $link ? '<a href="' . esc_url( $link ) . '">' . esc_html( $value ) . '</a>' : esc_html( $value ) ) . '</span></div>';
	}
}
