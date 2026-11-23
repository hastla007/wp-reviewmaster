<?php
/**
 * Sources & profiles page (Core: manual profiles / review links).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Admin;

use ReviewCaptain\Domain\Platform;
use ReviewCaptain\Plugin;
use ReviewCaptain\Security\Capabilities;

/**
 * Lists, creates, edits and deletes profiles. API connections are a locked Pro hint.
 */
final class SourcesPage {

	public const SLUG = 'review-captain-sources';

	/**
	 * Constructor.
	 *
	 * @param Plugin   $plugin   Plugin.
	 * @param Profiles $profiles Profiles.
	 */
	public function __construct( private Plugin $plugin, private Profiles $profiles = new Profiles() ) {}

	/**
	 * Page callback.
	 */
	public function render(): void {
		if ( ! current_user_can( Capabilities::MANAGE_SOURCES ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'review-captain' ), '', array( 'response' => 403 ) );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- routing only.
		$action = View::req( $_GET, 'action' );
		$id     = absint( View::req( $_GET, 'id' ) );
		// phpcs:enable
		echo '<div class="wrap rc-admin">';
		if ( 'edit' === $action || 'new' === $action ) {
			$this->render_form( 'edit' === $action ? $this->profiles->find( $id ) : null );
		} elseif ( 'delete' === $action && null !== ( $profile = $this->profiles->find( $id ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.Found
			$this->render_delete_confirm( $profile );
		} else {
			$this->render_list();
		}
		echo '</div>';
	}

	/**
	 * List.
	 */
	private function render_list(): void {
		echo View::header( __( 'Sources & profiles', 'review-captain' ), View::page_url( self::SLUG, array( 'action' => 'new' ) ), __( 'Add profile', 'review-captain' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo Notices::render_current(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<p>' . esc_html__( 'Add public profile links for your review platforms. Visitors can review you there via “Write a review”; manually added reviews can be assigned to a profile. No scraping.', 'review-captain' ) . '</p>';

		$rows = $this->profiles->all();
		if ( ! $rows ) {
			echo '<div class="rc-admin-empty"><p>' . esc_html__( 'No sources yet. Add a profile link so visitors can review you on Google and other platforms.', 'review-captain' ) . '</p>';
			echo '<p><a class="button button-primary" href="' . esc_url( View::page_url( self::SLUG, array( 'action' => 'new' ) ) ) . '">' . esc_html__( 'Add profile', 'review-captain' ) . '</a></p></div>';
		} else {
			echo '<table class="widefat striped rc-admin-profiles"><thead><tr>';
			foreach ( array( __( 'Platform', 'review-captain' ), __( 'Label', 'review-captain' ), __( 'Profile link', 'review-captain' ), __( '“Write a review” link', 'review-captain' ), __( 'Status', 'review-captain' ), __( 'Reviews', 'review-captain' ) ) as $head ) {
				echo '<th scope="col">' . esc_html( $head ) . '</th>';
			}
			echo '</tr></thead><tbody>';
			foreach ( $rows as $row ) {
				$id       = (int) $row['id'];
				$platform = Platform::tryFrom( (string) $row['platform'] );
				$edit     = View::page_url( self::SLUG, array( 'action' => 'edit', 'id' => $id ) );
				$delete   = View::page_url( self::SLUG, array( 'action' => 'delete', 'id' => $id ) );
				echo '<tr><td>' . esc_html( $platform ? $platform->label() : (string) $row['platform'] ) . '</td>';
				echo '<td><strong><a href="' . esc_url( $edit ) . '">' . esc_html( (string) $row['label'] ) . '</a></strong>';
				echo '<div class="row-actions"><span class="edit"><a href="' . esc_url( $edit ) . '">' . esc_html__( 'Edit', 'review-captain' ) . '</a> | </span><span class="delete"><a class="submitdelete" href="' . esc_url( $delete ) . '">' . esc_html__( 'Delete', 'review-captain' ) . '</a></span></div></td>';
				echo '<td>' . $this->link( (string) ( $row['profile_url'] ?? '' ) ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '<td>' . $this->link( (string) ( $row['review_url'] ?? '' ) ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '<td>' . View::pill( 'manual' === $row['status'] ? __( 'Manual / link only', 'review-captain' ) : (string) $row['status'], 'manual' === $row['status'] ? 'neutral' : ( empty( $row['last_error_code'] ) ? 'ok' : 'warning' ) ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '<td>' . esc_html( number_format_i18n( $this->profiles->review_count( $id ) ) ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}

		if ( SettingsHints::enabled( $this->plugin->settings() ) ) {
			echo '<div class="card rc-admin-pro-card"><h2>' . esc_html__( 'API connection', 'review-captain' ) . '</h2>';
			echo '<p>' . esc_html__( 'Automatic sync via official APIs (e.g. Google Business Profile, Trustpilot) requires an authorized owner account.', 'review-captain' ) . '</p>';
			echo '<p>' . View::pro_lock( __( 'API connection in Review Captain Pro', 'review-captain' ) ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Escaped external link or dash.
	 *
	 * @param string $url URL.
	 */
	private function link( string $url ): string {
		if ( '' === $url || ! Profiles::is_https( $url ) ) {
			return '–';
		}
		return '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( View::excerpt( $url, 50 ) ) . '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'review-captain' ) . '</span></a>';
	}

	/**
	 * Create/edit form.
	 *
	 * @param array<string, mixed>|null $profile Profile or null for new.
	 */
	private function render_form( ?array $profile ): void {
		$draft = get_transient( 'review_captain_profile_draft_' . get_current_user_id() );
		delete_transient( 'review_captain_profile_draft_' . get_current_user_id() );
		$data = is_array( $draft ) ? $draft : ( $profile ?? array() );

		echo View::header( null === $profile ? __( 'Add profile', 'review-captain' ) : __( 'Edit profile', 'review-captain' ), View::page_url( self::SLUG ), __( '← Back to overview', 'review-captain' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo Notices::render_current(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo View::form_fields( 'save_profile' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( null !== $profile ) {
			echo '<input type="hidden" name="id" value="' . esc_attr( (string) $profile['id'] ) . '" />';
		}
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row"><label for="rc-p-platform">' . esc_html__( 'Platform', 'review-captain' ) . ' <span class="required">*</span></label></th><td><select id="rc-p-platform" name="profile[platform]" required>';
		echo '<option value="">' . esc_html__( '– please select –', 'review-captain' ) . '</option>';
		foreach ( ReviewsPage::platform_choices( true ) as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $value, (string) ( $data['platform'] ?? '' ), false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';
		$fields = array(
			'label'       => array( __( 'Label', 'review-captain' ), __( 'E.g. “Downtown branch” – only visible internally and in widgets.', 'review-captain' ), 'text' ),
			'profile_url' => array( __( 'Profile link', 'review-captain' ), __( 'Public profile page (https). Linked as the source.', 'review-captain' ), 'url' ),
			'review_url'  => array( __( '“Write a review” link', 'review-captain' ), __( 'Direct link to the platform’s review form (https), for buttons on your website.', 'review-captain' ), 'url' ),
		);
		foreach ( $fields as $key => [ $label, $help, $type ] ) {
			$id = 'rc-p-' . $key;
			echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . ( 'label' === $key ? ' <span class="required">*</span>' : '' ) . '</label></th><td>';
			echo '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="profile[' . esc_attr( $key ) . ']" class="regular-text" value="' . esc_attr( (string) ( $data[ $key ] ?? '' ) ) . '"' . ( 'url' === $type ? ' pattern="https://.+"' : ' required' ) . ' />';
			echo '<p class="description">' . esc_html( $help ) . '</p></td></tr>';
		}
		$pro_hint = SettingsHints::enabled( $this->plugin->settings() ) ? '<p>' . View::pro_lock( __( 'API connection in Review Captain Pro', 'review-captain' ) ) . '</p>' : '';
		echo '<tr><th scope="row">' . esc_html__( 'Connection', 'review-captain' ) . '</th><td>' . esc_html__( 'Manual (link only)', 'review-captain' ) . $pro_hint . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</tbody></table>';
		echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Save profile', 'review-captain' ) . '</button></p></form>';
	}

	/**
	 * Delete confirmation.
	 *
	 * @param array<string, mixed> $profile Profile.
	 */
	private function render_delete_confirm( array $profile ): void {
		$count = $this->profiles->review_count( (int) $profile['id'] );
		echo View::header( __( 'Delete profile', 'review-captain' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		/* translators: 1: profile label, 2: number of reviews */
		echo '<p>' . esc_html( sprintf( __( 'Delete the profile “%1$s”? %2$s assigned reviews are kept and will not be deleted.', 'review-captain' ), (string) $profile['label'], number_format_i18n( $count ) ) ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo View::form_fields( 'delete_profile' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<input type="hidden" name="id" value="' . esc_attr( (string) $profile['id'] ) . '" />';
		echo '<p><label><input type="checkbox" name="confirm" value="1" required /> ' . esc_html__( 'Yes, delete profile', 'review-captain' ) . '</label></p>';
		echo '<p><button type="submit" class="button button-link-delete">' . esc_html__( 'Delete permanently', 'review-captain' ) . '</button> <a class="button" href="' . esc_url( View::page_url( self::SLUG ) ) . '">' . esc_html__( 'Cancel', 'review-captain' ) . '</a></p></form>';
	}

	/**
	 * Handler: save profile.
	 */
	public function handle_save(): void {
		Capabilities::verify_request( Capabilities::MANAGE_SOURCES, 'save_profile' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above.
		$input = isset( $_POST['profile'] ) && is_array( $_POST['profile'] ) ? wp_unslash( $_POST['profile'] ) : array();
		$id    = absint( $_POST['id'] ?? 0 );
		// phpcs:enable
		$result = $this->profiles->save( $input, $id > 0 ? $id : null );
		if ( $result['errors'] ) {
			$draft = array();
			foreach ( array( 'platform', 'label', 'profile_url', 'review_url' ) as $key ) {
				$draft[ $key ] = sanitize_text_field( (string) ( $input[ $key ] ?? '' ) );
			}
			set_transient( 'review_captain_profile_draft_' . get_current_user_id(), $draft, 5 * MINUTE_IN_SECONDS );
			$back = View::page_url( self::SLUG, $id > 0 ? array( 'action' => 'edit', 'id' => $id ) : array( 'action' => 'new' ) );
			View::redirect( Notices::url( $back, 'profile_invalid', $result['errors'] ) );
			return;
		}
		$this->plugin->audit()->record( $id > 0 ? 'profile.updated' : 'profile.created', 'profile', (int) $result['id'], array( 'platform' => sanitize_key( (string) ( $input['platform'] ?? '' ) ) ) );
		View::redirect( Notices::url( View::page_url( self::SLUG ), 'profile_saved' ) );
	}

	/**
	 * Handler: delete profile (requires explicit confirmation).
	 */
	public function handle_delete(): void {
		Capabilities::verify_request( Capabilities::MANAGE_SOURCES, 'delete_profile' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above.
		$id      = absint( $_POST['id'] ?? 0 );
		$confirm = ! empty( $_POST['confirm'] );
		// phpcs:enable
		if ( ! $confirm ) {
			View::redirect( Notices::url( View::page_url( self::SLUG, array( 'action' => 'delete', 'id' => $id ) ), 'confirm_required' ) );
			return;
		}
		if ( $this->profiles->delete( $id ) ) {
			$this->plugin->audit()->record( 'profile.deleted', 'profile', $id );
		}
		View::redirect( Notices::url( View::page_url( self::SLUG ), 'profile_deleted' ) );
	}
}
