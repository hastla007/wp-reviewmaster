<?php
/**
 * Reviews admin page: list, detail/edit, create (RC-038, RC-040, RC-043, RC-053).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Admin;

use ReviewCaptain\Domain\Kind;
use ReviewCaptain\Domain\Origin;
use ReviewCaptain\Domain\Platform;
use ReviewCaptain\Domain\Review;
use ReviewCaptain\Domain\ReviewStatus;
use ReviewCaptain\Media\PhotoStore;
use ReviewCaptain\Moderation\ReportStore;
use ReviewCaptain\Moderation\TagStore;
use ReviewCaptain\Plugin;
use ReviewCaptain\Security\Capabilities;

/**
 * Renders the Reviews screens. Writes go through ReviewActions (admin-post / load hook).
 */
final class ReviewsPage {

	public const SLUG = 'review-captain-reviews';

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( private Plugin $plugin ) {}

	/**
	 * Page callback.
	 */
	public function render(): void {
		if ( ! current_user_can( Capabilities::MODERATE ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'review-captain' ), '', array( 'response' => 403 ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only.
		$action = View::req( $_GET, 'action' );
		echo '<div class="wrap rc-admin">';
		if ( 'edit' === $action ) {
			$review = $this->plugin->reviews()->find( absint( View::req( $_GET, 'id' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( null === $review ) {
				echo View::header( __( 'Review', 'review-captain' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo View::notice( 'error', __( 'The entry was not found.', 'review-captain' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} else {
				$this->render_edit( $review );
			}
		} elseif ( 'new' === $action ) {
			$this->render_new( View::req( $_GET, 'type', ReviewEditor::TYPE_LOCAL ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		} else {
			$this->render_list();
		}
		echo '</div>';
	}

	/**
	 * List screen.
	 */
	private function render_list(): void {
		if ( ! class_exists( '\WP_List_Table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		}
		$table = new ReviewsListTable( $this->plugin );
		$table->prepare_items();

		echo View::header( __( 'Reviews', 'review-captain' ), View::page_url( self::SLUG, array( 'action' => 'new' ) ), __( 'Add new', 'review-captain' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo Notices::render_current(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		$pending = $this->plugin->reviews()->count( array( 'status' => ReviewStatus::PENDING->value ) );
		if ( 0 === $pending && $this->plugin->reviews()->count() > 0 ) {
			echo View::notice( 'info', __( 'All done – no reviews awaiting review.', 'review-captain' ), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		$table->views();
		$this->render_filters();

		$status = View::req( $_GET, 'status' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<form method="post" action="' . esc_url( View::page_url( self::SLUG ) ) . '">';
		echo '<input type="hidden" name="rc_bulk" value="1" />';
		echo '<input type="hidden" name="status" value="' . esc_attr( $status ) . '" />';
		wp_nonce_field( Capabilities::nonce_action( 'bulk_reviews' ), '_rc_nonce' );
		$table->display();
		echo '</form>';
	}

	/**
	 * Filter + search form (GET, read-only).
	 */
	private function render_filters(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
		$get = $_GET;
		// phpcs:enable
		echo '<form method="get" class="rc-admin-filters">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '" />';
		if ( '' !== View::req( $get, 'status' ) ) {
			echo '<input type="hidden" name="status" value="' . esc_attr( View::req( $get, 'status' ) ) . '" />';
		}
		echo '<p class="search-box"><label class="screen-reader-text" for="rc-search">' . esc_html__( 'Search reviews', 'review-captain' ) . '</label>';
		echo '<input type="search" id="rc-search" name="s" value="' . esc_attr( View::req( $get, 's' ) ) . '" /> ';
		echo '<button type="submit" class="button">' . esc_html__( 'Search reviews', 'review-captain' ) . '</button></p>';

		echo '<div class="tablenav top rc-admin-filterbar"><div class="alignleft actions">';
		$this->select( 'kind', __( 'Type', 'review-captain' ), array( '' => __( 'All types', 'review-captain' ) ) + self::kind_choices(), View::req( $get, 'kind' ) );
		$this->select( 'platform', __( 'Platform', 'review-captain' ), array( '' => __( 'All platforms', 'review-captain' ) ) + self::platform_choices(), View::req( $get, 'platform' ) );
		$ratings = array( '' => __( 'Any rating', 'review-captain' ) );
		for ( $i = 5; $i >= 1; $i-- ) {
			/* translators: %d: stars on a 5-point scale */
			$ratings[ (string) $i ] = sprintf( __( '%d out of 5 or higher (converted)', 'review-captain' ), $i );
		}
		$this->select( 'rating', __( 'Rating', 'review-captain' ), $ratings, View::req( $get, 'rating' ) );
		$this->select(
			'period',
			__( 'Period', 'review-captain' ),
			array(
				''    => __( 'All time', 'review-captain' ),
				'7'   => __( 'Last 7 days', 'review-captain' ),
				'30'  => __( 'Last 30 days', 'review-captain' ),
				'90'  => __( 'Last 90 days', 'review-captain' ),
				'365' => __( 'Last 12 months', 'review-captain' ),
			),
			View::req( $get, 'period' )
		);
		$settings = $this->plugin->settings();
		if ( $settings->get( 'submissions', 'tags_enabled', false ) ) {
			$tags = array( '' => __( 'All tags', 'review-captain' ) );
			foreach ( ( new TagStore() )->all() as $tag => $count ) {
				$tags[ $tag ] = $tag . ' (' . number_format_i18n( $count ) . ')';
			}
			$this->select( 'tag', __( 'Tag', 'review-captain' ), $tags, View::req( $get, 'tag' ) );
		}
		if ( $settings->get( 'submissions', 'reports_enabled', false ) ) {
			echo '<label class="rc-admin-filter-check"><input type="checkbox" name="reported" value="1"' . checked( View::req( $get, 'reported' ), '1', false ) . ' /> ' . esc_html__( 'Only with open reports', 'review-captain' ) . '</label> ';
		}
		echo '<button type="submit" class="button">' . esc_html__( 'Filter', 'review-captain' ) . '</button>';
		echo '</div></div></form>';
	}

	/**
	 * Render a labelled select.
	 *
	 * @param string                $name     Name.
	 * @param string                $label    Label.
	 * @param array<string, string> $choices  Choices.
	 * @param string                $current  Current.
	 */
	private function select( string $name, string $label, array $choices, string $current ): void {
		$id = 'rc-filter-' . $name;
		echo '<label class="screen-reader-text" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
		echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
		foreach ( $choices as $value => $text ) {
			echo '<option value="' . esc_attr( (string) $value ) . '"' . selected( (string) $value, $current, false ) . '>' . esc_html( $text ) . '</option>';
		}
		echo '</select> ';
	}

	/**
	 * Kind choices.
	 *
	 * @return array<string, string>
	 */
	public static function kind_choices(): array {
		$out = array();
		foreach ( Kind::cases() as $kind ) {
			$out[ $kind->value ] = $kind->label();
		}
		return $out;
	}

	/**
	 * Platform choices.
	 *
	 * @param bool $external_only Only external platforms.
	 * @return array<string, string>
	 */
	public static function platform_choices( bool $external_only = false ): array {
		$out = array();
		foreach ( Platform::cases() as $platform ) {
			if ( $external_only && ! $platform->is_external() ) {
				continue;
			}
			$out[ $platform->value ] = $platform->label();
		}
		return $out;
	}

	/**
	 * Context type choices.
	 *
	 * @return array<string, string>
	 */
	public static function context_choices(): array {
		return array(
			''             => __( 'No reference', 'review-captain' ),
			'post'         => __( 'Post/page', 'review-captain' ),
			'product'      => __( 'Product', 'review-captain' ),
			'event'        => _x( 'Event', 'reference type', 'review-captain' ),
			'voucher'      => __( 'Voucher', 'review-captain' ),
			'app'          => __( 'App', 'review-captain' ),
			'form'         => __( 'Form', 'review-captain' ),
			'course'       => __( 'Course', 'review-captain' ),
			'organization' => __( 'Business', 'review-captain' ),
		);
	}

	/**
	 * Create screen.
	 *
	 * @param string $type Entry type.
	 */
	private function render_new( string $type ): void {
		$types = ReviewEditor::types();
		$type  = array_key_exists( $type, $types ) ? $type : ReviewEditor::TYPE_LOCAL;
		$draft = ReviewActions::take_draft();

		echo View::header( __( 'Create new entry', 'review-captain' ), View::page_url( self::SLUG ), __( '← Back to list', 'review-captain' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo Notices::render_current(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $types as $key => $label ) {
			$class = 'nav-tab' . ( $key === $type ? ' nav-tab-active' : '' );
			echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( View::page_url( self::SLUG, array( 'action' => 'new', 'type' => $key ) ) ) . '"' . ( $key === $type ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a>';
		}
		echo '</h2>';

		if ( ReviewEditor::TYPE_TESTIMONIAL === $type ) {
			echo View::notice( 'info', __( 'A testimonial is labeled as a site-owned statement – not as a platform review. It receives no review markup for search engines.', 'review-captain' ), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} elseif ( ReviewEditor::TYPE_EXTERNAL === $type ) {
			echo View::notice( 'info', __( 'Only add texts you are permitted to use, and always link to the original. The rating stays on its original scale and is not combined with other platforms.', 'review-captain' ), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo View::form_fields( 'save_review' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<input type="hidden" name="review[type]" value="' . esc_attr( $type ) . '" />';
		echo '<table class="form-table" role="presentation"><tbody>';

		if ( ReviewEditor::TYPE_EXTERNAL === $type ) {
			$this->row_select( 'platform', __( 'Platform', 'review-captain' ), self::platform_choices( true ), (string) ( $draft['platform'] ?? '' ), true );
			$profiles = array( '' => __( '– no profile –', 'review-captain' ) );
			foreach ( ( new Profiles() )->all() as $profile ) {
				$profiles[ (string) $profile['id'] ] = Platform::tryFrom( (string) $profile['platform'] )?->label() . ' – ' . $profile['label'];
			}
			$this->row_select( 'profile_id', __( 'Profile', 'review-captain' ), $profiles, (string) ( $draft['profile_id'] ?? '' ) );
			echo '<tr><th scope="row"><label for="rc-rating_value">' . esc_html__( 'Original rating', 'review-captain' ) . ' <span class="required">*</span></label></th><td>';
			echo '<input type="text" inputmode="decimal" id="rc-rating_value" name="review[rating_value]" class="small-text" required value="' . esc_attr( (string) ( $draft['rating_value'] ?? '' ) ) . '" /> ';
			echo '<label for="rc-scale-min">' . esc_html__( 'on a scale of', 'review-captain' ) . '</label> <input type="number" step="1" id="rc-scale-min" name="review[rating_scale_min]" class="small-text" value="' . esc_attr( (string) ( $draft['rating_scale_min'] ?? '1' ) ) . '" /> ';
			echo '<label for="rc-scale-max">' . esc_html__( 'to', 'review-captain' ) . '</label> <input type="number" step="1" id="rc-scale-max" name="review[rating_scale_max]" class="small-text" value="' . esc_attr( (string) ( $draft['rating_scale_max'] ?? '5' ) ) . '" />';
			echo '<p class="description">' . esc_html__( 'Exactly as shown on the platform (e.g. 4 out of 5 or 9 out of 10).', 'review-captain' ) . '</p></td></tr>';
		} else {
			echo '<tr><th scope="row"><label for="rc-rating_value">' . esc_html__( 'Rating', 'review-captain' ) . '</label></th><td>';
			echo '<select id="rc-rating_value" name="review[rating_value]"><option value="">' . esc_html__( '– no stars –', 'review-captain' ) . '</option>';
			for ( $i = 5; $i >= 1; $i-- ) {
				/* translators: %d: rating value */
				echo '<option value="' . esc_attr( (string) $i ) . '"' . selected( (string) $i, (string) ( $draft['rating_value'] ?? '' ), false ) . '>' . esc_html( sprintf( __( '%d out of 5', 'review-captain' ), $i ) ) . '</option>';
			}
			echo '</select></td></tr>';
		}

		$this->row_text( 'title', __( 'Title', 'review-captain' ), (string) ( $draft['title'] ?? '' ) );
		$body_label = ReviewEditor::TYPE_EXTERNAL === $type ? __( 'Permitted text (excerpt)', 'review-captain' ) : __( 'Text', 'review-captain' );
		$this->row_textarea( 'body', $body_label, (string) ( $draft['body'] ?? '' ), true );
		$this->row_text( 'author_name', ReviewEditor::TYPE_TESTIMONIAL === $type ? __( 'Person\'s name', 'review-captain' ) : __( 'Name', 'review-captain' ), (string) ( $draft['author_name'] ?? '' ), true );
		$this->row_date( 'published_at', ReviewEditor::TYPE_EXTERNAL === $type ? __( 'Date of original review', 'review-captain' ) : __( 'Date', 'review-captain' ), (string) ( $draft['published_at'] ?? '' ), ReviewEditor::TYPE_EXTERNAL === $type );
		$this->row_text( 'language', __( 'Language', 'review-captain' ), (string) ( $draft['language'] ?? '' ), false, __( 'Language code, e.g. de or de_AT. Texts are never translated.', 'review-captain' ) );

		if ( ReviewEditor::TYPE_EXTERNAL === $type ) {
			$this->row_text( 'source_url', __( 'Profile/original link', 'review-captain' ), (string) ( $draft['source_url'] ?? '' ), true, __( 'https links only. Linked as the source on the frontend.', 'review-captain' ), 'url' );
			$this->row_text( 'source_note', __( 'Source note', 'review-captain' ), (string) ( $draft['source_note'] ?? '' ), true, __( 'e.g. “Excerpt from Google, added on 11/14/2026”.', 'review-captain' ) );
		}
		$this->row_context( (string) ( $draft['context_type'] ?? '' ), (string) ( $draft['context_id'] ?? '' ) );

		echo '<tr><th scope="row">' . esc_html__( 'Verification', 'review-captain' ) . '</th><td>';
		echo esc_html( ReviewEditor::TYPE_EXTERNAL === $type ? __( 'External platform review', 'review-captain' ) : __( 'Unverified', 'review-captain' ) );
		echo '<p class="description">' . esc_html__( '“Verified purchase” or “Attendance confirmed” can only be set by a connected source, not manually.', 'review-captain' ) . '</p></td></tr>';

		$this->row_select(
			'status',
			__( 'Status', 'review-captain' ),
			array(
				ReviewStatus::PENDING->value  => ReviewStatus::PENDING->label(),
				ReviewStatus::APPROVED->value => ReviewStatus::APPROVED->label(),
			),
			(string) ( $draft['status'] ?? ReviewStatus::PENDING->value )
		);
		echo '</tbody></table>';
		echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Create entry', 'review-captain' ) . '</button></p>';
		echo '</form>';
	}

	/**
	 * Edit / detail screen.
	 *
	 * @param Review $review Review.
	 */
	private function render_edit( Review $review ): void {
		$external = $review->platform->is_external();
		$draft    = ReviewActions::take_draft();
		$val      = static fn( string $key, string $current ): string => array_key_exists( $key, $draft ) ? (string) $draft[ $key ] : $current;

		echo View::header( $external ? __( 'View review', 'review-captain' ) : __( 'Edit review', 'review-captain' ), View::page_url( self::SLUG ), __( '← Back to list', 'review-captain' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo Notices::render_current(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		echo '<div class="rc-admin-columns"><div class="rc-admin-main">';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo View::form_fields( 'save_review' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<input type="hidden" name="id" value="' . esc_attr( (string) $review->id ) . '" />';
		// Optimistic lock (TEC-005 R-12a): saving fails if someone else changed the content meanwhile.
		echo '<input type="hidden" name="revision" value="' . esc_attr( (string) $review->revision ) . '" />';

		if ( Kind::TESTIMONIAL === $review->kind ) {
			echo View::notice( 'info', __( 'Site testimonial (text testimonial) – not output as a platform review.', 'review-captain' ), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( $external ) {
			/* translators: %s: platform name */
			echo View::notice( 'info', sprintf( __( 'Content comes from %s and cannot be changed here. You can change the visibility (status), the profile, and the reference.', 'review-captain' ), $review->platform->label() ), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		echo '<table class="form-table" role="presentation"><tbody>';
		if ( $external ) {
			$this->row_static( __( 'Platform', 'review-captain' ), $review->platform->label() );
			$this->row_static( __( 'Original rating', 'review-captain' ), View::rating( $review ) );
			$this->row_static( __( 'Title', 'review-captain' ), '' !== $review->title ? $review->title : '–' );
			echo '<tr><th scope="row">' . esc_html__( 'Text', 'review-captain' ) . '</th><td><blockquote class="rc-admin-quote">' . nl2br( esc_html( $review->body ) ) . '</blockquote></td></tr>';
			$this->row_static( __( 'Name', 'review-captain' ), $review->author_name );
			$this->row_static( __( 'Date of original review', 'review-captain' ), View::date( $review->published_at ) );
			echo '<tr><th scope="row">' . esc_html__( 'Original link', 'review-captain' ) . '</th><td>';
			if ( null !== $review->source_url && Profiles::is_https( $review->source_url ) ) {
				echo '<a href="' . esc_url( $review->source_url ) . '" target="_blank" rel="noopener noreferrer nofollow">' . esc_html( $review->source_url ) . '</a>';
			} else {
				echo '–';
			}
			echo '</td></tr>';
			if ( ! empty( $review->meta['source_note'] ) ) {
				$this->row_static( __( 'Source note', 'review-captain' ), (string) $review->meta['source_note'] );
			}
			$profiles = array( '' => __( '– no profile –', 'review-captain' ) );
			foreach ( ( new Profiles() )->all( $review->platform->value ) as $profile ) {
				$profiles[ (string) $profile['id'] ] = (string) $profile['label'];
			}
			if ( null === $review->external_id ) {
				$this->row_select( 'profile_id', __( 'Profile', 'review-captain' ), $profiles, $val( 'profile_id', (string) $review->profile_id ) );
			}
		} else {
			echo '<tr><th scope="row"><label for="rc-rating_value">' . esc_html__( 'Rating', 'review-captain' ) . '</label></th><td>';
			echo '<select id="rc-rating_value" name="review[rating_value]"><option value="">' . esc_html__( '– no stars –', 'review-captain' ) . '</option>';
			$current = null !== $review->rating_value ? (string) (int) round( $review->rating_value ) : '';
			for ( $i = 5; $i >= 1; $i-- ) {
				/* translators: %d: rating value */
				echo '<option value="' . esc_attr( (string) $i ) . '"' . selected( (string) $i, $val( 'rating_value', $current ), false ) . '>' . esc_html( sprintf( __( '%d out of 5', 'review-captain' ), $i ) ) . '</option>';
			}
			echo '</select></td></tr>';
			$this->row_text( 'title', __( 'Title', 'review-captain' ), $val( 'title', $review->title ) );
			$this->row_textarea( 'body', __( 'Text', 'review-captain' ), $val( 'body', $review->body ), true );
			$this->row_text( 'author_name', __( 'Name', 'review-captain' ), $val( 'author_name', $review->author_name ), true );
			$this->row_date( 'published_at', __( 'Date', 'review-captain' ), $val( 'published_at', null !== $review->published_at ? substr( $review->published_at, 0, 10 ) : '' ) );
			$this->row_text( 'language', __( 'Language', 'review-captain' ), $val( 'language', $review->language ) );
		}
		$this->row_context( $val( 'context_type', (string) $review->context_type ), $val( 'context_id', (string) $review->context_id ) );
		echo '</tbody></table>';
		echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Save changes', 'review-captain' ) . '</button></p>';
		echo '</form>';

		$this->render_history( $review );
		echo '</div><div class="rc-admin-side">';
		$this->render_moderation_box( $review );
		$this->render_reports_box( $review );
		$this->render_tags_box( $review );
		$this->render_photo_box( $review );
		$this->render_origin_box( $review );
		echo '</div></div>';
	}

	/**
	 * Open public reports (RC-124).
	 *
	 * @param Review $review Review.
	 */
	private function render_reports_box( Review $review ): void {
		$reasons = ( new ReportStore() )->open_reasons( (int) $review->id );
		if ( ! $reasons ) {
			return;
		}
		$labels = ReportStore::reason_labels();
		echo '<div class="postbox" id="rc-reports"><div class="postbox-header"><h2>' . esc_html__( 'Open reports', 'review-captain' ) . '</h2></div><div class="inside"><ul>';
		foreach ( $reasons as $reason => $count ) {
			echo '<li>' . esc_html( ( $labels[ $reason ] ?? $reason ) . ': ' . number_format_i18n( $count ) ) . '</li>';
		}
		echo '</ul><p class="description">' . esc_html__( 'A status decision (e.g. approving again or rejecting) closes the reports. Nothing is stored about the reporters.', 'review-captain' ) . '</p></div></div>';
	}

	/**
	 * Internal tags (RC-124).
	 *
	 * @param Review $review Review.
	 */
	private function render_tags_box( Review $review ): void {
		if ( ! $this->plugin->settings()->get( 'submissions', 'tags_enabled', false ) ) {
			return;
		}
		$tags = ( new TagStore() )->for_review( (int) $review->id );
		echo '<div class="postbox" id="rc-tags"><div class="postbox-header"><h2>' . esc_html__( 'Internal tags', 'review-captain' ) . '</h2></div><div class="inside">';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo View::form_fields( 'save_tags' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<input type="hidden" name="id" value="' . esc_attr( (string) $review->id ) . '" />';
		echo '<p><label for="rc-tags-input">' . esc_html__( 'Tags (comma-separated)', 'review-captain' ) . '</label><br /><input type="text" id="rc-tags-input" name="tags" class="widefat" maxlength="500" value="' . esc_attr( implode( ', ', $tags ) ) . '" aria-describedby="rc-tags-desc" />';
		echo '<span class="description" id="rc-tags-desc">' . esc_html__( 'Visible internally only. Do not enter personal data.', 'review-captain' ) . '</span></p>';
		echo '<p><button type="submit" class="button">' . esc_html__( 'Save tags', 'review-captain' ) . '</button></p></form></div></div>';
	}

	/**
	 * Photo preview for moderators (RC-121).
	 *
	 * @param Review $review Review.
	 */
	private function render_photo_box( Review $review ): void {
		if ( null === $review->id || null === PhotoStore::for_review( $review->id ) ) {
			return;
		}
		echo '<div class="postbox" id="rc-photo"><div class="postbox-header"><h2>' . esc_html__( 'Photo', 'review-captain' ) . '</h2></div><div class="inside">';
		echo '<p><img src="' . esc_url( PhotoStore::url( $review->uuid ) ) . '" alt="' . esc_attr__( 'Review photo (moderation preview)', 'review-captain' ) . '" style="max-width:100%;height:auto" /></p>';
		echo '<p class="description">' . esc_html__( 'Publicly visible only after approval and when “Show photos” is enabled. Removed together with the review or on a privacy erasure.', 'review-captain' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo View::form_fields( 'delete_photo' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<input type="hidden" name="id" value="' . esc_attr( (string) $review->id ) . '" />';
		echo '<p><button type="submit" class="button">' . esc_html__( 'Delete photo', 'review-captain' ) . '</button></p></form></div></div>';
	}

	/**
	 * Moderation box (separate form, reason required for reject/archive).
	 *
	 * @param Review $review Review.
	 */
	private function render_moderation_box( Review $review ): void {
		echo '<div class="postbox" id="rc-moderation"><div class="postbox-header"><h2>' . esc_html__( 'Status', 'review-captain' ) . '</h2></div><div class="inside">';
		echo '<p>' . esc_html__( 'Current:', 'review-captain' ) . ' ' . View::pill( $review->status->label() ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$targets = $review->status->allowed_transitions();
		if ( ! $targets ) {
			echo '<p class="description">' . esc_html__( 'This entry was removed by the source and can no longer be moderated.', 'review-captain' ) . '</p></div></div>';
			return;
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo View::form_fields( 'review_status' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<input type="hidden" name="id" value="' . esc_attr( (string) $review->id ) . '" />';
		echo '<p><label for="rc-status-to">' . esc_html__( 'New status', 'review-captain' ) . '</label><br /><select id="rc-status-to" name="to">';
		if ( ReviewStatus::APPROVED === $review->status && ( new ReportStore() )->open_count( (int) $review->id ) > 0 ) {
			// Open reports below the threshold can be closed without hiding the review (review P2).
			echo '<option value="' . esc_attr( ReviewStatus::APPROVED->value ) . '">' . esc_html__( 'Keep approved, close reports', 'review-captain' ) . '</option>';
		}
		foreach ( $targets as $target ) {
			echo '<option value="' . esc_attr( $target->value ) . '">' . esc_html( $target->label() ) . '</option>';
		}
		echo '</select></p>';
		echo '<p><label for="rc-status-reason">' . esc_html__( 'Reason', 'review-captain' ) . '</label><br /><input type="text" id="rc-status-reason" name="reason" class="widefat" maxlength="500" aria-describedby="rc-status-reason-desc" />';
		echo '<span class="description" id="rc-status-reason-desc">' . esc_html__( 'Required for “Rejected” and “Archived”. Stored in the history – do not enter personal data.', 'review-captain' ) . '</span></p>';
		echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Change status', 'review-captain' ) . '</button></p>';
		echo '</form></div></div>';
	}

	/**
	 * Provenance box (read-only; verification is display only – RC-041).
	 *
	 * @param Review $review Review.
	 */
	private function render_origin_box( Review $review ): void {
		$origins = array(
			Origin::LOCAL_SUBMISSION->value => __( 'Form on this site', 'review-captain' ),
			Origin::MANUAL->value           => __( 'Created manually', 'review-captain' ),
			Origin::CSV_IMPORT->value       => __( 'CSV import', 'review-captain' ),
			Origin::PROVIDER_SYNC->value    => __( 'Sync', 'review-captain' ),
			Origin::CONNECTOR->value        => __( 'Connector', 'review-captain' ),
		);
		echo '<div class="postbox"><div class="postbox-header"><h2>' . esc_html__( 'Source details', 'review-captain' ) . '</h2></div><div class="inside"><dl class="rc-admin-dl">';
		$rows = array(
			__( 'Type', 'review-captain' )           => $review->kind->label(),
			__( 'Origin', 'review-captain' )      => $origins[ $review->origin->value ] ?? $review->origin->value,
			__( 'Platform', 'review-captain' )     => $review->platform->label(),
			__( 'Verification', 'review-captain' ) => $review->verification->label(),
			__( 'Created', 'review-captain' )      => View::date( $review->created_at, true ),
		);
		if ( Origin::LOCAL_SUBMISSION === $review->origin ) {
			$rows[ __( 'Consent', 'review-captain' ) ] = $review->consent_publish ? __( 'yes', 'review-captain' ) . ( null !== $review->consent_at ? ', ' . View::date( $review->consent_at ) : '' ) : __( 'no', 'review-captain' );
			if ( null !== $review->author_email ) {
				$rows[ __( 'Contact (private)', 'review-captain' ) ] = $review->author_email;
			}
		}
		if ( null !== $review->import_batch_id ) {
			$rows[ __( 'Import batch', 'review-captain' ) ] = '#' . $review->import_batch_id;
		}
		foreach ( $rows as $label => $value ) {
			echo '<dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd>';
		}
		echo '</dl><p class="description">' . esc_html__( '“Verified purchase” and “Attendance confirmed” are set only by a connected source; this is not possible manually.', 'review-captain' ) . '</p></div></div>';
	}

	/**
	 * Moderation history.
	 *
	 * @param Review $review Review.
	 */
	private function render_history( Review $review ): void {
		$history = $this->plugin->reviews()->history( (int) $review->id );
		echo '<h2>' . esc_html__( 'Change history', 'review-captain' ) . '</h2>';
		if ( ! $history ) {
			echo '<p>' . esc_html__( 'No entries yet.', 'review-captain' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Time', 'review-captain' ) . '</th><th scope="col">' . esc_html__( 'Action', 'review-captain' ) . '</th><th scope="col">' . esc_html__( 'Reason', 'review-captain' ) . '</th><th scope="col">' . esc_html__( 'User', 'review-captain' ) . '</th></tr></thead><tbody>';
		foreach ( $history as $event ) {
			$from   = ReviewStatus::tryFrom( (string) ( $event['from_status'] ?? '' ) );
			$to     = ReviewStatus::tryFrom( (string) ( $event['to_status'] ?? '' ) );
			$action = 'created' === $event['action'] ? __( 'created', 'review-captain' ) . ( $to ? ' (' . $to->label() . ')' : '' ) : ( $from && $to ? $from->label() . ' → ' . $to->label() : (string) $event['action'] );
			$user   = ! empty( $event['user_id'] ) ? get_userdata( (int) $event['user_id'] ) : false;
			echo '<tr><td>' . esc_html( View::date( (string) $event['created_at'], true ) ) . '</td><td>' . esc_html( $action ) . '</td><td>' . esc_html( (string) $event['reason'] ) . '</td><td>' . esc_html( $user ? (string) $user->display_name : __( 'System', 'review-captain' ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Row: text input.
	 *
	 * @param string $key      Field key.
	 * @param string $label    Label.
	 * @param string $value    Value.
	 * @param bool   $required Required.
	 * @param string $help     Help text.
	 * @param string $type     Input type.
	 */
	private function row_text( string $key, string $label, string $value, bool $required = false, string $help = '', string $type = 'text' ): void {
		$id = 'rc-' . $key;
		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . ( $required ? ' <span class="required">*</span>' : '' ) . '</label></th><td>';
		echo '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="review[' . esc_attr( $key ) . ']" class="regular-text" value="' . esc_attr( $value ) . '"' . ( $required ? ' required' : '' ) . ( 'url' === $type ? ' pattern="https://.+"' : '' ) . ' />';
		if ( '' !== $help ) {
			echo '<p class="description">' . esc_html( $help ) . '</p>';
		}
		echo '</td></tr>';
	}

	/**
	 * Row: date input.
	 *
	 * @param string $key      Key.
	 * @param string $label    Label.
	 * @param string $value    Y-m-d.
	 * @param bool   $required Required.
	 */
	private function row_date( string $key, string $label, string $value, bool $required = false ): void {
		$this->row_text( $key, $label, $value, $required, '', 'date' );
	}

	/**
	 * Row: textarea.
	 *
	 * @param string $key      Key.
	 * @param string $label    Label.
	 * @param string $value    Value.
	 * @param bool   $required Required.
	 */
	private function row_textarea( string $key, string $label, string $value, bool $required = false ): void {
		$id = 'rc-' . $key;
		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . ( $required ? ' <span class="required">*</span>' : '' ) . '</label></th><td>';
		echo '<textarea id="' . esc_attr( $id ) . '" name="review[' . esc_attr( $key ) . ']" rows="6" class="large-text"' . ( $required ? ' required' : '' ) . '>' . esc_textarea( $value ) . '</textarea></td></tr>';
	}

	/**
	 * Row: select.
	 *
	 * @param string                $key      Key.
	 * @param string                $label    Label.
	 * @param array<string, string> $choices  Choices.
	 * @param string                $current  Current.
	 * @param bool                  $required Required.
	 */
	private function row_select( string $key, string $label, array $choices, string $current, bool $required = false ): void {
		$id = 'rc-' . $key;
		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . ( $required ? ' <span class="required">*</span>' : '' ) . '</label></th><td>';
		echo '<select id="' . esc_attr( $id ) . '" name="review[' . esc_attr( $key ) . ']"' . ( $required ? ' required' : '' ) . '>';
		if ( $required ) {
			echo '<option value="">' . esc_html__( '– please select –', 'review-captain' ) . '</option>';
		}
		foreach ( $choices as $value => $text ) {
			echo '<option value="' . esc_attr( (string) $value ) . '"' . selected( (string) $value, $current, false ) . '>' . esc_html( (string) $text ) . '</option>';
		}
		echo '</select></td></tr>';
	}

	/**
	 * Row: context selection.
	 *
	 * @param string $type Context type.
	 * @param string $id   Context ID.
	 */
	private function row_context( string $type, string $id ): void {
		echo '<tr><th scope="row"><label for="rc-context_type">' . esc_html__( 'Reference', 'review-captain' ) . '</label></th><td>';
		echo '<select id="rc-context_type" name="review[context_type]">';
		foreach ( self::context_choices() as $value => $text ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $value, $type, false ) . '>' . esc_html( $text ) . '</option>';
		}
		echo '</select> <label class="screen-reader-text" for="rc-context_id">' . esc_html__( 'Reference ID', 'review-captain' ) . '</label>';
		echo '<input type="text" id="rc-context_id" name="review[context_id]" class="regular-text" value="' . esc_attr( $id ) . '" placeholder="' . esc_attr__( 'ID, e.g. post or product ID', 'review-captain' ) . '" />';
		echo '<p class="description">' . esc_html__( 'Optional: Assigns the entry to a page, product, or event (for widgets with filters).', 'review-captain' ) . '</p></td></tr>';
	}

	/**
	 * Row: read-only value.
	 *
	 * @param string $label Label.
	 * @param string $value Value.
	 */
	private function row_static( string $label, string $value ): void {
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
	}
}
