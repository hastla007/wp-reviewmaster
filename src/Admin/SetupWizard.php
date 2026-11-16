<?php
/**
 * Resumable setup wizard and dashboard checklist (SET-008, UX-012). Every step can be skipped; nothing external is activated.
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Admin;

use ReviewCaptain\Profiles\ProfileRepository;
use ReviewCaptain\Security\Capabilities;
use ReviewCaptain\Settings\Settings;
use ReviewCaptain\Widgets\WidgetRepository;

defined( 'ABSPATH' ) || exit;

final class SetupWizard {

	public const OPTION = 'review_captain_setup';
	private const PAGE  = 'review-captain-setup';

	/**
	 * @return array<string,string>
	 */
	public static function steps(): array {
		return array(
			'basics'  => __( 'Basic details', 'review-captain' ),
			'reviews' => __( 'First reviews', 'review-captain' ),
			'source'  => __( 'Review profiles', 'review-captain' ),
			'widget'  => __( 'First widget', 'review-captain' ),
			'privacy' => __( 'Privacy', 'review-captain' ),
		);
	}

	/**
	 * @return array<string,string> step => todo|done|skipped
	 */
	public static function state(): array {
		$state = (array) get_option( self::OPTION, array() );
		$out   = array();
		foreach ( array_keys( self::steps() ) as $step ) {
			$out[ $step ] = in_array( $state[ $step ] ?? '', array( 'done', 'skipped' ), true ) ? $state[ $step ] : 'todo';
		}
		return $out;
	}

	public static function set( string $step, string $status ): void {
		$state          = self::state();
		$state[ $step ] = $status;
		update_option( self::OPTION, $state, false );
	}

	public static function register_actions(): void {
		add_action( 'admin_post_review_captain_setup_step', array( self::class, 'handle_step' ) );
	}

	public static function checklist_notice(): void {
		$state = self::state();
		$done  = count( array_filter( $state, static fn( $s ) => 'todo' !== $s ) );
		if ( $done === count( $state ) || ! current_user_can( 'rc_manage_settings' ) ) {
			return;
		}
		/* translators: 1: done steps, 2: total steps */
		echo '<div class="notice notice-info inline"><p>' . esc_html( sprintf( __( 'Setup: %1$d of %2$d steps done.', 'review-captain' ), $done, count( $state ) ) ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ) . '">' . esc_html__( 'Continue setup', 'review-captain' ) . '</a></p></div>';
	}

	public static function render(): void {
		$state = self::state();
		$steps = self::steps();
		$step  = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $steps[ $step ] ) ) {
			$step = (string) ( array_search( 'todo', $state, true ) ? array_search( 'todo', $state, true ) : 'basics' );
		}
		echo '<div class="wrap rc-setup"><h1>' . esc_html__( 'Set up Review Captain', 'review-captain' ) . '</h1>';
		Menu::notices();
		echo '<ol class="rc-steps">';
		foreach ( $steps as $key => $label ) {
			$cls = $key === $step ? 'current' : $state[ $key ];
			echo '<li class="rc-step rc-step--' . esc_attr( $cls ) . '"><a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE . '&step=' . $key ) ) . '"' . ( $key === $step ? ' aria-current="step"' : '' ) . '>' . esc_html( $label ) . '</a></li>';
		}
		echo '</ol><div class="postbox"><div class="inside">';
		echo '<form method="post" action="' . esc_url( Menu::post_url() ) . '"><input type="hidden" name="action" value="review_captain_setup_step"><input type="hidden" name="step" value="' . esc_attr( $step ) . '">';
		wp_nonce_field( 'rc_setup_' . $step );

		switch ( $step ) {
			case 'basics':
				echo '<h2>' . esc_html__( 'Basic details', 'review-captain' ) . '</h2>';
				printf( '<p><label for="rc-s-brand">%s</label><br><input type="text" id="rc-s-brand" name="brand_name" class="regular-text" value="%s"></p>', esc_html__( 'Brand name', 'review-captain' ), esc_attr( (string) Settings::get( 'brand_name' ) ) );
				printf( '<p><label for="rc-s-locale">%s</label><br><input type="text" id="rc-s-locale" name="default_locale" value="%s"></p>', esc_html__( 'Default language', 'review-captain' ), esc_attr( (string) Settings::get( 'default_locale' ) ) );
				/* translators: %s: timezone */
				echo '<p class="description">' . esc_html( sprintf( __( 'Time zone is taken from WordPress: %s', 'review-captain' ), wp_timezone_string() ) ) . '</p>';
				break;
			case 'reviews':
				echo '<h2>' . esc_html__( 'How do you want to start?', 'review-captain' ) . '</h2><ul class="rc-choices">';
				echo '<li><strong>' . esc_html__( 'Collect your own reviews', 'review-captain' ) . '</strong> – ' . esc_html__( 'Add the “Review Captain Review Form” block to a page, or use the shortcode', 'review-captain' ) . ' <code>[review_captain_form]</code>.</li>';
				echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=review-captain-reviews&action=add' ) ) . '">' . esc_html__( 'Add a review from another platform manually', 'review-captain' ) . '</a></li>';
				echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=review-captain-import' ) ) . '">' . esc_html__( 'Import existing reviews (CSV)', 'review-captain' ) . '</a></li></ul>';
				printf( '<p><label><input type="checkbox" name="default_status_pending" value="1"%s> %s</label></p>', checked( 'pending' === Settings::get( 'default_status' ), true, false ), esc_html__( 'Publish new reviews only after moderation (recommended)', 'review-captain' ) );
				break;
			case 'source':
				echo '<h2>' . esc_html__( 'Your review profiles', 'review-captain' ) . '</h2><p>' . esc_html__( 'Add the public link to your profile on a platform. It is used for the neutral “Review us on …” buttons.', 'review-captain' ) . '</p>';
				echo '<p><select name="provider" aria-label="' . esc_attr__( 'Platform', 'review-captain' ) . '">';
				foreach ( \ReviewCaptain\Reviews\ReviewService::EXTERNAL_PROVIDERS as $p ) {
					echo '<option value="' . esc_attr( $p ) . '">' . esc_html( \ReviewCaptain\Reviews\ReviewService::provider_label( $p ) ) . '</option>';
				}
				echo '</select> <input type="url" name="url" class="regular-text" placeholder="https://"></p>';
				do_action( 'review_captain_setup_source_step' );
				break;
			case 'widget':
				echo '<h2>' . esc_html__( 'First widget', 'review-captain' ) . '</h2><p>' . esc_html__( 'We create a simple grid with your approved reviews. You can change it at any time.', 'review-captain' ) . '</p>';
				printf( '<p><label for="rc-s-wtitle">%s</label><br><input type="text" id="rc-s-wtitle" name="widget_title" value="%s" class="regular-text"></p>', esc_html__( 'Widget title (internal)', 'review-captain' ), esc_attr__( 'Reviews', 'review-captain' ) );
				break;
			case 'privacy':
				echo '<h2>' . esc_html__( 'Privacy', 'review-captain' ) . '</h2><p>' . esc_html__( 'Review Captain added a suggested text for your privacy policy (Settings → Privacy → Policy guide). Please check and adapt it.', 'review-captain' ) . '</p>';
				/* translators: 1: days, 2: days */
				echo '<p>' . esc_html( sprintf( __( 'Defaults: rejected reviews are deleted after %1$d days, technical logs after %2$d days. You can change this under Settings → Privacy & retention.', 'review-captain' ), (int) Settings::get( 'retention_rejected_reviews_days' ), (int) Settings::get( 'retention_logs_days' ) ) ) . '</p>';
				echo '<p><label><input type="checkbox" name="privacy_checked" value="1"> ' . esc_html__( 'I have reviewed the privacy notes', 'review-captain' ) . '</label></p>';
				break;
		}
		echo '<p class="rc-setup__buttons">';
		submit_button( __( 'Save and continue', 'review-captain' ), 'primary', 'save', false );
		echo ' ';
		submit_button( __( 'Skip this step', 'review-captain' ), 'secondary', 'skip', false );
		echo ' <a href="' . esc_url( admin_url( 'admin.php?page=review-captain' ) ) . '">' . esc_html__( 'Finish later', 'review-captain' ) . '</a></p></form></div></div></div>';
	}

	public static function handle_step(): void {
		$step = isset( $_POST['step'] ) ? sanitize_key( wp_unslash( $_POST['step'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked below.
		Capabilities::check( 'rc_manage_settings', 'rc_setup_' . $step );
		$steps = self::steps();
		if ( ! isset( $steps[ $step ] ) ) {
			Menu::back( self::PAGE );
		}
		$error = '';
		if ( isset( $_POST['skip'] ) ) {
			self::set( $step, 'skipped' );
		} else {
			switch ( $step ) {
				case 'basics':
					$values                   = Settings::group( 'general' );
					$values['brand_name']     = sanitize_text_field( wp_unslash( $_POST['brand_name'] ?? '' ) );
					$values['default_locale'] = sanitize_text_field( wp_unslash( $_POST['default_locale'] ?? '' ) );
					Settings::save_group( 'general', Settings::sanitize_group( 'general', $values )['values'] );
					break;
				case 'reviews':
					$values                   = Settings::group( 'submissions' );
					$values['default_status'] = empty( $_POST['default_status_pending'] ) ? 'approved' : 'pending';
					Settings::save_group( 'submissions', Settings::sanitize_group( 'submissions', $values )['values'] );
					break;
				case 'source':
					$url = esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) );
					if ( '' !== $url ) {
						$res = ProfileRepository::save_link( 0, sanitize_key( wp_unslash( $_POST['provider'] ?? '' ) ), '', $url );
						if ( is_wp_error( $res ) ) {
							$error = $res->get_error_message();
						}
					}
					break;
				case 'widget':
					WidgetRepository::save( 0, sanitize_text_field( wp_unslash( $_POST['widget_title'] ?? __( 'Reviews', 'review-captain' ) ) ), 'grid', WidgetRepository::default_config() );
					break;
			}
			if ( '' === $error ) {
				self::set( $step, 'done' );
			}
		}
		if ( '' !== $error ) {
			Menu::back( self::PAGE, '', $error, array( 'step' => $step ) );
		}
		$next = array_search( 'todo', self::state(), true );
		if ( false === $next ) {
			Menu::back( 'review-captain', 'saved' );
		}
		Menu::back( self::PAGE, 'saved', '', array( 'step' => (string) $next ) );
	}
}
