<?php
/**
 * Reviews list table (RC-038).
 *
 * Only load this file after WP_List_Table is available (ReviewsPage takes care of it).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Admin;

use ReviewCaptain\Domain\Kind;
use ReviewCaptain\Domain\Platform;
use ReviewCaptain\Domain\Review;
use ReviewCaptain\Domain\ReviewStatus;
use ReviewCaptain\Domain\Verification;
use ReviewCaptain\Plugin;
use ReviewCaptain\Security\Capabilities;

/**
 * WP_List_Table for reviews with views, filters, search, pagination and bulk actions.
 */
class ReviewsListTable extends \WP_List_Table {

	public const PER_PAGE = 20;

	/**
	 * Profile labels by ID.
	 *
	 * @var array<int, string>
	 */
	private array $profile_labels = array();

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( private Plugin $plugin ) {
		parent::__construct(
			array(
				'singular' => 'review',
				'plural'   => 'reviews',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Query args built from the current request (filters are read-only GET params).
	 *
	 * @param array<string, mixed> $request Request ($_GET).
	 * @return array<string, mixed>
	 */
	public static function query_args( array $request ): array {
		$args   = array();
		$status = View::req( $request, 'status' );
		if ( '' !== $status && in_array( $status, ReviewStatus::values(), true ) ) {
			$args['status'] = $status;
		} else {
			// "Alle" hides system tombstones; they have their own view.
			$args['status'] = array_values( array_diff( ReviewStatus::values(), array( ReviewStatus::REMOVED->value ) ) );
		}
		$kind = View::req( $request, 'kind' );
		if ( in_array( $kind, Kind::values(), true ) ) {
			$args['kind'] = $kind;
		}
		$platform = View::req( $request, 'platform' );
		if ( in_array( $platform, Platform::values(), true ) ) {
			$args['platform'] = $platform;
		}
		$rating = absint( View::req( $request, 'rating' ) );
		if ( $rating >= 1 && $rating <= 5 ) {
			// Normalised threshold for filtering only (never displayed as a score).
			$args['min_normalized'] = ( $rating - 1 ) / 4;
		}
		$period = absint( View::req( $request, 'period' ) );
		if ( in_array( $period, array( 7, 30, 90, 365 ), true ) ) {
			$args['date_from'] = gmdate( 'Y-m-d', time() - $period * DAY_IN_SECONDS );
		}
		$search = View::req( $request, 's' );
		if ( '' !== $search ) {
			$args['search'] = mb_substr( $search, 0, 100 );
		}
		$orderby         = View::req( $request, 'orderby' );
		$args['orderby'] = in_array( $orderby, array( 'rating', 'published_at', 'created_at' ), true ) ? $orderby : 'created_at';
		$args['order']   = 'asc' === strtolower( View::req( $request, 'order' ) ) ? 'ASC' : 'DESC';
		return $args;
	}

	/**
	 * Columns.
	 *
	 * @return array<string, string>
	 */
	public function get_columns() {
		return array(
			'cb'           => '<input type="checkbox" />',
			'rating'       => __( 'Bewertung', 'review-captain' ),
			'excerpt'      => __( 'Text', 'review-captain' ),
			'author'       => __( 'Autor', 'review-captain' ),
			'platform'     => __( 'Plattform / Quelle', 'review-captain' ),
			'kind'         => __( 'Typ', 'review-captain' ),
			'verification' => __( 'Verifizierung', 'review-captain' ),
			'status'       => __( 'Status', 'review-captain' ),
			'date'         => __( 'Datum', 'review-captain' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	protected function get_sortable_columns() {
		return array(
			'rating' => array( 'rating', false ),
			'date'   => array( 'created_at', true ),
		);
	}

	/**
	 * Bulk actions.
	 *
	 * @return array<string, string>
	 */
	protected function get_bulk_actions() {
		if ( ! current_user_can( Capabilities::MODERATE ) ) {
			return array();
		}
		return array(
			'approve' => __( 'Freigeben', 'review-captain' ),
			'reject'  => __( 'Ablehnen', 'review-captain' ),
			'archive' => __( 'Archivieren', 'review-captain' ),
		);
	}

	/**
	 * Status views with counts.
	 *
	 * @return array<string, string>
	 */
	protected function get_views() {
		$repo    = $this->plugin->reviews();
		$current = View::req( $_GET, 'status' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$base    = View::page_url( ReviewsPage::SLUG );
		$all     = $repo->count( array( 'status' => array_values( array_diff( ReviewStatus::values(), array( ReviewStatus::REMOVED->value ) ) ) ) );
		$views   = array(
			'all' => sprintf(
				'<a href="%s"%s>%s <span class="count">(%s)</span></a>',
				esc_url( $base ),
				'' === $current ? ' class="current" aria-current="page"' : '',
				esc_html__( 'Alle', 'review-captain' ),
				esc_html( number_format_i18n( $all ) )
			),
		);
		foreach ( ReviewStatus::cases() as $status ) {
			$count = $repo->count( array( 'status' => $status->value ) );
			if ( 0 === $count && ReviewStatus::REMOVED === $status ) {
				continue;
			}
			$views[ $status->value ] = sprintf(
				'<a href="%s"%s>%s <span class="count">(%s)</span></a>',
				esc_url( add_query_arg( 'status', $status->value, $base ) ),
				$current === $status->value ? ' class="current" aria-current="page"' : '',
				esc_html( $status->label() ),
				esc_html( number_format_i18n( $count ) )
			);
		}
		return $views;
	}

	/**
	 * Load items.
	 */
	public function prepare_items(): void {
		$args     = self::query_args( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$repo     = $this->plugin->reviews();
		$total    = $repo->count( $args );
		$page     = max( 1, $this->get_pagenum() );
		$per_page = self::PER_PAGE;

		$this->items = $repo->query(
			$args + array(
				'limit'  => $per_page,
				'offset' => ( $page - 1 ) * $per_page,
			)
		);
		foreach ( ( new Profiles() )->all() as $profile ) {
			$this->profile_labels[ (int) $profile['id'] ] = (string) $profile['label'];
		}
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'excerpt' );
		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			)
		);
	}

	/**
	 * Empty state (E2/E3).
	 */
	public function no_items(): void {
		$filtered = false;
		foreach ( array( 'kind', 'platform', 'rating', 'period', 's', 'status' ) as $key ) {
			if ( '' !== View::req( $_GET, $key ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$filtered = true;
			}
		}
		if ( $filtered ) {
			echo esc_html__( 'Keine Reviews für diese Filter.', 'review-captain' ) . ' <a href="' . esc_url( View::page_url( ReviewsPage::SLUG ) ) . '">' . esc_html__( 'Filter zurücksetzen', 'review-captain' ) . '</a>';
			return;
		}
		echo esc_html__( 'Noch keine Reviews. Lege dein erstes Review an oder sammle Bewertungen mit einem Formular.', 'review-captain' ) . ' <a href="' . esc_url( View::page_url( ReviewsPage::SLUG, array( 'action' => 'new' ) ) ) . '">' . esc_html__( 'Neu hinzufügen', 'review-captain' ) . '</a>';
	}

	/**
	 * Extra controls above the table: reason field for bulk reject/archive.
	 *
	 * @param string $which top|bottom.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which || ! $this->has_items() ) {
			return;
		}
		echo '<div class="alignleft actions">';
		echo '<label for="rc-bulk-reason">' . esc_html__( 'Grund (Pflicht bei Ablehnen/Archivieren)', 'review-captain' ) . '</label> ';
		echo '<input type="text" id="rc-bulk-reason" name="bulk_reason" class="regular-text" maxlength="500" />';
		echo '</div>';
	}

	/**
	 * Checkbox column.
	 *
	 * @param Review $item Review.
	 */
	protected function column_cb( $item ) {
		return '<label class="screen-reader-text" for="rc-cb-' . esc_attr( (string) $item->id ) . '">' . esc_html__( 'Auswählen', 'review-captain' ) . '</label>'
			. '<input type="checkbox" id="rc-cb-' . esc_attr( (string) $item->id ) . '" name="review[]" value="' . esc_attr( (string) $item->id ) . '" />';
	}

	/**
	 * Rating on original scale.
	 *
	 * @param Review $item Review.
	 */
	protected function column_rating( $item ) {
		return '<span class="rc-admin-rating">' . esc_html( View::rating( $item ) ) . '</span>';
	}

	/**
	 * Excerpt + row actions.
	 *
	 * @param Review $item Review.
	 */
	protected function column_excerpt( $item ) {
		$edit  = View::page_url( ReviewsPage::SLUG, array( 'action' => 'edit', 'id' => $item->id ) );
		$text  = '' !== $item->title ? $item->title . ' – ' . $item->body : $item->body;
		$html  = '<a class="row-title" href="' . esc_url( $edit ) . '">' . esc_html( View::excerpt( $text, 80 ) ?: __( '(ohne Text)', 'review-captain' ) ) . '</a>';
		$extra = $item->platform->is_external() ? __( 'Ansehen', 'review-captain' ) : __( 'Bearbeiten', 'review-captain' );

		$actions = array( 'edit' => '<a href="' . esc_url( $edit ) . '">' . esc_html( $extra ) . '</a>' );
		if ( $item->status->can_transition_to( ReviewStatus::APPROVED ) ) {
			$actions['approve'] = '<a href="' . esc_url(
				View::action_url(
					'review_status',
					array(
						'id'   => $item->id,
						'to'   => ReviewStatus::APPROVED->value,
						'back' => 'list',
					)
				)
			) . '">' . esc_html__( 'Freigeben', 'review-captain' ) . '</a>';
		}
		if ( $item->status->can_transition_to( ReviewStatus::REJECTED ) ) {
			$actions['reject'] = '<a href="' . esc_url( $edit . '#rc-moderation' ) . '">' . esc_html__( 'Ablehnen …', 'review-captain' ) . '</a>';
		}
		if ( $item->status->can_transition_to( ReviewStatus::ARCHIVED ) ) {
			$actions['archive'] = '<a href="' . esc_url( $edit . '#rc-moderation' ) . '">' . esc_html__( 'Archivieren …', 'review-captain' ) . '</a>';
		}
		if ( null !== $item->source_url && Profiles::is_https( $item->source_url ) ) {
			$actions['source'] = '<a href="' . esc_url( $item->source_url ) . '" target="_blank" rel="noopener noreferrer nofollow">' . esc_html__( 'Quelle öffnen', 'review-captain' ) . '<span class="screen-reader-text"> ' . esc_html__( '(öffnet in neuem Tab)', 'review-captain' ) . '</span> ↗</a>';
		}
		return $html . $this->row_actions( $actions );
	}

	/**
	 * Author.
	 *
	 * @param Review $item Review.
	 */
	protected function column_author( $item ) {
		return esc_html( '' !== $item->author_name ? $item->author_name : __( 'Anonym', 'review-captain' ) );
	}

	/**
	 * Platform + profile.
	 *
	 * @param Review $item Review.
	 */
	protected function column_platform( $item ) {
		$html = esc_html( $item->platform->label() );
		if ( null !== $item->profile_id ) {
			$label = $this->profile_labels[ $item->profile_id ] ?? __( 'Profil gelöscht', 'review-captain' );
			$html .= '<br /><span class="description">' . esc_html( $label ) . '</span>';
		}
		return $html;
	}

	/**
	 * Kind.
	 *
	 * @param Review $item Review.
	 */
	protected function column_kind( $item ) {
		return esc_html( $item->kind->label() );
	}

	/**
	 * Verification.
	 *
	 * @param Review $item Review.
	 */
	protected function column_verification( $item ) {
		$mod = match ( $item->verification ) {
			Verification::PURCHASE_VERIFIED, Verification::ATTENDANCE_CONFIRMED => 'ok',
			Verification::EXTERNAL_PLATFORM => 'neutral',
			default => 'neutral',
		};
		return View::pill( $item->verification->label(), $mod );
	}

	/**
	 * Status.
	 *
	 * @param Review $item Review.
	 */
	protected function column_status( $item ) {
		$mod = match ( $item->status ) {
			ReviewStatus::APPROVED => 'ok',
			ReviewStatus::PENDING, ReviewStatus::FLAGGED => 'warning',
			ReviewStatus::REJECTED, ReviewStatus::REMOVED => 'error',
			default => 'neutral',
		};
		return View::pill( $item->status->label(), $mod );
	}

	/**
	 * Date (original date if present).
	 *
	 * @param Review $item Review.
	 */
	protected function column_date( $item ) {
		return esc_html( View::date( $item->published_at ?? $item->created_at ) );
	}

	/**
	 * Fallback.
	 *
	 * @param Review $item        Review.
	 * @param string $column_name Column.
	 */
	protected function column_default( $item, $column_name ) {
		return '';
	}
}
