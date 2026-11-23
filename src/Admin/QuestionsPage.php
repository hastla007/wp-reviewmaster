<?php
/**
 * Admin moderation of product questions (RC-122).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Admin;

use ReviewCaptain\Plugin;
use ReviewCaptain\Questions\QuestionRepository;
use ReviewCaptain\Security\Capabilities;

/**
 * Lists questions by status; answer, approve, reject or delete. Only registered while
 * `submissions.qa_enabled` is on.
 */
final class QuestionsPage {

	public const SLUG = 'review-captain-questions';

	/**
	 * Constructor.
	 *
	 * @param Plugin                  $plugin    Plugin.
	 * @param QuestionRepository|null $questions Repository.
	 */
	public function __construct( private Plugin $plugin, private ?QuestionRepository $questions = null ) {
		$this->questions = $questions ?? new QuestionRepository();
	}

	/**
	 * Repository.
	 */
	private function repo(): QuestionRepository {
		return $this->questions ?? new QuestionRepository();
	}

	/**
	 * Status labels.
	 *
	 * @return array<string, string>
	 */
	public static function status_labels(): array {
		return array(
			'pending'  => __( 'Awaiting moderation', 'review-captain' ),
			'approved' => __( 'Approved', 'review-captain' ),
			'rejected' => __( 'Rejected', 'review-captain' ),
		);
	}

	/**
	 * Page callback.
	 */
	public function render(): void {
		if ( ! current_user_can( Capabilities::MODERATE ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'review-captain' ), '', array( 'response' => 403 ) );
		}
		$status = View::req( $_GET, 'status', 'pending' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$labels = self::status_labels();
		$counts = $this->repo()->counts();
		echo '<div class="wrap rc-admin">';
		echo View::header( __( 'Questions & answers', 'review-captain' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo Notices::render_current(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<p class="description">' . esc_html__( 'Questions are separate from reviews: no stars, not counted in review summaries, no schema markup. Output with [review_captain_qa v="1" context_type="product" context_id="current"].', 'review-captain' ) . '</p>';
		echo '<ul class="subsubsub">';
		$links = array();
		foreach ( $labels as $key => $label ) {
			$links[] = '<li><a href="' . esc_url( View::page_url( self::SLUG, array( 'status' => $key ) ) ) . '"' . ( $status === $key ? ' class="current" aria-current="page"' : '' ) . '>' . esc_html( $label ) . ' <span class="count">(' . esc_html( number_format_i18n( $counts[ $key ] ?? 0 ) ) . ')</span></a></li>';
		}
		echo implode( ' | ', $links ) . '</ul>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$rows = $this->repo()->query( isset( $labels[ $status ] ) ? $status : 'pending', 50 );
		if ( ! $rows ) {
			echo '<p style="clear:both">' . esc_html__( 'No questions in this view.', 'review-captain' ) . '</p></div>';
			return;
		}
		echo '<table class="widefat striped" style="clear:both"><thead><tr><th scope="col">' . esc_html__( 'Question', 'review-captain' ) . '</th><th scope="col">' . esc_html__( 'Reference', 'review-captain' ) . '</th><th scope="col">' . esc_html__( 'Answer and moderation', 'review-captain' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$id = (int) $row['id'];
			echo '<tr><td><p>' . esc_html( (string) $row['question'] ) . '</p><p class="description">' . esc_html( (string) $row['author_name'] . ' · ' . View::date( (string) $row['created_at'], true ) ) . '</p></td>';
			echo '<td>' . esc_html( (string) $row['context_type'] . ' #' . (string) $row['context_id'] ) . '</td><td>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			echo View::form_fields( 'moderate_question' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<input type="hidden" name="id" value="' . esc_attr( (string) $id ) . '" /><input type="hidden" name="from" value="' . esc_attr( (string) $row['status'] ) . '" />';
			echo '<label class="screen-reader-text" for="rc-answer-' . esc_attr( (string) $id ) . '">' . esc_html__( 'Answer', 'review-captain' ) . '</label>';
			echo '<textarea id="rc-answer-' . esc_attr( (string) $id ) . '" name="answer" rows="3" class="widefat" maxlength="2000">' . esc_textarea( (string) ( $row['answer'] ?? '' ) ) . '</textarea>';
			echo '<p><select name="to" aria-label="' . esc_attr__( 'Status', 'review-captain' ) . '">';
			foreach ( $labels as $key => $label ) {
				echo '<option value="' . esc_attr( $key ) . '"' . selected( $key, (string) $row['status'], false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select> <button type="submit" class="button button-primary">' . esc_html__( 'Save', 'review-captain' ) . '</button> ';
			echo '<button type="submit" name="delete" value="1" class="button button-link-delete">' . esc_html__( 'Delete', 'review-captain' ) . '</button></p></form></td></tr>';
		}
		echo '</tbody></table></div>';
	}

	/**
	 * Handler: answer/moderate/delete (admin-post: review_captain_moderate_question).
	 */
	public function handle(): void {
		Capabilities::verify_request( Capabilities::MODERATE, 'moderate_question' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above.
		$id     = absint( $_POST['id'] ?? 0 );
		$from   = sanitize_key( wp_unslash( (string) ( $_POST['from'] ?? '' ) ) );
		$to     = sanitize_key( wp_unslash( (string) ( $_POST['to'] ?? '' ) ) );
		$answer = sanitize_textarea_field( wp_unslash( (string) ( $_POST['answer'] ?? '' ) ) );
		$delete = ! empty( $_POST['delete'] );
		// phpcs:enable
		$back = View::page_url( self::SLUG, array( 'status' => $from ) );
		$row  = $this->repo()->find( $id );
		if ( null === $row ) {
			View::redirect( Notices::url( $back, 'question_failed' ) );
			return;
		}
		if ( $delete ) {
			$this->repo()->delete( $id );
			$this->plugin->audit()->record( 'question.deleted', 'question', $id, array() );
			View::redirect( Notices::url( $back, 'question_saved' ) );
			return;
		}
		$this->repo()->answer( $id, mb_substr( $answer, 0, 2000 ), get_current_user_id() );
		$ok = $this->repo()->change_status( $id, $from, $to );
		$this->plugin->audit()->record(
			'question.moderated',
			'question',
			$id,
			array(
				'from' => $from,
				'to'   => $to,
			)
		);
		View::redirect( Notices::url( $back, $ok ? 'question_saved' : 'question_failed' ) );
	}
}
