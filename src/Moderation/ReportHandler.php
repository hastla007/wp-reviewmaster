<?php
/**
 * Public "report this review" endpoint (RC-124).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Moderation;

use ReviewCaptain\Domain\ReviewStatus;
use ReviewCaptain\Plugin;
use ReviewCaptain\Security\PowCaptcha;
use ReviewCaptain\Security\RateLimiter;

/**
 * admin-post.php?action=review_captain_report (logged-in and anonymous).
 *
 * Requires `submissions.reports_enabled`. Protection: nonce, approved/public review only, reason
 * from the enabled part of a fixed list (`submissions.report_reasons`, SET-RC-26), rate limit per hashed address (and one report per review and address and
 * hour). When the number of open reports reaches `submissions.report_threshold` the review is set
 * to `flagged` (hidden until a moderator decides). Nothing about the reporter is stored.
 * Moderators are notified per accepted report unless `submissions.report_notify` is `none`.
 */
class ReportHandler {

	public const ACTION       = 'review_captain_report';
	public const NONCE_ACTION = 'review_captain_report';
	public const NONCE_FIELD  = '_rc_report_nonce';

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( private Plugin $plugin ) {}

	/**
	 * admin-post handler.
	 */
	public function handle(): void {
		$result = $this->process( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in process().
		wp_safe_redirect( $result['redirect'], 303 );
		exit;
	}

	/**
	 * Process a report.
	 *
	 * @param array<string, mixed> $post Unslashed POST.
	 * @return array{ok: bool, error: string, flagged: bool, redirect: string}
	 */
	public function process( array $post ): array {
		$settings = $this->plugin->settings();
		$return   = self::return_url( $post );
		$result   = static function ( bool $ok, string $error = '', bool $flagged = false ) use ( $return ): array {
			return array(
				'ok'       => $ok,
				'error'    => $error,
				'flagged'  => $flagged,
				// Fragment: back to the status message, not to the top of the page (SET-RC-P5).
				'redirect' => add_query_arg( 'rc_reported', $ok ? '1' : 'error', $return ) . '#rc-report-notice',
			);
		};

		if ( ! $settings->get( 'submissions', 'reports_enabled', false ) ) {
			return $result( false, 'disabled' );
		}
		if ( ! wp_verify_nonce( (string) ( $post[ self::NONCE_FIELD ] ?? '' ), self::NONCE_ACTION ) ) {
			return $result( false, 'nonce' );
		}
		// SET-RC-11: proof-of-work check and external spam checks (neutral error, nothing stored).
		$spam = PowCaptcha::check( 'report', $post );
		if ( null !== $spam ) {
			return $result( false, PowCaptcha::public_code( $spam ) );
		}
		$uuid   = strtolower( (string) ( $post['rc_review'] ?? '' ) );
		$reason = sanitize_key( (string) ( $post['rc_reason'] ?? '' ) );
		if ( ! preg_match( '/^[0-9a-f-]{36}$/', $uuid ) || ! in_array( $reason, ReportStore::enabled_reasons( $settings ), true ) ) {
			return $result( false, 'invalid' );
		}
		$review = $this->plugin->reviews()->find_by_uuid( $uuid );
		if ( null === $review || null === $review->id || ReviewStatus::APPROVED !== $review->status || null !== $review->removed_at ) {
			// Only publicly visible reviews can be reported.
			return $result( false, 'invalid' );
		}

		$limiter = new RateLimiter( 'report' );
		if ( $limiter->seen( $uuid ) ) {
			// Same address, same review, same hour: acknowledge without counting twice.
			return $result( true );
		}
		if ( $limiter->count() >= (int) $settings->get( 'submissions', 'report_rate_limit_per_hour', 5 ) ) {
			return $result( false, 'rate_limited' );
		}
		$limiter->hit( $uuid );

		$open    = ( new ReportStore() )->add( $review->id, $reason );
		$flagged = false;
		if ( $open >= max( 1, (int) $settings->get( 'submissions', 'report_threshold', 1 ) ) ) {
			// CAS inside change_status: a concurrent moderation decision wins.
			$flagged = $this->plugin->reviews()->change_status( $review->id, ReviewStatus::FLAGGED, 'public_report:' . $reason );
		}
		$this->plugin->audit()->record(
			'review.reported',
			'review',
			$review->id,
			array(
				'reason' => $reason,
				'count'  => $open,
				'status' => $flagged ? ReviewStatus::FLAGGED->value : $review->status->value,
			)
		);
		/**
		 * Filter the moderation URL used in notification mails (also in FormHandler).
		 *
		 * @param string                       $url    URL (review list filtered to open reports).
		 * @param \ReviewCaptain\Domain\Review $review Reported review.
		 */
		$url = (string) apply_filters( 'review_captain_moderation_url', admin_url( 'admin.php?page=review-captain&reported=1' ), $review );
		( new Notifier( $settings ) )->notify(
			Notifier::EVENT_REPORT,
			array(
				'moderation_url' => $url,
				'flagged'        => $flagged,
				'reason'         => $reason,
			)
		);
		return $result( true, '', $flagged );
	}

	/**
	 * Validated return URL.
	 *
	 * @param array<string, mixed> $post POST.
	 */
	private static function return_url( array $post ): string {
		$fallback = home_url( '/' );
		$url      = isset( $post['rc_return'] ) ? esc_url_raw( (string) $post['rc_return'] ) : '';
		$url      = '' !== $url ? wp_validate_redirect( $url, $fallback ) : $fallback;
		$url      = strtok( (string) $url, '#' );
		return remove_query_arg( array( 'rc_reported', 'rc_submitted', 'rc_error', 'rc_form', 'rc_draft' ), false !== $url ? $url : $fallback );
	}
}
