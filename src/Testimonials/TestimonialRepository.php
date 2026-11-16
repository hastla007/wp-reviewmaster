<?php
/**
 * Text testimonials in Core (RC-053); media kinds are managed by Pro (RC-107 ff.).
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Testimonials;

use ReviewCaptain\Database\Schema;

defined( 'ABSPATH' ) || exit;

final class TestimonialRepository {

	public const NAME_MODES = array( 'full', 'first_name', 'initials', 'anonymous' );

	private static function table(): string {
		return Schema::table( 'rc_testimonials' );
	}

	/**
	 * @param array<string,mixed> $input quote, display_name, display_name_mode, role_label, context_type, context_id, language, status, consent_note, valid_until.
	 * @return int|\WP_Error
	 */
	public static function save_text( int $id, array $input ) {
		global $wpdb;
		$quote = trim( sanitize_textarea_field( wp_unslash( (string) ( $input['quote'] ?? '' ) ) ) );
		if ( '' === $quote ) {
			return new \WP_Error( 'quote', __( 'Please enter the testimonial text.', 'review-captain' ) );
		}
		$status  = in_array( $input['status'] ?? 'draft', array( 'draft', 'approved', 'archived' ), true ) ? $input['status'] : 'draft';
		$consent = trim( sanitize_text_field( wp_unslash( (string) ( $input['consent_note'] ?? '' ) ) ) );
		if ( 'approved' === $status && '' === $consent ) {
			return new \WP_Error( 'consent', __( 'Please document how the person agreed to publication before approving.', 'review-captain' ) );
		}
		$now  = current_time( 'mysql', true );
		$data = array(
			'kind'              => 'text',
			'quote'             => $quote,
			'display_name'      => substr( sanitize_text_field( wp_unslash( (string) ( $input['display_name'] ?? '' ) ) ), 0, 191 ),
			'display_name_mode' => in_array( $input['display_name_mode'] ?? '', self::NAME_MODES, true ) ? $input['display_name_mode'] : 'full',
			'role_label'        => substr( sanitize_text_field( wp_unslash( (string) ( $input['role_label'] ?? '' ) ) ), 0, 191 ),
			'context_type'      => substr( sanitize_key( (string) ( $input['context_type'] ?? '' ) ), 0, 32 ),
			'context_id'        => substr( sanitize_text_field( (string) ( $input['context_id'] ?? '' ) ), 0, 64 ),
			'language'          => substr( sanitize_text_field( (string) ( $input['language'] ?? '' ) ), 0, 20 ),
			'consent'           => wp_json_encode(
				array(
					'text' => array(
						'note'        => $consent,
						'recorded_at' => gmdate( 'c' ),
					),
				)
			),
			'valid_until'       => ! empty( $input['valid_until'] ) && strtotime( (string) $input['valid_until'] ) ? gmdate( 'Y-m-d 23:59:59', strtotime( (string) $input['valid_until'] ) ) : null,
			'status'            => $status,
			'updated_at'        => $now,
		);
		if ( $id > 0 ) {
			$wpdb->update( self::table(), $data, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		} else {
			$data['uuid']       = wp_generate_uuid4();
			$data['created_at'] = $now;
			$wpdb->insert( self::table(), $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$id = (int) $wpdb->insert_id;
		}
		\ReviewCaptain\Reviews\ReviewService::bump_cache();
		return $id;
	}

	public static function revoke( int $id ): void {
		global $wpdb;
		$wpdb->update(
			self::table(),
			array(
				'revoked_at' => current_time( 'mysql', true ),
				'status'     => 'archived',
			),
			array( 'id' => $id )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		\ReviewCaptain\Reviews\ReviewService::bump_cache();
	}

	public static function delete( int $id ): bool {
		global $wpdb;
		$ok = (bool) $wpdb->delete( self::table(), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		\ReviewCaptain\Reviews\ReviewService::bump_cache();
		return $ok;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public static function find( int $id ): ?array {
		global $wpdb;
		$table = self::table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		return $row ? $row : null;
	}

	/**
	 * Admin list.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function all( int $limit = 200 ): array {
		global $wpdb;
		$table = self::table();
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Publishable testimonials: approved, not revoked, not expired (RC-015). Core outputs only text.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function publishable( string $context_type = '', string $context_id = '', int $limit = 6 ): array {
		global $wpdb;
		$table = self::table();
		$sql   = "SELECT * FROM {$table} WHERE status = 'approved' AND revoked_at IS NULL AND (valid_until IS NULL OR valid_until > %s) AND kind = 'text'";
		$args  = array( current_time( 'mysql', true ) );
		if ( '' !== $context_type ) {
			$sql   .= ' AND context_type = %s';
			$args[] = $context_type;
			if ( '' !== $context_id ) {
				$sql   .= ' AND context_id = %s';
				$args[] = $context_id;
			}
		}
		$sql   .= ' ORDER BY id DESC LIMIT %d';
		$args[] = $limit;
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
	}
}
