<?php
/**
 * Widget definitions (rc_widgets). One definition is rendered identically by block, shortcode and builders (RC-118).
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Widgets;

use ReviewCaptain\Database\Schema;

defined( 'ABSPATH' ) || exit;

final class WidgetRepository {

	public const CORE_LAYOUTS = array( 'grid', 'list' );

	/**
	 * Default and allowed config keys.
	 *
	 * @return array<string,mixed>
	 */
	public static function default_config(): array {
		return array(
			'providers'            => array(),      // empty = all.
			'types'                => array(),
			'rating_min'           => 0,            // display filter only, never used for invitation logic.
			'limit'                => 6,
			'columns'              => 0,            // 0 = auto.
			'context_type'         => '',
			'context_id'           => '',
			'ids'                  => array(),      // hand-picked review IDs.
			'include_testimonials' => false,
			'show_date'            => null,         // Null means: use the global default.
			'author_mode'          => null,
			'show_summary'         => true,
			'order'                => 'date',
		);
	}

	/**
	 * @param array<string,mixed> $config Raw config.
	 * @return array<string,mixed>
	 */
	public static function sanitize_config( array $config ): array {
		$d   = self::default_config();
		$out = array(
			'providers'            => array_values( array_intersect( (array) ( $config['providers'] ?? array() ), array( 'site', 'google', 'trustpilot', 'etrusted', 'apple_app_store', 'google_play', 'other' ) ) ),
			'types'                => array_values( array_intersect( (array) ( $config['types'] ?? array() ), \ReviewCaptain\Reviews\ReviewRepository::TYPES ) ),
			'rating_min'           => max( 0, min( 5, (int) ( $config['rating_min'] ?? 0 ) ) ),
			'limit'                => max( 1, min( 24, (int) ( $config['limit'] ?? $d['limit'] ) ) ),
			'columns'              => max( 0, min( 4, (int) ( $config['columns'] ?? 0 ) ) ),
			'context_type'         => substr( sanitize_key( (string) ( $config['context_type'] ?? '' ) ), 0, 32 ),
			'context_id'           => substr( sanitize_text_field( (string) ( $config['context_id'] ?? '' ) ), 0, 64 ),
			'ids'                  => array_values( array_filter( array_map( 'absint', (array) ( $config['ids'] ?? array() ) ) ) ),
			'include_testimonials' => ! empty( $config['include_testimonials'] ),
			'show_date'            => isset( $config['show_date'] ) && '' !== $config['show_date'] && null !== $config['show_date'] ? (bool) $config['show_date'] : null,
			'author_mode'          => in_array( $config['author_mode'] ?? '', array( 'full', 'first_name_initial', 'initials', 'anonymous' ), true ) ? $config['author_mode'] : null,
			'show_summary'         => ! isset( $config['show_summary'] ) || (bool) $config['show_summary'],
			'order'                => in_array( $config['order'] ?? '', array( 'date', 'rating' ), true ) ? $config['order'] : 'date',
		);
		return $out;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public static function find( int $id ): ?array {
		global $wpdb;
		$table = Schema::table( 'rc_widgets' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		if ( ! $row ) {
			return null;
		}
		$config        = json_decode( (string) $row['config'], true );
		$row['config'] = self::sanitize_config( is_array( $config ) ? $config : array() );
		return $row;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function all(): array {
		global $wpdb;
		$table = Schema::table( 'rc_widgets' );
		return (array) $wpdb->get_results( "SELECT id, uuid, title, layout, status, updated_at FROM {$table} ORDER BY title ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * @param array<string,mixed> $config Config.
	 */
	public static function save( int $id, string $title, string $layout, array $config ): int {
		global $wpdb;
		$table  = Schema::table( 'rc_widgets' );
		$layout = sanitize_key( $layout );
		/**
		 * Allowed layouts. Pro adds carousel, badge, trustbar …
		 *
		 * @param string[] $layouts Layout keys.
		 */
		$allowed = (array) apply_filters( 'review_captain_widget_layouts', self::CORE_LAYOUTS );
		$data    = array(
			'title'      => substr( sanitize_text_field( $title ), 0, 191 ),
			'layout'     => in_array( $layout, $allowed, true ) ? $layout : 'grid',
			'config'     => wp_json_encode( self::sanitize_config( $config ) ),
			'updated_at' => current_time( 'mysql', true ),
		);
		if ( $id > 0 ) {
			$wpdb->update( $table, $data, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		} else {
			$data['uuid']       = wp_generate_uuid4();
			$data['created_at'] = $data['updated_at'];
			$wpdb->insert( $table, $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$id = (int) $wpdb->insert_id;
		}
		\ReviewCaptain\Reviews\ReviewService::bump_cache();
		return $id;
	}

	public static function delete( int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( Schema::table( 'rc_widgets' ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}
