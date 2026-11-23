<?php
/**
 * Public reports of reviews (RC-124).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Moderation;

use ReviewCaptain\Install\Schema;
use ReviewCaptain\Settings\Settings;

/**
 * Stores one row per accepted report: review, reason code, time. No reporter data (no IP, no
 * name, no e-mail) – the rate limit works with a short-lived hashed key only.
 */
final class ReportStore {

	public const REASONS = array( 'spam', 'offensive', 'personal_data', 'fake', 'other' );

	/**
	 * Translated reason labels.
	 *
	 * @return array<string, string>
	 */
	public static function reason_labels(): array {
		return array(
			'spam'          => __( 'Spam oder Werbung', 'review-captain' ),
			'offensive'     => __( 'Beleidigend oder unangemessen', 'review-captain' ),
			'personal_data' => __( 'Enthält personenbezogene Daten', 'review-captain' ),
			'fake'          => __( 'Vermutlich keine echte Erfahrung', 'review-captain' ),
			'other'         => __( 'Anderer Grund', 'review-captain' ),
		);
	}

	/**
	 * Reasons the public may choose (SET-RC-26): setting submissions.report_reasons, limited to
	 * the fixed list; filter `review_captain_report_reasons` wins. Never empty (falls back to all).
	 *
	 * @param Settings $settings Settings.
	 * @return list<string>
	 */
	public static function enabled_reasons( Settings $settings ): array {
		$chosen = $settings->get( 'submissions', 'report_reasons', self::REASONS );
		/**
		 * Filter the reasons offered in the public report form. Only values of ReportStore::REASONS count.
		 *
		 * @param list<string> $reasons Reason codes.
		 */
		$chosen  = apply_filters( 'review_captain_report_reasons', is_array( $chosen ) ? $chosen : self::REASONS );
		$reasons = array_values( array_intersect( self::REASONS, is_array( $chosen ) ? array_map( 'strval', $chosen ) : array() ) );
		return $reasons ? $reasons : self::REASONS;
	}

	/**
	 * Labels of the enabled reasons, in the order of the fixed list.
	 *
	 * @param Settings $settings Settings.
	 * @return array<string, string>
	 */
	public static function enabled_reason_labels( Settings $settings ): array {
		return array_intersect_key( self::reason_labels(), array_flip( self::enabled_reasons( $settings ) ) );
	}

	/**
	 * Record a report. Returns the number of open reports of the review afterwards.
	 *
	 * @param int    $review_id Review ID.
	 * @param string $reason    Reason code.
	 */
	public function add( int $review_id, string $reason ): int {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			Schema::table( 'review_reports' ),
			array(
				'review_id'  => $review_id,
				'reason'     => in_array( $reason, self::REASONS, true ) ? $reason : 'other',
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
			)
		);
		return $this->open_count( $review_id );
	}

	/**
	 * Open (unresolved) reports of a review.
	 *
	 * @param int $review_id Review ID.
	 */
	public function open_count( int $review_id ): int {
		global $wpdb;
		$table = Schema::table( 'review_reports' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE review_id = %d AND resolved_at IS NULL", $review_id ) );
	}

	/**
	 * Open reports grouped by reason (admin view).
	 *
	 * @param int $review_id Review ID.
	 * @return array<string, int>
	 */
	public function open_reasons( int $review_id ): array {
		global $wpdb;
		$table = Schema::table( 'review_reports' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT reason, COUNT(*) AS cnt FROM {$table} WHERE review_id = %d AND resolved_at IS NULL GROUP BY reason", $review_id ), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[ (string) $row['reason'] ] = (int) $row['cnt'];
		}
		return $out;
	}

	/**
	 * Mark all open reports of a review as resolved (a moderator decided).
	 *
	 * @param int $review_id Review ID.
	 */
	public static function resolve( int $review_id ): int {
		global $wpdb;
		$table = Schema::table( 'review_reports' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		return max( 0, (int) $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET resolved_at = %s WHERE review_id = %d AND resolved_at IS NULL", gmdate( 'Y-m-d H:i:s' ), $review_id ) ) );
	}
}
