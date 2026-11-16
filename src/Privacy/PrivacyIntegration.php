<?php
/**
 * WordPress privacy exporter/eraser and policy text (RC-047).
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Privacy;

use ReviewCaptain\Database\Schema;
use ReviewCaptain\Reviews\ReviewRepository;
use ReviewCaptain\Reviews\ReviewService;
use ReviewCaptain\Security\Crypto;
use ReviewCaptain\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class PrivacyIntegration {

	private const PAGE_SIZE = 100;

	public static function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( self::class, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( self::class, 'register_eraser' ) );
		add_action( 'admin_init', array( self::class, 'policy_content' ) );
	}

	/**
	 * @param array<string,array<string,mixed>> $exporters Exporters.
	 * @return array<string,array<string,mixed>>
	 */
	public static function register_exporter( array $exporters ): array {
		$exporters['review-captain'] = array(
			'exporter_friendly_name' => __( 'Review Captain reviews', 'review-captain' ),
			'callback'               => array( self::class, 'export' ),
		);
		return $exporters;
	}

	/**
	 * @param array<string,array<string,mixed>> $erasers Erasers.
	 * @return array<string,array<string,mixed>>
	 */
	public static function register_eraser( array $erasers ): array {
		$erasers['review-captain'] = array(
			'eraser_friendly_name' => __( 'Review Captain reviews', 'review-captain' ),
			'callback'             => array( self::class, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Reviews linked to an e-mail address (via keyed hash) or to the WP user with that address.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function find_reviews( string $email, int $page ): array {
		global $wpdb;
		$table   = Schema::table( 'rc_reviews' );
		$hash    = Crypto::lookup_hash( $email );
		$user    = get_user_by( 'email', $email );
		$user_id = $user ? (int) $user->ID : 0;
		$offset  = ( max( 1, $page ) - 1 ) * self::PAGE_SIZE;
		return (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE author_email_hash = %s OR ( %d > 0 AND author_user_id = %d ) ORDER BY id ASC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$hash,
				$user_id,
				$user_id,
				self::PAGE_SIZE,
				$offset
			),
			ARRAY_A
		);
	}

	/**
	 * @return array{data: array<int,array<string,mixed>>, done: bool}
	 */
	public static function export( string $email, int $page = 1 ): array {
		$rows = self::find_reviews( $email, $page );
		$data = array();
		foreach ( $rows as $row ) {
			$data[] = array(
				'group_id'    => 'review-captain-reviews',
				'group_label' => __( 'Reviews', 'review-captain' ),
				'item_id'     => 'review-' . $row['uuid'],
				'data'        => array(
					array(
						'name'  => __( 'Rating', 'review-captain' ),
						'value' => null !== $row['rating_value'] ? $row['rating_value'] . ' / ' . $row['rating_max'] : '',
					),
					array(
						'name'  => __( 'Title', 'review-captain' ),
						'value' => (string) $row['title'],
					),
					array(
						'name'  => __( 'Text', 'review-captain' ),
						'value' => (string) $row['content'],
					),
					array(
						'name'  => __( 'Name', 'review-captain' ),
						'value' => (string) $row['author_name'],
					),
					array(
						'name'  => __( 'Status', 'review-captain' ),
						'value' => (string) $row['status'],
					),
					array(
						'name'  => __( 'Date', 'review-captain' ),
						'value' => (string) ( $row['published_at'] ?? $row['created_at'] ),
					),
				),
			);
		}
		/**
		 * Add-ons append request/consent data for the same address.
		 */
		$data = (array) apply_filters( 'review_captain_privacy_export', $data, $email, $page );
		return array(
			'data' => $data,
			'done' => count( $rows ) < self::PAGE_SIZE,
		);
	}

	/**
	 * Anonymize reviews of the data subject. Rejected/pending reviews are deleted.
	 *
	 * @return array{items_removed:int|bool, items_retained:int|bool, messages:string[], done:bool}
	 */
	public static function erase( string $email, int $page = 1 ): array {
		// Always read page 1: anonymized rows no longer match.
		$rows      = self::find_reviews( $email, 1 );
		$removed   = 0;
		$erase_txt = (bool) Settings::get( 'erase_review_text_on_request' );
		foreach ( $rows as $row ) {
			$id = (int) $row['id'];
			if ( in_array( $row['status'], array( 'pending', 'rejected', 'spam' ), true ) || $erase_txt ) {
				ReviewRepository::delete( $id );
			} else {
				ReviewRepository::update(
					$id,
					array(
						'author_name'       => __( 'Anonymous', 'review-captain' ),
						'author_email_hash' => null,
						'author_user_id'    => null,
						'author_avatar_url' => null,
					)
				);
				ReviewRepository::log( $id, 'edited', 'privacy_erasure' );
			}
			++$removed;
		}
		if ( $removed ) {
			ReviewService::bump_cache();
		}
		$result = array(
			'items_removed'  => $removed,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => count( $rows ) < self::PAGE_SIZE,
		);
		return (array) apply_filters( 'review_captain_privacy_erase', $result, $email, $page );
	}

	public static function policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$text  = '<p class="privacy-policy-tutorial">' . esc_html__( 'Suggested text for your privacy policy. Please adapt it to the features you actually use.', 'review-captain' ) . '</p>';
		$text .= '<p>' . esc_html__( 'When you submit a review on this website we store your rating, text, the name you enter and – if provided – a pseudonymized reference to your e-mail address so that we can answer data protection requests. Reviews are published only after moderation. You can request access to or deletion of your reviews at any time.', 'review-captain' ) . '</p>';
		$text .= '<p>' . esc_html__( 'Reviews from other platforms (for example Google or Trustpilot) are shown with their source and a link to the original. Content from external services such as videos or official platform widgets is loaded only after you click on it.', 'review-captain' ) . '</p>';
		wp_add_privacy_policy_content( 'Review Captain', wp_kses_post( $text ) );
	}
}
