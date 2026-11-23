<?php
/**
 * WordPress personal data exporter/eraser for reviews (RC-047).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Privacy;

use ReviewCaptain\Domain\Review;
use ReviewCaptain\Media\PhotoStore;
use ReviewCaptain\Plugin;
use ReviewCaptain\Questions\QuestionRepository;
use ReviewCaptain\Repository\ReviewRepository;
use ReviewCaptain\Settings\Settings;

/**
 * Finds reviews of a person by author_email or by the WordPress user with that e-mail
 * (author_user_id) and exports or erases them.
 *
 * Also covered: review photos and correction versions (deleted on anonymisation/deletion, RC-121,
 * RC-125A) and product questions
 * (table questions, RC-122: exported; anonymised or deleted according to the erase mode).
 *
 * Limits (documented): beyond that only the reviews table is covered. Review texts of external platforms are
 * not linked to an e-mail and therefore not found; moderation history (review_events) holds no
 * personal data; audit entries reference user IDs only and are pruned by retention; Pro data
 * (requests, suppression lists) is handled by Review Captain Pro's own exporter/eraser.
 */
class PersonalData {

	public const GROUP_ID = 'review-captain';

	public const PAGE_SIZE = 50;

	/**
	 * Meta keys that may hold personal data and are cleared on anonymisation.
	 */
	public const PERSONAL_META_KEYS = array( 'ip', 'ip_hash', 'user_agent', 'email', 'author_email', 'phone', 'author_url', 'author_avatar', 'consent_ip', 'submitter', 'recipient_ref', 'request_uuid', 'order_id', 'customer_id' );

	/**
	 * Constructor.
	 *
	 * @param ReviewRepository $reviews  Repository.
	 * @param Settings         $settings Settings.
	 */
	public function __construct( private ReviewRepository $reviews, private Settings $settings ) {}

	/**
	 * Exporter callback (wp_privacy_personal_data_exporters).
	 *
	 * @param string $email E-mail address.
	 * @param int    $page  Page (1-based).
	 * @return array{data: list<array{group_id: string, group_label: string, group_description: string, item_id: string, data: list<array{name: string, value: string}>}>, done: bool}
	 */
	public function export( string $email, int $page = 1 ): array {
		$page    = max( 1, $page );
		$reviews = $this->find( $email, ( $page - 1 ) * self::PAGE_SIZE );
		$items   = array();
		foreach ( $reviews as $r ) {
			$items[] = array(
				'group_id'          => self::GROUP_ID,
				'group_label'       => __( 'Review Captain', 'review-captain' ),
				'group_description' => __( 'Reviews and testimonials submitted with this email address or user account.', 'review-captain' ),
				'item_id'           => 'review-captain-review-' . $r->uuid,
				'data'              => self::export_fields( $r ),
			);
		}
		if ( 1 === $page ) {
			foreach ( ( new QuestionRepository() )->by_email( $email ) as $q ) {
				$items[] = array(
					'group_id'          => self::GROUP_ID . '-questions',
					'group_label'       => __( 'Review Captain – Product questions', 'review-captain' ),
					'group_description' => __( 'Questions asked with this email address.', 'review-captain' ),
					'item_id'           => 'review-captain-question-' . (string) $q['uuid'],
					'data'              => array_values(
						array_filter(
							array(
								array(
									'name'  => __( 'Question', 'review-captain' ),
									'value' => (string) $q['question'],
								),
								array(
									'name'  => __( 'Name', 'review-captain' ),
									'value' => (string) $q['author_name'],
								),
								array(
									'name'  => __( 'Email address', 'review-captain' ),
									'value' => (string) $q['author_email'],
								),
								array(
									'name'  => __( 'Context', 'review-captain' ),
									'value' => (string) $q['context_type'] . ':' . (string) $q['context_id'],
								),
								array(
									'name'  => __( 'Status', 'review-captain' ),
									'value' => (string) $q['status'],
								),
								array(
									'name'  => __( 'Created on (UTC)', 'review-captain' ),
									'value' => (string) $q['created_at'],
								),
							),
							static fn( array $f ): bool => '' !== $f['value']
						)
					),
				);
			}
		}
		return array(
			'data' => $items,
			'done' => count( $reviews ) < self::PAGE_SIZE,
		);
	}

	/**
	 * Eraser callback (wp_privacy_personal_data_erasers). Mode from setting privacy.erase_mode.
	 *
	 * @param string $email E-mail address.
	 * @param int    $page  Page (ignored: erased records no longer match, so always the first batch).
	 * @return array{items_removed: bool, items_retained: bool, messages: list<string>, done: bool}
	 */
	public function erase( string $email, int $page = 1 ): array {
		$mode     = 'delete' === $this->settings->get( 'privacy', 'erase_mode', 'anonymize' ) ? 'delete' : 'anonymize';
		$reviews  = $this->find( $email, 0 );
		$removed  = 0;
		$retained = 0;
		$messages = array();

		foreach ( $reviews as $r ) {
			if ( null === $r->id ) {
				continue;
			}
			if ( 'delete' === $mode ) {
				if ( $this->reviews->delete( $r->id ) ) {
					++$removed;
				} else {
					++$retained;
				}
				continue;
			}
			if ( $this->anonymize( $r ) ) {
				++$removed;
			} else {
				++$retained;
			}
		}
		$questions = new QuestionRepository();
		foreach ( $questions->by_email( $email ) as $q ) {
			$ok = 'delete' === $mode ? $questions->delete( (int) $q['id'] ) : $questions->anonymize( (int) $q['id'] );
			if ( $ok ) {
				++$removed;
			} else {
				++$retained;
			}
		}
		if ( $retained > 0 ) {
			$messages[] = __( 'Some reviews could not be anonymized and were retained.', 'review-captain' );
		}
		if ( $removed > 0 ) {
			Plugin::instance()->audit()->record(
				'privacy.erased',
				'review',
				'',
				array(
					'count' => $removed,
					'mode'  => $mode,
				)
			);
		}
		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => $retained > 0,
			'messages'       => $messages,
			'done'           => count( $reviews ) < self::PAGE_SIZE || ( 0 === $removed ),
		);
	}

	/**
	 * Anonymise one review. The text is kept only if publication consent was given.
	 *
	 * @param Review $r Review.
	 */
	public function anonymize( Review $r ): bool {
		$r->author_name    = __( 'Anonymous', 'review-captain' );
		$r->author_email   = null;
		$r->author_user_id = null;
		foreach ( self::personal_meta_keys() as $key ) {
			unset( $r->meta[ $key ] );
		}
		if ( ! $r->consent_publish ) {
			$r->title = '' === $r->title ? '' : (string) wp_privacy_anonymize_data( 'text', $r->title );
			$r->body  = (string) wp_privacy_anonymize_data( 'text', $r->body );
		}
		$r->meta['anonymized_at'] = gmdate( 'c' );
		if ( null !== $r->id ) {
			PhotoStore::delete_for_review( $r->id ); // A photo may show the person (RC-121).
			// Correction versions (RC-125A) hold the person's earlier and proposed texts verbatim (P4-3, P2-12).
			\ReviewCaptain\Moderation\CorrectionService::delete_for_review( $r->id );
		}
		$r->submission_key        = null; // Derived from the e-mail (HMAC) – personal.
		try {
			return $this->reviews->update( $r );
		} catch ( \InvalidArgumentException $e ) {
			return $this->force_anonymize( $r );
		}
		return true;
	}

	/**
	 * Fallback for legacy rows that do not pass validation: write the anonymised columns directly.
	 *
	 * @param Review $r Review.
	 */
	private function force_anonymize( Review $r ): bool {
		global $wpdb;
		$ok = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->reviews->table(),
			array(
				'author_name'    => $r->author_name,
				'author_email'   => null,
				'author_user_id' => null,
				'submission_key' => null,
				'title'          => $r->title,
				'body'           => $r->body,
				'meta'           => wp_json_encode( (object) $r->meta ),
				'updated_at'     => gmdate( 'Y-m-d H:i:s' ),
			),
			array( 'id' => $r->id )
		);
		return false !== $ok;
	}

	/**
	 * Personal meta keys (filterable).
	 *
	 * @return list<string>
	 */
	public static function personal_meta_keys(): array {
		/**
		 * Filter meta keys removed on anonymisation.
		 *
		 * @param list<string> $keys Keys.
		 */
		$keys = apply_filters( 'review_captain_privacy_personal_meta_keys', self::PERSONAL_META_KEYS );
		return is_array( $keys ) ? array_values( array_filter( $keys, 'is_string' ) ) : self::PERSONAL_META_KEYS;
	}

	/**
	 * Reviews of a person.
	 *
	 * @param string $email  E-mail.
	 * @param int    $offset Offset.
	 * @return list<Review>
	 */
	public function find( string $email, int $offset ): array {
		global $wpdb;
		$email = trim( $email );
		if ( '' === $email ) {
			return array();
		}
		$user    = get_user_by( 'email', $email );
		$user_id = $user && isset( $user->ID ) ? (int) $user->ID : 0;
		$table   = $this->reviews->table();
		if ( $user_id > 0 ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
			$sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE author_email = %s OR author_user_id = %d ORDER BY id ASC LIMIT %d OFFSET %d", $email, $user_id, self::PAGE_SIZE, max( 0, $offset ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
			$sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE author_email = %s ORDER BY id ASC LIMIT %d OFFSET %d", $email, self::PAGE_SIZE, max( 0, $offset ) );
		}
		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		return array_map( array( Review::class, 'from_row' ), is_array( $rows ) ? $rows : array() );
	}

	/**
	 * Name/value pairs for the export.
	 *
	 * @param Review $r Review.
	 * @return list<array{name: string, value: string}>
	 */
	private static function export_fields( Review $r ): array {
		$fields = array(
			__( 'Type', 'review-captain' )                         => $r->kind->label(),
			__( 'Platform', 'review-captain' )                   => $r->platform->label(),
			__( 'Rating', 'review-captain' )                   => null === $r->rating_value ? '' : sprintf( '%s / %s', self::num( $r->rating_value ), self::num( $r->rating_scale_max ) ),
			__( 'Title', 'review-captain' )                       => $r->title,
			__( 'Text', 'review-captain' )                        => $r->body,
			__( 'Name', 'review-captain' )                        => $r->author_name,
			__( 'Email address', 'review-captain' )              => (string) $r->author_email,
			__( 'User account ID', 'review-captain' )            => null === $r->author_user_id ? '' : (string) $r->author_user_id,
			__( 'Status', 'review-captain' )                      => $r->status->label(),
			__( 'Verification', 'review-captain' )               => $r->verification->label(),
			__( 'Context', 'review-captain' )                     => null === $r->context_type ? '' : $r->context_type . ':' . (string) $r->context_id,
			__( 'Consent to publication', 'review-captain' ) => $r->consent_publish ? __( 'yes', 'review-captain' ) . ( $r->consent_at ? ' (' . $r->consent_at . ' UTC)' : '' ) : __( 'no', 'review-captain' ),
			__( 'Published on', 'review-captain' )           => (string) $r->published_at,
			__( 'Created on (UTC)', 'review-captain' )           => $r->created_at,
			__( 'Photo', 'review-captain' )                        => null !== $r->id && null !== PhotoStore::for_review( $r->id ) ? __( 'yes (removed upon deletion or anonymization)', 'review-captain' ) : '',
			// RC-125A: earlier and proposed versions of author corrections.
			__( 'Versions (corrections)', 'review-captain' )      => null === $r->id ? '' : implode(
				"\n",
				array_map(
					static fn( array $v ): string => sprintf( '[%s %s] %s / %s – %s', $v['status'], $v['created_at'], self::num( (float) $v['rating_value'] ), $v['title'], $v['body'] ),
					\ReviewCaptain\Moderation\CorrectionService::versions( (int) $r->id )
				)
			),
		);
		$out = array();
		foreach ( $fields as $name => $value ) {
			if ( '' !== $value ) {
				$out[] = array(
					'name'  => (string) $name,
					'value' => (string) $value,
				);
			}
		}
		return $out;
	}

	/**
	 * Number format.
	 *
	 * @param float $v Value.
	 */
	private static function num( float $v ): string {
		return rtrim( rtrim( number_format( $v, 2, '.', '' ), '0' ), '.' );
	}
}
