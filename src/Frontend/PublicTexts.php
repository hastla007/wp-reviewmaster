<?php
/**
 * Configurable public texts: form texts, verification labels and notice (SET-RC-01, -02, -04, -26).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Frontend;

use ReviewCaptain\Domain\Verification;
use ReviewCaptain\Settings\Settings;

/**
 * Resolves public texts: stored setting, else the translated default; the filter
 * `review_captain_public_text` has the last word. Only texts are configurable – which
 * verification level a review carries is never influenced by these settings.
 */
final class PublicTexts {

	public const PRIVACY_PLACEHOLDER = '{privacy_link}';

	/**
	 * Option with every consent text version shown at the form (P3, proof of consent):
	 * version => {text, privacy_url, first_used (UTC)}. Not autoloaded; removed on uninstall with data.
	 */
	public const CONSENT_ARCHIVE_OPTION = 'review_captain_consent_texts';

	/**
	 * Pseudo level for testimonials (kind, not a verification state).
	 */
	public const TESTIMONIAL = 'testimonial';

	/**
	 * Translated defaults keyed by "tab.key".
	 *
	 * @return array<string, string>
	 */
	public static function defaults(): array {
		return array(
			'submissions.consent_text'           => __( 'I consent to my review being published with my name on this website. My email address will not be published. I can withdraw my consent at any time.', 'review-captain' ),
			'submissions.name_label'             => __( 'Name (will be published)', 'review-captain' ),
			'submissions.email_hint'             => __( 'Will never be published. We only use it for follow-up questions and privacy requests.', 'review-captain' ),
			'submissions.submit_label'           => __( 'Submit review', 'review-captain' ),
			'submissions.report_confirmation'    => __( 'Thank you for your report. We will review it.', 'review-captain' ),
			'display.verification_notice_title'  => __( 'How we check reviews', 'review-captain' ),
			'display.label_purchase_verified'    => __( 'Verified purchase', 'review-captain' ),
			'display.label_attendance_confirmed' => __( 'Attendance confirmed', 'review-captain' ),
			'display.label_unverified'           => __( 'Unverified', 'review-captain' ),
			'display.label_external'             => __( 'External platform review', 'review-captain' ),
			'display.label_testimonial'          => __( 'Testimonial from this website', 'review-captain' ),
		);
	}

	/**
	 * Effective text of a setting (empty = translated default; filter wins).
	 *
	 * @param Settings $settings Settings.
	 * @param string   $tab      Tab.
	 * @param string   $key      Key.
	 */
	public static function get( Settings $settings, string $tab, string $key ): string {
		$id   = $tab . '.' . $key;
		$text = trim( (string) $settings->get( $tab, $key, '' ) );
		if ( '' === $text ) {
			$text = self::defaults()[ $id ] ?? '';
		}
		/**
		 * Filter a configurable public text (form texts, verification labels, report confirmation).
		 *
		 * @param string $text Text (setting or translated default).
		 * @param string $id   "tab.key", e.g. "submissions.consent_text" or "display.label_unverified".
		 */
		$filtered = apply_filters( 'review_captain_public_text', $text, $id );
		return is_string( $filtered ) ? $filtered : $text;
	}

	/**
	 * Setting key of the label of a level.
	 *
	 * @param string $level Verification value or self::TESTIMONIAL.
	 */
	public static function label_key( string $level ): string {
		return match ( $level ) {
			Verification::PURCHASE_VERIFIED->value    => 'label_purchase_verified',
			Verification::ATTENDANCE_CONFIRMED->value => 'label_attendance_confirmed',
			Verification::EXTERNAL_PLATFORM->value    => 'label_external',
			self::TESTIMONIAL                         => 'label_testimonial',
			default                                   => 'label_unverified',
		};
	}

	/**
	 * Public label of a level.
	 *
	 * @param Settings $settings Settings.
	 * @param string   $level    Verification value or self::TESTIMONIAL.
	 */
	public static function level_label( Settings $settings, string $level ): string {
		return self::get( $settings, 'display', self::label_key( $level ) );
	}

	/**
	 * Consent text as HTML: escaped text, {privacy_link} replaced by a link to the privacy page
	 * (plain word if no privacy page is set).
	 *
	 * @param Settings $settings Settings.
	 */
	public static function consent_html( Settings $settings ): string {
		$html = esc_html( self::get( $settings, 'submissions', 'consent_text' ) );
		if ( ! str_contains( $html, self::PRIVACY_PLACEHOLDER ) ) {
			return $html;
		}
		$url  = self::privacy_url();
		$word = esc_html__( 'Privacy policy', 'review-captain' );
		$link = '' !== $url ? '<a href="' . esc_url( $url ) . '">' . $word . '</a>' : $word;
		return str_replace( self::PRIVACY_PLACEHOLDER, $link, $html );
	}

	/**
	 * Name shown to reviewers (general.brand_name; empty = site title), plain text.
	 *
	 * @param Settings $settings Settings.
	 */
	public static function brand_name( Settings $settings ): string {
		// One rule for mails and texts (P1-7, P2-06): brand name, otherwise the site title, as plain text.
		return \ReviewCaptain\Moderation\Notifier::site_name( $settings );
	}

	/**
	 * Version of the effective consent text (text and privacy link) for the proof of consent.
	 * The consent text is configurable (SET-RC-04), so a review records which version was
	 * agreed to; the text of each version is archived once in self::CONSENT_ARCHIVE_OPTION.
	 *
	 * @param Settings $settings Settings.
	 * @return string 16 hex characters.
	 */
	public static function consent_version( Settings $settings ): string {
		$text    = self::get( $settings, 'submissions', 'consent_text' );
		$url     = self::privacy_url();
		$version = substr( hash( 'sha256', $text . "\n" . $url ), 0, 16 );
		$archive = get_option( self::CONSENT_ARCHIVE_OPTION, array() );
		$archive = is_array( $archive ) ? $archive : array();
		if ( ! isset( $archive[ $version ] ) ) {
			$archive[ $version ] = array(
				'text'        => $text,
				'privacy_url' => $url,
				'first_used'  => gmdate( 'Y-m-d H:i:s' ),
			);
			update_option( self::CONSENT_ARCHIVE_OPTION, $archive, false );
		}
		return $version;
	}

	/**
	 * URL of the privacy policy page ('' = none).
	 */
	public static function privacy_url(): string {
		$url = function_exists( 'get_privacy_policy_url' ) ? (string) get_privacy_policy_url() : '';
		/**
		 * Filter the privacy policy URL used for {privacy_link} in the consent text.
		 *
		 * @param string $url URL ('' = no link).
		 */
		$url = apply_filters( 'review_captain_privacy_policy_url', $url );
		return is_string( $url ) && preg_match( '#^https?://#i', $url ) ? $url : '';
	}

	/**
	 * Neutral draft of the verification notice. Describes only the given levels; no legal claims.
	 * Legal review pending (EU Omnibus directive) – operators replace it via display.verification_notice.
	 *
	 * @param Settings $settings Settings.
	 * @param array    $levels   Levels that occur: list of Verification values and/or self::TESTIMONIAL.
	 */
	public static function default_verification_notice( Settings $settings, array $levels ): string {
		$quote = static fn( string $level ): string => sprintf( /* translators: %s: quoted text */ _x( '“%s”', 'quotation marks', 'review-captain' ), self::level_label( $settings, $level ) );
		$out   = array();
		foreach ( array( Verification::PURCHASE_VERIFIED->value, Verification::ATTENDANCE_CONFIRMED->value, Verification::UNVERIFIED->value, Verification::EXTERNAL_PLATFORM->value, self::TESTIMONIAL ) as $level ) {
			if ( ! in_array( $level, $levels, true ) ) {
				continue;
			}
			switch ( $level ) {
				case Verification::PURCHASE_VERIFIED->value:
					/* translators: %s: label of the level in quotes */
					$out[] = sprintf( __( '%s: the purchase was confirmed by a system connected to this site (for example the order system).', 'review-captain' ), $quote( $level ) );
					break;
				case Verification::ATTENDANCE_CONFIRMED->value:
					/* translators: %s: label of the level in quotes */
					$out[] = sprintf( __( '%s: attendance was confirmed by a system connected to this site (for example the ticketing system).', 'review-captain' ), $quote( $level ) );
					break;
				case Verification::UNVERIFIED->value:
					$out[] = $settings->get( 'display', 'show_unverified_label', false )
						/* translators: %s: label of the level in quotes */
						? sprintf( __( '%s: no purchase or use has been verified for this review.', 'review-captain' ), $quote( $level ) )
						: __( 'Reviews without a verification label: no purchase or use has been verified.', 'review-captain' );
					break;
				case Verification::EXTERNAL_PLATFORM->value:
					/* translators: %s: label of the level in quotes */
					$out[] = sprintf( __( '%s: the review comes from an external platform and is labelled with its source and a link to the original. Whether and how it was checked there depends on the platform’s rules.', 'review-captain' ), $quote( $level ) );
					break;
				case self::TESTIMONIAL:
					/* translators: %s: label of the level in quotes */
					$out[] = sprintf( __( '%s: testimonial selected and published by this site itself.', 'review-captain' ), $quote( $level ) );
					break;
			}
		}
		return implode( "\n\n", $out );
	}
}
