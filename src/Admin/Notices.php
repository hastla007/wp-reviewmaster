<?php
/**
 * Status notices after redirects (PRG pattern).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Admin;

/**
 * Maps the `rc_notice` query parameter to a translated notice. Only known codes render,
 * so the parameter can never inject text.
 */
final class Notices {

	public const PARAM        = 'rc_notice';
	public const PARAM_FIELDS = 'rc_fields';
	public const PARAM_COUNT  = 'rc_count';

	/**
	 * Add a status code (and optional field list/count) to a redirect URL.
	 *
	 * @param string       $url    URL.
	 * @param string       $code   Code.
	 * @param list<string> $fields Field keys / error codes.
	 * @param int|null     $count  Count.
	 */
	public static function url( string $url, string $code, array $fields = array(), ?int $count = null ): string {
		$args = array( self::PARAM => $code );
		if ( $fields ) {
			$args[ self::PARAM_FIELDS ] = implode( ',', array_map( 'sanitize_key', $fields ) );
		}
		if ( null !== $count ) {
			$args[ self::PARAM_COUNT ] = $count;
		}
		return add_query_arg( $args, $url );
	}

	/**
	 * Known codes.
	 *
	 * @return array<string, array{0: string, 1: string}> code => [type, message]
	 */
	public static function messages(): array {
		return array(
			'saved'                => array( 'success', __( 'Settings saved.', 'review-captain' ) ),
			'saved_invalid'        => array( 'error', __( 'Some values were not saved because they are invalid. All other changes are saved. Please check:', 'review-captain' ) ),
			'unchanged'            => array( 'info', __( 'No changes – nothing was saved.', 'review-captain' ) ),
			'tab_forbidden'        => array( 'error', __( 'You do not have permission for this section. Nothing was saved.', 'review-captain' ) ),
			'reset_done'           => array( 'success', __( 'Settings have been reset to defaults. Reviews, profiles, and history remain unchanged. The previous state was backed up.', 'review-captain' ) ),
			'confirm_required'     => array( 'error', __( 'Please confirm the action using the checkbox.', 'review-captain' ) ),
			'import_preview'       => array( 'info', __( 'Preview created. Check the changes and confirm the import.', 'review-captain' ) ),
			'import_invalid'       => array( 'error', __( 'The file could not be imported: not a valid Review Captain configuration export or unsupported version.', 'review-captain' ) ),
			'import_empty'         => array( 'info', __( 'The import contains no changes compared to the current settings.', 'review-captain' ) ),
			'import_expired'       => array( 'error', __( 'The import preview has expired. Please upload the file again.', 'review-captain' ) ),
			'import_applied'       => array( 'success', __( 'Configuration imported. The previous state was backed up and can be restored. Credentials may need to be reconnected.', 'review-captain' ) ),
			'import_discarded'     => array( 'info', __( 'Import discarded. Nothing was changed.', 'review-captain' ) ),
			'rollback_done'        => array( 'success', __( 'The backed-up settings have been restored.', 'review-captain' ) ),
			'rollback_missing'     => array( 'error', __( 'No backup is available.', 'review-captain' ) ),
			'review_created'       => array( 'success', __( 'Entry created.', 'review-captain' ) ),
			'review_saved'         => array( 'success', __( 'Changes saved.', 'review-captain' ) ),
			'review_unchanged'     => array( 'info', __( 'No changes.', 'review-captain' ) ),
			'review_invalid'       => array( 'error', __( 'The entry was not saved. Please check:', 'review-captain' ) ),
			'review_missing'       => array( 'error', __( 'The entry was not found.', 'review-captain' ) ),
			'review_conflict'      => array( 'error', __( 'The entry has since been changed by someone else. Your changes were not saved – please check the current state and save again.', 'review-captain' ) ),
			'tags_saved'           => array( 'success', __( 'Tags saved.', 'review-captain' ) ),
			'photo_deleted'        => array( 'success', __( 'Photo deleted.', 'review-captain' ) ),
			'question_saved'       => array( 'success', __( 'Question updated.', 'review-captain' ) ),
			'question_failed'      => array( 'error', __( 'The question was not changed (not found or changed in the meantime).', 'review-captain' ) ),
			'campaign_saved'       => array( 'success', __( 'Feedback link created.', 'review-captain' ) ),
			'campaign_invalid'     => array( 'error', __( 'The feedback link was not created. Please check:', 'review-captain' ) ),
			'campaign_deleted'     => array( 'success', __( 'Feedback link deleted.', 'review-captain' ) ),
			'status_changed'       => array( 'success', __( 'Status changed.', 'review-captain' ) ),
			'status_failed'        => array( 'error', __( 'This status change is not allowed or the entry has been changed in the meantime.', 'review-captain' ) ),
			'reason_required'      => array( 'error', __( 'Please provide a reason for rejecting or archiving.', 'review-captain' ) ),
			'bulk_done'            => array( 'success', __( 'Bulk action completed. Changed entries:', 'review-captain' ) ),
			'bulk_none'            => array( 'warning', __( 'No entries changed (nothing selected or status change not allowed).', 'review-captain' ) ),
			'profile_saved'        => array( 'success', __( 'Profile saved.', 'review-captain' ) ),
			'profile_invalid'      => array( 'error', __( 'The profile was not saved. Please check:', 'review-captain' ) ),
			'profile_deleted'      => array( 'success', __( 'Profile deleted. Assigned reviews are kept.', 'review-captain' ) ),
			'checklist_updated'    => array( 'success', __( 'Setup step updated.', 'review-captain' ) ),
		);
	}

	/**
	 * Human readable labels for error/field codes shown after a notice.
	 *
	 * @param string $code Code.
	 */
	public static function detail_label( string $code ): string {
		$map = array(
			'missing_platform'             => __( 'Platform missing', 'review-captain' ),
			'missing_rating'               => __( 'Original rating missing', 'review-captain' ),
			'missing_body'                 => __( 'Text missing', 'review-captain' ),
			'missing_date'                 => __( 'Date missing or invalid', 'review-captain' ),
			'missing_source_url'           => __( 'Profile/original link missing', 'review-captain' ),
			'source_url_not_https'         => __( 'Link must start with https://', 'review-captain' ),
			'missing_source_note'          => __( 'Source attribution missing', 'review-captain' ),
			'missing_author'               => __( 'Name missing', 'review-captain' ),
			'rating_out_of_scale'          => __( 'Rating is outside the scale', 'review-captain' ),
			'invalid_scale'                => __( 'Invalid scale', 'review-captain' ),
			'empty_review'                 => __( 'Text or rating missing', 'review-captain' ),
			'invalid_context_type'         => __( 'Invalid context', 'review-captain' ),
			'testimonial_must_be_local'    => __( 'Testimonials are always site-owned', 'review-captain' ),
			'external_verification_on_local' => __( 'Verification does not match the source', 'review-captain' ),
			'missing_label'                => __( 'Label missing', 'review-captain' ),
			'invalid_platform'             => __( 'Invalid platform', 'review-captain' ),
			'profile_url_not_https'        => __( 'Profile URL must start with https://', 'review-captain' ),
			'review_url_not_https'         => __( '“Leave a review” link must start with https://', 'review-captain' ),
			'missing_url'                  => __( 'Enter at least one URL', 'review-captain' ),
			'save_failed'                  => __( 'Saving failed', 'review-captain' ),
			'reason_required'              => __( 'Reason missing', 'review-captain' ),
			'label'                        => __( 'Label missing or too long (maximum 80 characters)', 'review-captain' ),
			'profile'                      => __( 'Profile not found or has no https review link', 'review-captain' ),
			'target_url'                   => __( 'Target page must be an https address on this site', 'review-captain' ),
			'whatsapp_number'              => __( 'WhatsApp number: 8–15 digits in international format without a leading 0', 'review-captain' ),
			'whatsapp_text'                => __( 'WhatsApp text is too long (maximum 300 characters)', 'review-captain' ),
			'limit'                        => __( 'Maximum number of feedback links reached', 'review-captain' ),
		);
		if ( isset( $map[ $code ] ) ) {
			return $map[ $code ];
		}
		// Settings fields are passed as "tab-key".
		if ( str_contains( $code, '-' ) ) {
			[ $tab, $key ] = explode( '-', $code, 2 );
			return SettingsFields::label( $tab, $key );
		}
		return $code;
	}

	/**
	 * Render the notice for the current request, if any.
	 */
	public static function render_current(): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only display of a status code.
		$code = isset( $_GET[ self::PARAM ] ) ? sanitize_key( wp_unslash( (string) $_GET[ self::PARAM ] ) ) : '';
		if ( '' === $code ) {
			return '';
		}
		$fields = isset( $_GET[ self::PARAM_FIELDS ] ) ? array_filter( array_map( 'sanitize_key', explode( ',', wp_unslash( (string) $_GET[ self::PARAM_FIELDS ] ) ) ) ) : array();
		$count  = isset( $_GET[ self::PARAM_COUNT ] ) ? absint( $_GET[ self::PARAM_COUNT ] ) : null;
		// phpcs:enable
		return self::render( $code, array_values( $fields ), $count );
	}

	/**
	 * Render a notice.
	 *
	 * @param string       $code   Code.
	 * @param list<string> $fields Details.
	 * @param int|null     $count  Count.
	 */
	public static function render( string $code, array $fields = array(), ?int $count = null ): string {
		$messages = self::messages();
		if ( ! isset( $messages[ $code ] ) ) {
			return '';
		}
		[ $type, $message ] = $messages[ $code ];
		if ( null !== $count ) {
			$message .= ' ' . number_format_i18n( $count );
		}
		$html = '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible" role="' . ( 'error' === $type ? 'alert' : 'status' ) . '"><p>' . esc_html( $message ) . '</p>';
		if ( $fields ) {
			$html .= '<ul class="rc-admin-notice-list">';
			foreach ( $fields as $field ) {
				$html .= '<li>' . esc_html( self::detail_label( $field ) ) . '</li>';
			}
			$html .= '</ul>';
		}
		return $html . '</div>';
	}
}
