<?php
/**
 * Privacy integration (RC-047).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Privacy;

use ReviewCaptain\Module;
use ReviewCaptain\Plugin;

/**
 * Registers WordPress personal data exporter/eraser and a privacy policy text suggestion.
 */
final class PrivacyModule implements Module {

	private ?Plugin $plugin = null;

	/**
	 * Register hooks.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function register( Plugin $plugin ): void {
		$this->plugin = $plugin;
		$plugin->factory( 'privacy', static fn( Plugin $p ) => new PersonalData( $p->reviews(), $p->settings() ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
		add_action( 'admin_init', array( $this, 'add_policy_content' ) );
		add_filter( 'site_status_tests', array( $this, 'site_status_tests' ) );
	}

	/**
	 * Site Health: reminder while no retention period for the contact address is set (owner decision S9).
	 *
	 * @param mixed $tests Tests.
	 * @return array<string, mixed>
	 */
	public function site_status_tests( mixed $tests ): array {
		$tests                                       = is_array( $tests ) ? $tests : array();
		$tests['direct']                             = isset( $tests['direct'] ) && is_array( $tests['direct'] ) ? $tests['direct'] : array();
		$tests['direct']['review_captain_retention'] = array(
			'label' => __( 'Review Captain: retention of contact addresses', 'review-captain' ),
			'test'  => array( $this, 'retention_status' ),
		);
		return $tests;
	}

	/**
	 * Result of the Site Health test "retention of contact addresses". Names no period itself –
	 * which period fits is a legal question (country profile SET-RC-15 only suggests one).
	 *
	 * @return array<string, mixed>
	 */
	public function retention_status(): array {
		$plugin = $this->plugin ?? Plugin::instance();
		$days   = (int) $plugin->settings()->get( 'privacy', 'retention_contact_days', 0 );
		$result = array(
			'label'       => __( 'A retention period is set for the contact addresses of reviews', 'review-captain' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Privacy', 'review-captain' ),
				'color' => 'blue',
			),
			/* translators: %s: number of days */
			'description' => '<p>' . esc_html( sprintf( __( 'Review Captain removes the email address of a review %s days after it was received. The review stays.', 'review-captain' ), number_format_i18n( $days ) ) ) . '</p>',
			'actions'     => '',
			'test'        => 'review_captain_retention',
		);
		if ( $days > 0 ) {
			return $result;
		}
		$result['badge']['color'] = 'orange';
		$result['label']          = __( 'Review Captain stores the contact addresses of reviews indefinitely', 'review-captain' );
		$result['status']         = 'recommended';
		$result['description']    = '<p>' . esc_html__( 'No retention period is set for the email addresses of reviews. Set in the Privacy tab after how many days they are removed; the reviews stay. Please have the period that suits your website checked legally.', 'review-captain' ) . '</p>';
		$result['actions']        = '<p><a href="' . esc_url( \ReviewCaptain\Admin\View::page_url( \ReviewCaptain\Admin\SettingsPage::SLUG, array( 'tab' => 'privacy' ) ) ) . '">' . esc_html__( 'Set retention', 'review-captain' ) . '</a></p>';
		return $result;
	}

	/**
	 * Personal data service.
	 */
	public function personal_data(): PersonalData {
		$plugin = $this->plugin ?? Plugin::instance();
		/** @var PersonalData */
		return $plugin->has( 'privacy' ) ? $plugin->get( 'privacy' ) : new PersonalData( $plugin->reviews(), $plugin->settings() );
	}

	/**
	 * Add exporter.
	 *
	 * @param mixed $exporters Exporters.
	 * @return array<string, array{exporter_friendly_name: string, callback: callable}>
	 */
	public function register_exporter( mixed $exporters ): array {
		$exporters                           = is_array( $exporters ) ? $exporters : array();
		$exporters[ PersonalData::GROUP_ID ] = array(
			'exporter_friendly_name' => __( 'Review Captain', 'review-captain' ),
			'callback'               => fn( string $email, int $page = 1 ): array => $this->personal_data()->export( $email, $page ),
		);
		return $exporters;
	}

	/**
	 * Add eraser.
	 *
	 * @param mixed $erasers Erasers.
	 * @return array<string, array{eraser_friendly_name: string, callback: callable}>
	 */
	public function register_eraser( mixed $erasers ): array {
		$erasers                           = is_array( $erasers ) ? $erasers : array();
		$erasers[ PersonalData::GROUP_ID ] = array(
			'eraser_friendly_name' => __( 'Review Captain', 'review-captain' ),
			'callback'             => fn( string $email, int $page = 1 ): array => $this->personal_data()->erase( $email, $page ),
		);
		return $erasers;
	}

	/**
	 * Suggested privacy policy text (German).
	 */
	public function add_policy_content(): void {
		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			wp_add_privacy_policy_content( __( 'Review Captain', 'review-captain' ), wp_kses_post( self::policy_text() ) );
		}
	}

	/**
	 * Policy text suggestion.
	 */
	public static function policy_text(): string {
		$settings = \ReviewCaptain\Plugin::instance()->settings();
		// P3: the suggestion must not claim a moderation or a consent the settings switched off.
		$moderated = 'approved' !== $settings->get( 'general', 'new_review_status', 'pending' );
		$consent   = false !== $settings->get( 'submissions', 'require_consent', true );
		if ( $moderated && $consent ) {
			$publish = __( 'Your review will only be published after moderation and only if you have consented to publication. We store the time of your consent as proof.', 'review-captain' );
		} elseif ( $consent ) {
			$publish = __( 'Your review is only published if you have agreed to its publication. We store the time of your consent as proof.', 'review-captain' );
		} elseif ( $moderated ) {
			$publish = __( 'Your review is published only after it has been checked.', 'review-captain' );
		} else {
			$publish = '';
		}
		$parts = array(
			'<p><strong>' . __( 'Suggestion – please adapt to your actual usage.', 'review-captain' ) . '</strong></p>',
			'<p>' . __( 'When you submit a review or testimonial on this website, we store your rating, the text, the name you provide, and your email address and – if you are logged in – your user account ID. The email address is not published; it is used for follow-up questions, abuse prevention, and matching data access and erasure requests.', 'review-captain' ) . '</p>',
			'' !== $publish ? '<p>' . $publish . '</p>' : '',
			'<p>' . __( 'Reviews from external platforms (e.g. Google or Trustpilot) are only displayed with the display name, date, text visible there, and a link to the source, and are regularly synced with the source; if a review is deleted there, we remove it here as well.', 'review-captain' ) . '</p>',
			'<p>' . __( 'You can use this website\'s privacy tools to request information about your stored reviews and to request their deletion or anonymization. With anonymization, your name is replaced with “Anonymous” and your email address is removed; the review text is only retained if you had consented to its publication.', 'review-captain' ) . '</p>',
			'<p>' . ( $moderated
				? __( 'If enabled: A photo uploaded with a review is re-encoded before storage, removing image metadata such as the location where it was taken; it is only displayed after moderation and is deleted along with the review. We store product questions with a name and optionally an email address; we store review reports without any information about the reporting person.', 'review-captain' )
				: __( 'If enabled: a photo uploaded with the review is re-encoded before it is stored, which removes image metadata such as the location where it was taken; it is shown with the review and deleted with it. We store product questions with a name and, optionally, an email address; we store reports of reviews without any details about the person reporting.', 'review-captain' ) ) . '</p>',
			'<p>' . __( 'Rejected submissions and technical logs are automatically deleted according to the configured retention periods.', 'review-captain' ) . '</p>',
		);
		// SET-RC-08: only when the operator set a retention period for the contact address.
		$contact_days = (int) $settings->get( 'privacy', 'retention_contact_days', 0 );
		if ( $contact_days > 0 ) {
			/* translators: %s: number of days */
			$parts[] = '<p>' . sprintf( __( 'We delete the email address of a review %s days after the review was received; the review itself is kept.', 'review-captain' ), number_format_i18n( $contact_days ) ) . '</p>';
		}
		// P3: the period for approved own reviews (SET-RC-08) belongs into the suggestion as well.
		$approved_days = (int) $settings->get( 'privacy', 'retention_approved_days', 0 );
		if ( $approved_days > 0 ) {
			/* translators: %s: number of days */
			$parts[] = '<p>' . sprintf( __( 'We completely delete reviews you have submitted on this website %s days after they were received.', 'review-captain' ), number_format_i18n( $approved_days ) ) . '</p>';
		}
		return implode( "\n", array_filter( $parts ) );
	}
}
