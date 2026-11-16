<?php
/**
 * Reviews list table with search, filters, pagination and bulk moderation (RC-038).
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Admin;

use ReviewCaptain\Frontend\Renderer;
use ReviewCaptain\Reviews\ReviewRepository;
use ReviewCaptain\Reviews\ReviewService;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

final class ReviewsListTable extends \WP_List_Table {

	/** @var array<string,mixed> */
	private array $filters = array();

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'review',
				'plural'   => 'reviews',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Filters from the request (read-only GET, no state change).
	 *
	 * @return array<string,mixed>
	 */
	public static function request_filters(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$get = wp_unslash( $_GET );
		// phpcs:enable
		$f = array();
		if ( ! empty( $get['status'] ) && in_array( $get['status'], ReviewRepository::STATUSES, true ) ) {
			$f['status'] = $get['status'];
		}
		if ( ! empty( $get['provider'] ) ) {
			$f['provider'] = sanitize_key( $get['provider'] );
		}
		if ( ! empty( $get['type'] ) && in_array( $get['type'], ReviewRepository::TYPES, true ) ) {
			$f['type'] = $get['type'];
		}
		if ( ! empty( $get['rating_min'] ) ) {
			$f['rating_min'] = max( 1, min( 5, (int) $get['rating_min'] ) );
		}
		if ( ! empty( $get['origin'] ) && in_array( $get['origin'], ReviewRepository::ORIGINS, true ) ) {
			$f['origin'] = $get['origin'];
		}
		foreach ( array( 'date_from', 'date_to' ) as $d ) {
			if ( ! empty( $get[ $d ] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $get[ $d ] ) ) {
				$f[ $d ] = $get[ $d ];
			}
		}
		if ( ! empty( $get['language'] ) ) {
			$f['language'] = sanitize_text_field( $get['language'] );
		}
		if ( ! empty( $get['s'] ) ) {
			$f['search'] = sanitize_text_field( $get['s'] );
		}
		if ( ! empty( $get['batch'] ) ) {
			$f['batch'] = absint( $get['batch'] );
		}
		return $f;
	}

	public function get_columns(): array {
		return array(
			'cb'           => '<input type="checkbox">',
			'content'      => __( 'Review', 'review-captain' ),
			'rating'       => __( 'Rating', 'review-captain' ),
			'provider'     => __( 'Source', 'review-captain' ),
			'type'         => __( 'Type', 'review-captain' ),
			'status'       => __( 'Status', 'review-captain' ),
			'verification' => __( 'Verified', 'review-captain' ),
			'date'         => __( 'Date', 'review-captain' ),
		);
	}

	protected function get_sortable_columns(): array {
		return array(
			'date'   => array( 'date', true ),
			'rating' => array( 'rating', false ),
		);
	}

	protected function get_bulk_actions(): array {
		if ( ! current_user_can( 'rc_moderate_reviews' ) ) {
			return array();
		}
		$actions = array(
			'approved' => __( 'Approve', 'review-captain' ),
			'rejected' => __( 'Reject', 'review-captain' ),
			'archived' => __( 'Archive', 'review-captain' ),
			'spam'     => __( 'Mark as spam', 'review-captain' ),
		);
		if ( current_user_can( 'rc_manage_reviews' ) ) {
			$actions['delete'] = __( 'Delete permanently', 'review-captain' );
		}
		return $actions;
	}

	protected function get_views(): array {
		$counts  = ReviewRepository::status_counts();
		$current = $this->filters['status'] ?? '';
		$base    = admin_url( 'admin.php?page=review-captain-reviews' );
		$labels  = self::status_labels();
		$views   = array(
			'all' => sprintf( '<a href="%s"%s>%s <span class="count">(%s)</span></a>', esc_url( $base ), '' === $current ? ' class="current"' : '', esc_html__( 'All', 'review-captain' ), number_format_i18n( array_sum( $counts ) ) ),
		);
		foreach ( $counts as $status => $n ) {
			$views[ $status ] = sprintf( '<a href="%s"%s>%s <span class="count">(%s)</span></a>', esc_url( add_query_arg( 'status', $status, $base ) ), $current === $status ? ' class="current"' : '', esc_html( $labels[ $status ] ), number_format_i18n( $n ) );
		}
		return $views;
	}

	/**
	 * @return array<string,string>
	 */
	public static function status_labels(): array {
		return array(
			'pending'  => __( 'Pending', 'review-captain' ),
			'approved' => __( 'Approved', 'review-captain' ),
			'rejected' => __( 'Rejected', 'review-captain' ),
			'archived' => __( 'Archived', 'review-captain' ),
			'spam'     => __( 'Spam', 'review-captain' ),
		);
	}

	protected function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}
		$f = $this->filters;
		echo '<div class="alignleft actions">';
		echo '<label class="screen-reader-text" for="rc-filter-provider">' . esc_html__( 'Source', 'review-captain' ) . '</label><select name="provider" id="rc-filter-provider"><option value="">' . esc_html__( 'All sources', 'review-captain' ) . '</option>';
		foreach ( array_merge( array( 'site' ), ReviewService::EXTERNAL_PROVIDERS ) as $p ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $p ), selected( $f['provider'] ?? '', $p, false ), esc_html( ReviewService::provider_label( $p ) ) );
		}
		echo '</select>';
		echo '<label class="screen-reader-text" for="rc-filter-rating">' . esc_html__( 'Minimum rating', 'review-captain' ) . '</label><select name="rating_min" id="rc-filter-rating"><option value="">' . esc_html__( 'All ratings', 'review-captain' ) . '</option>';
		for ( $i = 5; $i >= 1; $i-- ) {
			/* translators: %d: stars */
			printf( '<option value="%d"%s>%s</option>', (int) $i, selected( (int) ( $f['rating_min'] ?? 0 ), $i, false ), esc_html( sprintf( __( '%d+ stars', 'review-captain' ), $i ) ) );
		}
		echo '</select>';
		echo '<label class="screen-reader-text" for="rc-filter-type">' . esc_html__( 'Type', 'review-captain' ) . '</label><select name="type" id="rc-filter-type"><option value="">' . esc_html__( 'All types', 'review-captain' ) . '</option>';
		foreach ( ReviewRepository::TYPES as $t ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $t ), selected( $f['type'] ?? '', $t, false ), esc_html( self::type_label( $t ) ) );
		}
		echo '</select>';
		printf( '<label class="screen-reader-text" for="rc-from">%s</label><input type="date" id="rc-from" name="date_from" value="%s">', esc_html__( 'From', 'review-captain' ), esc_attr( $f['date_from'] ?? '' ) );
		printf( '<label class="screen-reader-text" for="rc-to">%s</label><input type="date" id="rc-to" name="date_to" value="%s">', esc_html__( 'To', 'review-captain' ), esc_attr( $f['date_to'] ?? '' ) );
		submit_button( __( 'Filter', 'review-captain' ), '', 'filter_action', false );
		echo '</div>';
	}

	public static function type_label( string $type ): string {
		$labels = array(
			'service' => __( 'Service', 'review-captain' ),
			'product' => __( 'Product', 'review-captain' ),
			'app'     => __( 'App', 'review-captain' ),
			'event'   => __( 'Event', 'review-captain' ),
			'voucher' => __( 'Voucher', 'review-captain' ),
			'general' => __( 'General', 'review-captain' ),
		);
		return $labels[ $type ] ?? $type;
	}

	public function prepare_items(): void {
		$this->filters = self::request_filters();
		$per_page      = $this->get_items_per_page( 'rc_reviews_per_page', 20 );
		$page          = $this->get_pagenum();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$orderby = isset( $_GET['orderby'] ) && 'rating' === $_GET['orderby'] ? 'rating' : 'date';
		$order   = isset( $_GET['order'] ) && 'asc' === strtolower( sanitize_key( wp_unslash( $_GET['order'] ) ) ) ? 'ASC' : 'DESC';
		// phpcs:enable
		$total                 = ReviewRepository::count( $this->filters );
		$this->items           = ReviewRepository::query( $this->filters, $per_page, ( $page - 1 ) * $per_page, $orderby, $order );
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'content' );
		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
			)
		);
	}

	public function no_items(): void {
		esc_html_e( 'No reviews found. Collect reviews with the review form block or add reviews from other platforms manually.', 'review-captain' );
	}

	/**
	 * @param array<string,mixed> $item Row.
	 */
	protected function column_cb( $item ): string {
		return '<input type="checkbox" name="ids[]" value="' . (int) $item['id'] . '">';
	}

	/**
	 * @param array<string,mixed> $item Row.
	 */
	protected function column_content( $item ): string {
		$view    = admin_url( 'admin.php?page=review-captain-reviews&action=view&id=' . (int) $item['id'] );
		$title   = $item['title'] ? $item['title'] : wp_trim_words( (string) $item['content'], 8, '…' );
		$title   = '' !== trim( (string) $title ) ? $title : __( '(no text)', 'review-captain' );
		$out     = '<strong><a class="row-title" href="' . esc_url( $view ) . '">' . esc_html( $title ) . '</a></strong>';
		$out    .= '<div class="rc-excerpt">' . esc_html( wp_trim_words( (string) $item['content'], 25, '…' ) ) . '</div>';
		$out    .= '<div class="rc-author">' . esc_html( (string) $item['author_name'] ) . '</div>';
		$actions = array( 'view' => '<a href="' . esc_url( $view ) . '">' . esc_html__( 'View', 'review-captain' ) . '</a>' );
		if ( current_user_can( 'rc_moderate_reviews' ) ) {
			foreach ( array(
				'approved' => __( 'Approve', 'review-captain' ),
				'rejected' => __( 'Reject', 'review-captain' ),
			) as $to => $label ) {
				if ( $item['status'] !== $to ) {
					$url            = wp_nonce_url(
						add_query_arg(
							array(
								'action' => 'review_captain_moderate',
								'id'     => (int) $item['id'],
								'to'     => $to,
							),
							admin_url( 'admin-post.php' )
						),
						'rc_moderate_' . (int) $item['id']
					);
					$actions[ $to ] = '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
				}
			}
		}
		if ( 'manual' === $item['origin'] && current_user_can( 'rc_manage_reviews' ) ) {
			$actions['edit'] = '<a href="' . esc_url( admin_url( 'admin.php?page=review-captain-reviews&action=edit&id=' . (int) $item['id'] ) ) . '">' . esc_html__( 'Edit', 'review-captain' ) . '</a>';
		}
		return $out . $this->row_actions( $actions );
	}

	/**
	 * @param array<string,mixed> $item Row.
	 */
	protected function column_rating( $item ): string {
		return null === $item['rating_value'] ? '—' : Renderer::stars( (float) $item['rating_value'], (int) $item['rating_min'], (int) $item['rating_max'] );
	}

	/**
	 * @param array<string,mixed> $item Row.
	 */
	protected function column_provider( $item ): string {
		$label = esc_html( ReviewService::provider_label( (string) $item['provider'] ) );
		if ( ! empty( $item['tombstoned_at'] ) ) {
			$label .= '<br><span class="rc-pill rc-pill--warn">' . esc_html__( 'removed at source', 'review-captain' ) . '</span>';
		}
		return $label;
	}

	/**
	 * @param array<string,mixed> $item Row.
	 */
	protected function column_type( $item ): string {
		return esc_html( self::type_label( (string) $item['type'] ) );
	}

	/**
	 * @param array<string,mixed> $item Row.
	 */
	protected function column_status( $item ): string {
		$labels = self::status_labels();
		$out    = '<span class="rc-pill rc-pill--' . esc_attr( $item['status'] ) . '">' . esc_html( $labels[ $item['status'] ] ?? $item['status'] ) . '</span>';
		if ( (int) $item['reported_count'] > 0 ) {
			/* translators: %d: number of reports */
			$out .= '<br><span class="rc-pill rc-pill--warn">' . esc_html( sprintf( _n( '%d report', '%d reports', (int) $item['reported_count'], 'review-captain' ), (int) $item['reported_count'] ) ) . '</span>';
		}
		return $out;
	}

	/**
	 * @param array<string,mixed> $item Row.
	 */
	protected function column_verification( $item ): string {
		$labels = array(
			'purchase'   => __( 'Purchase verified', 'review-captain' ),
			'attendance' => __( 'Attendance confirmed', 'review-captain' ),
			'external'   => __( 'External platform', 'review-captain' ),
			'unverified' => __( 'Not verified', 'review-captain' ),
		);
		return esc_html( $labels[ $item['verification'] ] ?? '' );
	}

	/**
	 * @param array<string,mixed> $item Row.
	 */
	protected function column_date( $item ): string {
		$date = $item['published_at'] ? $item['published_at'] : $item['created_at'];
		return esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $date . ' UTC' ) ) );
	}
}
