<?php
/**
 * Public platform profile links managed in Core (Konzept §8.4); connected profiles are added by Pro.
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Profiles;

use ReviewCaptain\Database\Schema;
use ReviewCaptain\Reviews\ReviewService;

defined( 'ABSPATH' ) || exit;

final class ProfileRepository {

	/**
	 * @return int|\WP_Error
	 */
	public static function save_link( int $id, string $provider, string $label, string $url ) {
		global $wpdb;
		if ( ! in_array( $provider, ReviewService::EXTERNAL_PROVIDERS, true ) ) {
			return new \WP_Error( 'provider', __( 'Please choose the platform.', 'review-captain' ) );
		}
		$url = esc_url_raw( $url, array( 'https' ) );
		if ( '' === $url || ! ReviewService::valid_https_url( $url ) ) {
			return new \WP_Error( 'url', __( 'Please enter the https link to your profile.', 'review-captain' ) );
		}
		$now  = current_time( 'mysql', true );
		$data = array(
			'provider'     => $provider,
			'label'        => substr( sanitize_text_field( $label ), 0, 191 ),
			'public_url'   => $url,
			'external_ref' => 'link:' . md5( $url ),
			'updated_at'   => $now,
		);
		$table    = Schema::table( 'rc_profiles' );
		$suppress = $wpdb->suppress_errors( true );
		if ( $id > 0 ) {
			$ok = $wpdb->update( $table, $data, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		} else {
			$data['created_at'] = $now;
			$ok                 = $wpdb->insert( $table, $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$id                 = (int) $wpdb->insert_id;
		}
		$wpdb->suppress_errors( $suppress );
		if ( false === $ok ) {
			return new \WP_Error( 'duplicate', __( 'This profile link already exists.', 'review-captain' ) );
		}
		ReviewService::milestone( 'first_source_at' );
		return $id;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function all(): array {
		global $wpdb;
		$table = Schema::table( 'rc_profiles' );
		return (array) $wpdb->get_results( "SELECT * FROM {$table} ORDER BY provider, label", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public static function find( int $id ): ?array {
		global $wpdb;
		$table = Schema::table( 'rc_profiles' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		return $row ? $row : null;
	}

	public static function delete( int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( Schema::table( 'rc_profiles' ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}
