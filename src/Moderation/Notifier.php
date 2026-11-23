<?php
/**
 * Moderation notifications for new reviews, public reports and questions (SET-RC-09, SET-RC-26).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Moderation;

use ReviewCaptain\Settings\Settings;
use ReviewCaptain\Settings\SettingsSchema;

/**
 * Sends a short e-mail with a link to the moderation screen. By default the mail never contains
 * review or question text and never contact data of the author or reporter.
 *
 * Recipients: submissions.notify_recipients (empty = admin_email). Subject/body of the review
 * mail: submissions.notify_subject / notify_body (empty = default text) with the placeholders
 * {site}, {moderation_url} and {status}. Filters `review_captain_notify_recipients` and
 * `review_captain_notification` win over the settings.
 */
final class Notifier {

	public const EVENT_REVIEW   = 'review';
	public const EVENT_REPORT   = 'report';
	public const EVENT_QUESTION = 'question';

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings.
	 */
	public function __construct( private Settings $settings ) {}

	/**
	 * Whether the event triggers a mail according to the settings.
	 *
	 * @param string $event Event.
	 */
	public function enabled( string $event ): bool {
		return match ( $event ) {
			self::EVENT_REVIEW   => (bool) $this->settings->get( 'submissions', 'notify_admin', true ),
			self::EVENT_REPORT   => 'recipients' === $this->settings->get( 'submissions', 'report_notify', 'recipients' ),
			self::EVENT_QUESTION => (bool) $this->settings->get( 'submissions', 'notify_questions', true ),
			default              => false,
		};
	}

	/**
	 * Effective recipients (valid addresses only).
	 *
	 * @param string $event Event.
	 * @return list<string>
	 */
	public function recipients( string $event ): array {
		$list = (array) $this->settings->get( 'submissions', 'notify_recipients', array() );
		if ( ! $list ) {
			$list = array( (string) get_option( 'admin_email', '' ) );
		}
		/**
		 * Filter the recipients of a moderation notification.
		 *
		 * @param list<string> $list  Addresses.
		 * @param string       $event review|report|question.
		 */
		$list = apply_filters( 'review_captain_notify_recipients', $list, $event );
		return SettingsSchema::sanitize_email_list(
			array(
				'default'   => array(),
				'max_items' => 50,
			),
			is_array( $list ) ? $list : array()
		);
	}

	/**
	 * Send the notification of an event if enabled.
	 *
	 * @param string $event   Event (EVENT_*).
	 * @param array  $context Keys: moderation_url (string), approved (bool, reviews), flagged (bool, reports), reason (string, reports).
	 * @return bool Whether a mail was handed to wp_mail().
	 */
	public function notify( string $event, array $context ): bool {
		if ( ! $this->enabled( $event ) ) {
			return false;
		}
		$to = $this->recipients( $event );
		if ( ! $to ) {
			return false;
		}
		$mail = array_merge( array( 'to' => $to ), $this->message( $event, $context ) );
		/**
		 * Filter a moderation notification. Return a non-array or empty 'to' to suppress it.
		 *
		 * @param array{to: list<string>, subject: string, body: string} $mail    Mail.
		 * @param string                                                  $event   review|report|question.
		 * @param array<string, mixed>                                    $context Context (no personal data).
		 */
		$mail = apply_filters( 'review_captain_notification', $mail, $event, $context );
		if ( ! is_array( $mail ) || empty( $mail['to'] ) ) {
			return false;
		}
		return (bool) wp_mail( (array) $mail['to'], (string) ( $mail['subject'] ?? '' ), (string) ( $mail['body'] ?? '' ) );
	}

	/**
	 * Subject and body of an event.
	 *
	 * @param string               $event   Event.
	 * @param array<string, mixed> $context Context.
	 * @return array{subject: string, body: string}
	 */
	public function message( string $event, array $context ): array {
		$site = wp_strip_all_tags( (string) get_bloginfo( 'name' ) );
		$url  = (string) ( $context['moderation_url'] ?? admin_url( 'admin.php?page=review-captain' ) );

		if ( self::EVENT_REPORT === $event ) {
			$reason = (string) ( ReportStore::reason_labels()[ (string) ( $context['reason'] ?? '' ) ] ?? '' );
			return array(
				/* translators: %s: site name */
				'subject' => sprintf( __( '[%s] Bewertung gemeldet', 'review-captain' ), $site ),
				'body'    => ! empty( $context['flagged'] )
					/* translators: 1: report reason, 2: moderation URL */
					? sprintf( __( "Auf Ihrer Website wurde eine Bewertung gemeldet (Grund: %1\$s). Sie ist bis zur Prüfung ausgeblendet.\n\nJetzt prüfen: %2\$s", 'review-captain' ), $reason, $url )
					/* translators: 1: report reason, 2: moderation URL */
					: sprintf( __( "Auf Ihrer Website wurde eine Bewertung gemeldet (Grund: %1\$s).\n\nJetzt prüfen: %2\$s", 'review-captain' ), $reason, $url ),
			);
		}
		if ( self::EVENT_QUESTION === $event ) {
			return array(
				/* translators: %s: site name */
				'subject' => sprintf( __( '[%s] Neue Produktfrage eingegangen', 'review-captain' ), $site ),
				/* translators: %s: moderation URL */
				'body'    => sprintf( __( "Auf Ihrer Website wurde eine neue Frage gestellt. Sie wartet auf Moderation.\n\nJetzt prüfen: %s", 'review-captain' ), $url ),
			);
		}

		$approved = ! empty( $context['approved'] );
		$subject  = trim( (string) $this->settings->get( 'submissions', 'notify_subject', '' ) );
		$body     = trim( (string) $this->settings->get( 'submissions', 'notify_body', '' ) );
		$vars     = array(
			'{site}'           => $site,
			'{moderation_url}' => $url,
			'{status}'         => $approved ? __( 'freigegeben', 'review-captain' ) : __( 'wartet auf Moderation', 'review-captain' ),
		);
		return array(
			'subject' => '' !== $subject
				? strtr( $subject, $vars )
				/* translators: %s: site name */
				: sprintf( __( '[%s] Neue Bewertung eingegangen', 'review-captain' ), $site ),
			'body'    => '' !== $body
				? strtr( $body, $vars )
				: ( $approved
					/* translators: %s: moderation URL */
					? sprintf( __( "Auf Ihrer Website wurde eine neue Bewertung abgegeben und automatisch freigegeben.\n\nZur Übersicht: %s", 'review-captain' ), $url )
					/* translators: %s: moderation URL */
					: sprintf( __( "Auf Ihrer Website wurde eine neue Bewertung abgegeben. Sie wartet auf Moderation.\n\nJetzt prüfen: %s", 'review-captain' ), $url ) ),
		);
	}
}
