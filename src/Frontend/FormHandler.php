<?php
/**
 * Local review submission (RC-039, SET-013).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Frontend;

use ReviewCaptain\Domain\Kind;
use ReviewCaptain\Domain\Origin;
use ReviewCaptain\Domain\Platform;
use ReviewCaptain\Domain\Review;
use ReviewCaptain\Domain\ReviewStatus;
use ReviewCaptain\Domain\Verification;
use ReviewCaptain\Media\PhotoStore;
use ReviewCaptain\Moderation\Notifier;
use ReviewCaptain\Plugin;
use ReviewCaptain\Widgets\WidgetDefinition;

/**
 * Handles POSTs to admin-post.php?action=review_captain_submit (logged-in and anonymous).
 *
 * Protection: nonce, honeypot, signed render timestamp (min. `submissions.min_seconds`, default
 * 3 s), rate limit per IP hash (wp_hash; the plain IP is never stored), signed context, strict
 * field validation, blocked e-mail domains. Moderation rules (hold words, links) only ever hold a
 * review for moderation (SET-RC-10); nothing is silently dropped.
 * Result is communicated via redirect back (rc_submitted / rc_error).
 */
class FormHandler {

	public const ACTION         = 'review_captain_submit';
	public const NONCE_ACTION   = 'review_captain_submit';
	public const NONCE_FIELD    = '_rc_nonce';
	public const HONEYPOT_FIELD = 'rc_website';

	public const MIN_SECONDS = 3;
	public const MAX_AGE     = DAY_IN_SECONDS;

	/**
	 * Draft lifetime (keeps entered values after a validation error; no e-mail stored).
	 */
	public const DRAFT_TTL = 10 * MINUTE_IN_SECONDS;

	/**
	 * Maximum number of contexts in a multi-product form (RC-123). Default; raisable with the
	 * filter `review_captain_form_max_items` up to MAX_ITEMS_LIMIT (SET-RC-31).
	 */
	public const MAX_ITEMS = 20;

	/**
	 * Upper bound for `review_captain_form_max_items`.
	 */
	public const MAX_ITEMS_LIMIT = 50;

	/**
	 * Upper bound for entries of a moderation list setting (hold words, blocked domains).
	 */
	public const MAX_LIST_ENTRIES = 500;

	/**
	 * Plugin.
	 */
	private Plugin $plugin;

	/**
	 * Require is_uploaded_file() for photos (false only in tests/CLI).
	 */
	private bool $require_upload = true;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * admin-post handler: process and redirect.
	 */
	public function handle(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in process().
		$files  = isset( $_FILES['rc_photo'] ) && is_array( $_FILES['rc_photo'] ) ? array( 'rc_photo' => $_FILES['rc_photo'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated by PhotoStore.
		$result = $this->process( wp_unslash( $_POST ), $files ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in process().
		$this->redirect( $result['redirect'] );
	}

	/**
	 * Allow photo files that were not uploaded via HTTP (tests/CLI only).
	 *
	 * @param bool $require Require is_uploaded_file().
	 */
	public function set_require_upload( bool $require ): void {
		$this->require_upload = $require;
	}

	/**
	 * Process a submission. Returns the outcome and redirect target (no output, no exit).
	 *
	 * @param array<string, mixed> $post  Unslashed POST data.
	 * @param array<string, mixed> $files Upload entries (key rc_photo, RC-121).
	 * @return array{ok: bool, errors: list<string>, review: ?Review, redirect: string, reviews?: list<Review>}
	 */
	public function process( array $post, array $files = array() ): array {
		$settings = $this->plugin->settings();
		$return   = $this->return_url( $post );
		$form     = isset( $post['rc_form'] ) && ctype_digit( (string) $post['rc_form'] ) ? (string) $post['rc_form'] : '1';

		$fail = function ( array $errors, array $draft = array() ) use ( $return, $form ): array {
			$args = array(
				'rc_error' => implode( ',', $errors ),
				'rc_form'  => $form,
			);
			if ( $draft ) {
				$this->rate_hit( 'fails' );
				$token = strtolower( wp_generate_password( 20, false, false ) );
				$token = preg_replace( '/[^a-z0-9]/', '', $token );
				set_transient( 'review_captain_draft_' . $token, $draft, self::DRAFT_TTL );
				$args['rc_draft'] = $token;
			}
			return array(
				'ok'       => false,
				'errors'   => $errors,
				'review'   => null,
				// Anchor on the error box, which takes focus on load (QA F-06).
				'redirect' => add_query_arg( $args, $return ) . '#rc-form-' . $form . '-errors',
			);
		};

		if ( ! $settings->get( 'submissions', 'enabled', true ) ) {
			return $fail( array( 'disabled' ) );
		}
		if ( ! wp_verify_nonce( (string) ( $post[ self::NONCE_FIELD ] ?? '' ), self::NONCE_ACTION ) ) {
			return $fail( array( 'nonce' ) );
		}

		$success = function ( ?Review $review, bool $approved ) use ( $return, $form ): array {
			return array(
				'ok'       => true,
				'errors'   => array(),
				'review'   => $review,
				'redirect' => add_query_arg(
					array(
						'rc_submitted' => $approved ? '2' : '1',
						'rc_form'      => $form,
					),
					$return
				) . '#rc-form-' . $form,
			);
		};

		// Honeypot: silently pretend success, store nothing.
		if ( $settings->get( 'submissions', 'honeypot', true ) && '' !== trim( (string) ( $post[ self::HONEYPOT_FIELD ] ?? '' ) ) ) {
			return $success( null, false );
		}

		$ts_error = self::verify_timestamp( (string) ( $post['rc_ts'] ?? '' ) );
		if ( null !== $ts_error ) {
			return $fail( array( $ts_error ) );
		}

		$type = (string) ( $post['rc_context_type'] ?? '' );
		$cid  = (string) ( $post['rc_context_id'] ?? '' );
		if ( ! self::verify_context( $type, $cid, (string) ( $post['rc_context_sig'] ?? '' ) ) ) {
			return $fail( array( 'context' ) );
		}

		// Accepted submissions count fully; failed validations (which store a short-lived
		// draft) are limited to three times the hourly limit.
		$limit = (int) $settings->get( 'submissions', 'rate_limit_per_hour', 5 );
		if ( $this->rate_count() >= $limit || $this->rate_count( 'fails' ) >= 3 * $limit ) {
			return $fail( array( 'rate_limited' ) );
		}

		// Multi-product form (RC-123): signed list of context IDs, one review per rated context.
		$multi = null;
		if ( '' !== (string) ( $post['rc_context_ids'] ?? '' ) ) {
			if ( ! $settings->get( 'submissions', 'multi_product_enabled', false ) ) {
				return $fail( array( 'context' ) );
			}
			$multi = self::verify_context_list( $type, (string) $post['rc_context_ids'], (string) ( $post['rc_context_ids_sig'] ?? '' ) );
			if ( null === $multi ) {
				return $fail( array( 'context' ) );
			}
		}

		// Field validation.
		$errors  = array();
		$rating  = (string) ( $post['rc_rating'] ?? '' );
		$title   = $settings->get( 'submissions', 'allow_title', true ) ? sanitize_text_field( (string) ( $post['rc_title'] ?? '' ) ) : '';
		$body    = sanitize_textarea_field( (string) ( $post['rc_body'] ?? '' ) );
		$name    = sanitize_text_field( (string) ( $post['rc_name'] ?? '' ) );
		$email   = sanitize_email( (string) ( $post['rc_email'] ?? '' ) );
		$consent = ! empty( $post['rc_consent'] );
		$min     = (int) $settings->get( 'submissions', 'min_text_length', 20 );
		$max     = (int) $settings->get( 'submissions', 'max_text_length', 5000 );
		$len     = mb_strlen( $body );
		$items   = array();

		if ( null === $multi ) {
			if ( ! in_array( $rating, array( '1', '2', '3', '4', '5' ), true ) ) {
				$errors[] = 'rating';
			}
			if ( $len < max( 1, $min ) ) {
				$errors[] = 'body_short';
			} elseif ( $len > $max ) {
				$errors[] = 'body_long';
			}
		} else {
			$raw_items = isset( $post['rc_items'] ) && is_array( $post['rc_items'] ) ? $post['rc_items'] : array();
			foreach ( $multi as $item_id ) {
				$raw         = isset( $raw_items[ $item_id ] ) && is_array( $raw_items[ $item_id ] ) ? $raw_items[ $item_id ] : array();
				$item_rating = (string) ( $raw['rating'] ?? '' );
				$item_body   = sanitize_textarea_field( (string) ( $raw['body'] ?? '' ) );
				if ( '' === $item_rating && '' === trim( $item_body ) ) {
					continue; // Not rated: customers may rate only some positions.
				}
				if ( ! in_array( $item_rating, array( '1', '2', '3', '4', '5' ), true ) ) {
					$errors[] = 'item_rating';
				}
				$item_len = mb_strlen( $item_body );
				if ( $item_len < max( 1, $min ) ) {
					$errors[] = 'item_body_short';
				} elseif ( $item_len > $max ) {
					$errors[] = 'item_body_long';
				}
				$items[ $item_id ] = array(
					'rating' => $item_rating,
					'body'   => $item_body,
				);
			}
			if ( ! $items && ! $errors ) {
				$errors[] = 'items_empty';
			}
		}
		if ( mb_strlen( $title ) > 150 ) {
			$errors[] = 'title_long';
		}
		if ( '' === $name || mb_strlen( $name ) > 100 ) {
			$errors[] = 'name';
		}
		if ( '' === $email || ! is_email( $email ) || strlen( $email ) > 191 ) {
			$errors[] = 'email';
		} elseif ( self::email_domain_blocked( $email, self::list_setting( (string) $settings->get( 'submissions', 'block_email_domains', '' ) ) ) ) {
			$errors[] = 'email_blocked';
		}
		if ( $settings->get( 'submissions', 'require_consent', true ) && ! $consent ) {
			$errors[] = 'consent';
		}

		// Optional photo (RC-121): validated before anything is stored; single-context form only.
		$photo = null;
		$file  = isset( $files['rc_photo'] ) && is_array( $files['rc_photo'] ) ? $files['rc_photo'] : null;
		if ( null !== $file && UPLOAD_ERR_NO_FILE !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) && null === $multi && $settings->get( 'submissions', 'photos_enabled', false ) ) {
			$photo_error = PhotoStore::validate( $file, 1024 * (int) $settings->get( 'submissions', 'photo_max_kb', 2048 ), $this->require_upload );
			if ( null !== $photo_error ) {
				$errors[] = $photo_error;
			} elseif ( ! $errors ) {
				$photo = PhotoStore::process( (string) $file['tmp_name'] );
				if ( null === $photo ) {
					$errors[] = 'photo_invalid';
				}
			}
		}

		$errors = array_values( array_unique( $errors ) );
		if ( $errors ) {
			return $fail(
				$errors,
				array(
					'rating' => in_array( $rating, array( '1', '2', '3', '4', '5' ), true ) ? $rating : '',
					'title'  => mb_substr( $title, 0, 150 ),
					'body'   => mb_substr( $body, 0, $max ),
					'name'   => mb_substr( $name, 0, 100 ),
				)
			);
		}

		$unique  = (bool) $settings->get( 'submissions', 'multi_product_enabled', false ) && '' !== $type;
		$targets = null === $multi
			? array(
				( '' !== $cid ? $cid : '' ) => array(
					'rating' => $rating,
					'body'   => $body,
				),
			)
			: $items;
		$saved      = array();
		$duplicates = 0;
		foreach ( $targets as $target_id => $item ) {
			$target_id = (string) $target_id;
			$review    = $this->build_review( $settings, $item['rating'], $title, $item['body'], $name, $email, $consent, $type, $target_id );
			if ( $unique && '' !== $target_id ) {
				$review->submission_key = self::submission_key( $email, $type, $target_id );
			}
			$hold = self::hold_reasons( $review, $settings );
			if ( $hold ) {
				$review->status = ReviewStatus::PENDING; // Moderation rules only hold, never drop (SET-RC-10).
			}
			try {
				$inserted = $this->plugin->reviews()->insert( $review );
				if ( $hold && null !== $inserted->id ) {
					$this->plugin->reviews()->record_hold( $inserted->id, $hold );
				}
				$saved[] = $inserted;
			} catch ( \Throwable $e ) {
				if ( null !== $review->submission_key && null !== $this->plugin->reviews()->find_by_submission_key( $review->submission_key ) ) {
					++$duplicates; // UNIQUE submission_key: this person already rated this context.
					continue;
				}
				if ( ! $saved ) {
					return $fail( array( 'save' ) );
				}
			}
		}
		if ( ! $saved ) {
			return $fail( array( $duplicates > 0 ? 'duplicate' : 'save' ) );
		}
		$first = $saved[0];
		if ( null !== $photo && null !== $first->id ) {
			PhotoStore::store( $first->id, $photo );
		}

		$this->rate_hit();
		if ( $settings->get( 'submissions', 'notify_admin', true ) ) {
			$this->notify_admin( $first );
		}
		$out            = $success( $first, ReviewStatus::APPROVED === $first->status );
		$out['reviews'] = $saved;
		return $out;
	}

	/**
	 * Build a local submission record.
	 *
	 * @param \ReviewCaptain\Settings\Settings $settings Settings.
	 * @param string                          $rating   Rating 1–5.
	 * @param string                          $title    Title.
	 * @param string                          $body     Body.
	 * @param string                          $name     Name.
	 * @param string                          $email    E-mail.
	 * @param bool                            $consent  Consent.
	 * @param string                          $type     Context type.
	 * @param string                          $cid      Context ID.
	 */
	private function build_review( $settings, string $rating, string $title, string $body, string $name, string $email, bool $consent, string $type, string $cid ): Review {
		$now                     = gmdate( 'Y-m-d H:i:s' );
		$review                  = new Review();
		$review->kind            = Kind::REVIEW;
		$review->origin          = Origin::LOCAL_SUBMISSION;
		$review->platform        = Platform::LOCAL;
		$review->verification    = Verification::UNVERIFIED;
		$review->status          = 'approved' === $settings->get( 'general', 'new_review_status', 'pending' ) ? ReviewStatus::APPROVED : ReviewStatus::PENDING;
		$review->rating_value    = (float) $rating;
		$review->title           = $title;
		$review->body            = $body;
		$review->author_name     = $name;
		$review->author_email    = $email;
		$review->language        = substr( (string) determine_locale(), 0, 20 );
		$review->consent_publish = $consent;
		$review->consent_at      = $consent ? $now : null;
		$review->author_user_id  = is_user_logged_in() ? (int) get_current_user_id() : null;
		$review->context_type    = '' !== $type ? $type : null;
		$review->context_id      = '' !== $type && '' !== $cid ? $cid : null;
		return $review;
	}

	/**
	 * Moderation rules that hold this review (SET-RC-10): `hold_words` (whole words or phrases,
	 * case-insensitive, in title, text or name) and `hold_links` (http(s):// or www.). Returns rule
	 * codes only – never the matched content.
	 *
	 * @param Review                           $review   Review about to be stored.
	 * @param \ReviewCaptain\Settings\Settings $settings Settings.
	 * @return list<string>
	 */
	public static function hold_reasons( Review $review, $settings ): array {
		$text    = $review->title . "\n" . $review->body . "\n" . $review->author_name;
		$reasons = array();
		foreach ( self::list_setting( (string) $settings->get( 'submissions', 'hold_words', '' ) ) as $word ) {
			if ( preg_match( '/(?<![\p{L}\p{N}])' . preg_quote( $word, '/' ) . '(?![\p{L}\p{N}])/iu', $text ) ) {
				$reasons[] = 'hold_words';
				break;
			}
		}
		if ( $settings->get( 'submissions', 'hold_links', false ) && preg_match( '#(?:https?://|\bwww\.)\S#i', $text ) ) {
			$reasons[] = 'hold_links';
		}
		/**
		 * Reasons to hold a new submission for moderation (codes, no content). Returning a
		 * non-empty list holds the review (status pending); an empty list releases it.
		 *
		 * @param list<string> $reasons Reasons found by the settings.
		 * @param Review       $review  Review (not yet stored).
		 */
		$filtered = apply_filters( 'review_captain_submission_hold_reasons', $reasons, $review );
		return is_array( $filtered ) ? array_values( array_filter( array_map( 'sanitize_key', array_filter( $filtered, 'is_string' ) ) ) ) : $reasons;
	}

	/**
	 * Whether the e-mail domain (or a parent domain) is on the block list.
	 *
	 * @param string       $email   E-mail.
	 * @param list<string> $domains Blocked domains (lower case).
	 */
	public static function email_domain_blocked( string $email, array $domains ): bool {
		$at = strrpos( $email, '@' );
		if ( false === $at ) {
			return false;
		}
		$domain = strtolower( rtrim( substr( $email, $at + 1 ), '.' ) );
		/**
		 * Blocked e-mail domains for submissions (filter wins over the setting).
		 *
		 * @param list<string> $domains Domains.
		 */
		$domains = (array) apply_filters( 'review_captain_blocked_email_domains', $domains );
		foreach ( $domains as $blocked ) {
			$blocked = ltrim( strtolower( trim( (string) $blocked ) ), '@.' );
			if ( '' !== $blocked && ( $domain === $blocked || str_ends_with( $domain, '.' . $blocked ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Parse a list setting (one entry per line or comma separated): trimmed, lower case, unique.
	 *
	 * @param string $raw Raw setting.
	 * @return list<string>
	 */
	public static function list_setting( string $raw ): array {
		$out = array();
		$parts = preg_split( '/[\r\n,]+/', $raw );
		foreach ( false === $parts ? array() : $parts as $entry ) {
			$entry = mb_strtolower( trim( $entry ) );
			if ( '' !== $entry ) {
				$out[ $entry ] = $entry;
			}
		}
		return array_slice( array_values( $out ), 0, self::MAX_LIST_ENTRIES );
	}

	/**
	 * Effective maximum of contexts per multi-product form (SET-RC-31).
	 */
	public static function max_items(): int {
		/**
		 * Maximum number of products in one multi-product form (1–50, default 20).
		 *
		 * @param int $max Maximum.
		 */
		return max( 1, min( self::MAX_ITEMS_LIMIT, (int) apply_filters( 'review_captain_form_max_items', self::MAX_ITEMS ) ) );
	}

	/**
	 * Effective minimum fill-in time in seconds (SET-RC-10; filter wins).
	 */
	public static function min_seconds(): int {
		$value = (int) Plugin::instance()->settings()->get( 'submissions', 'min_seconds', self::MIN_SECONDS );
		/**
		 * Minimum seconds between rendering and submitting the form (0–120).
		 *
		 * @param int $value Seconds from the setting.
		 */
		return max( 0, min( 120, (int) apply_filters( 'review_captain_form_min_seconds', $value ) ) );
	}

	/**
	 * Uniqueness key author + context (HMAC; the e-mail itself is not derivable from it).
	 *
	 * @param string $email E-mail.
	 * @param string $type  Context type.
	 * @param string $cid   Context ID.
	 */
	public static function submission_key( string $email, string $type, string $cid ): string {
		return hash_hmac( 'sha1', strtolower( trim( $email ) ) . '|' . $type . '|' . $cid, wp_salt( 'auth' ) );
	}

	/**
	 * Signature of a context list (multi-product form).
	 *
	 * @param string       $type Context type.
	 * @param list<string> $ids  Context IDs.
	 */
	public static function sign_context_list( string $type, array $ids ): string {
		return wp_hash( 'rc_ctxl|' . $type . '|' . implode( ',', $ids ), 'nonce' );
	}

	/**
	 * Normalise a context ID list (comma separated). Invalid IDs are dropped.
	 *
	 * @param string $raw Raw list.
	 * @return list<string>
	 */
	public static function parse_context_list( string $raw ): array {
		$ids = array();
		foreach ( explode( ',', $raw ) as $id ) {
			$id = trim( $id );
			if ( '' !== $id && preg_match( '/^[A-Za-z0-9_.:-]{1,64}$/', $id ) ) {
				$ids[ $id ] = $id;
			}
		}
		return array_slice( array_values( $ids ), 0, self::max_items() );
	}

	/**
	 * Verify a signed context list. Returns the IDs or null.
	 *
	 * @param string $type Context type (required).
	 * @param string $raw  Comma separated IDs.
	 * @param string $sig  Signature.
	 * @return list<string>|null
	 */
	public static function verify_context_list( string $type, string $raw, string $sig ): ?array {
		if ( '' === $type || ! in_array( $type, WidgetDefinition::CONTEXT_TYPES, true ) ) {
			return null;
		}
		$ids = self::parse_context_list( $raw );
		if ( ! $ids || implode( ',', $ids ) !== $raw || '' === $sig || ! hash_equals( self::sign_context_list( $type, $ids ), $sig ) ) {
			return null;
		}
		return $ids;
	}

	/**
	 * Messages/draft for a rendered form instance (from the redirect query).
	 *
	 * @param int $number Form instance number.
	 * @return array{success: string, errors: list<string>, draft: array<string, string>}
	 */
	public static function request_state( int $number ): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
		$state = array(
			'success' => '',
			'errors'  => array(),
			'draft'   => array(),
		);
		$form  = isset( $_GET['rc_form'] ) ? sanitize_key( wp_unslash( (string) $_GET['rc_form'] ) ) : '1';
		if ( (string) $number !== $form ) {
			return $state;
		}
		if ( isset( $_GET['rc_submitted'] ) ) {
			$state['success'] = '2' === $_GET['rc_submitted']
				? __( 'Danke! Ihre Bewertung ist jetzt sichtbar.', 'review-captain' )
				: __( 'Danke! Ihre Bewertung wird nach Prüfung veröffentlicht.', 'review-captain' );
		}
		if ( isset( $_GET['rc_error'] ) ) {
			$codes           = explode( ',', sanitize_text_field( wp_unslash( (string) $_GET['rc_error'] ) ) );
			$state['errors'] = array_values( array_intersect( $codes, array_keys( self::error_messages() ) ) );
		}
		if ( isset( $_GET['rc_draft'] ) ) {
			$token = preg_replace( '/[^a-z0-9]/', '', (string) wp_unslash( $_GET['rc_draft'] ) );
			$draft = '' !== $token ? get_transient( 'review_captain_draft_' . $token ) : false;
			if ( is_array( $draft ) ) {
				$state['draft'] = array_map( 'strval', array_intersect_key( $draft, array_flip( array( 'rating', 'title', 'body', 'name' ) ) ) );
			}
		}
		// phpcs:enable
		return $state;
	}

	/**
	 * All error messages.
	 *
	 * @return array<string, string>
	 */
	public static function error_messages(): array {
		return array(
			'disabled'     => __( 'Bewertungen können derzeit nicht abgegeben werden.', 'review-captain' ),
			'nonce'        => __( 'Die Sitzung ist abgelaufen. Bitte laden Sie die Seite neu und senden Sie das Formular erneut.', 'review-captain' ),
			'too_fast'     => __( 'Das Formular wurde zu schnell abgeschickt. Bitte versuchen Sie es erneut.', 'review-captain' ),
			'expired'      => __( 'Das Formular ist abgelaufen. Bitte laden Sie die Seite neu.', 'review-captain' ),
			'context'      => __( 'Das Formular ist ungültig. Bitte laden Sie die Seite neu.', 'review-captain' ),
			'rate_limited' => __( 'Sie haben bereits mehrere Bewertungen gesendet. Bitte versuchen Sie es später erneut.', 'review-captain' ),
			'rating'       => __( 'Bitte wählen Sie eine Bewertung von 1 bis 5 Sternen.', 'review-captain' ),
			'title_long'   => __( 'Der Titel ist zu lang (höchstens 150 Zeichen).', 'review-captain' ),
			'body_short'   => __( 'Der Bewertungstext ist zu kurz.', 'review-captain' ),
			'body_long'    => __( 'Der Bewertungstext ist zu lang.', 'review-captain' ),
			'name'         => __( 'Bitte geben Sie Ihren Namen an (höchstens 100 Zeichen).', 'review-captain' ),
			'email'        => __( 'Bitte geben Sie eine gültige E-Mail-Adresse an.', 'review-captain' ),
			'email_blocked' => __( 'Mit dieser E-Mail-Adresse kann keine Bewertung abgegeben werden. Bitte verwenden Sie eine andere Adresse.', 'review-captain' ),
			'consent'      => __( 'Bitte bestätigen Sie die Einwilligung zur Veröffentlichung.', 'review-captain' ),
			'save'         => __( 'Ihre Bewertung konnte nicht gespeichert werden. Bitte versuchen Sie es später erneut.', 'review-captain' ),
			'duplicate'       => __( 'Sie haben dieses Produkt bereits bewertet.', 'review-captain' ),
			'items_empty'     => __( 'Bitte bewerten Sie mindestens ein Produkt.', 'review-captain' ),
			'item_rating'     => __( 'Bitte wählen Sie für jedes bewertete Produkt 1 bis 5 Sterne.', 'review-captain' ),
			'item_body_short' => __( 'Der Text zu einem bewerteten Produkt ist zu kurz.', 'review-captain' ),
			'item_body_long'  => __( 'Der Text zu einem bewerteten Produkt ist zu lang.', 'review-captain' ),
			'photo_upload'      => PhotoStore::message( 'photo_upload' ),
			'photo_size'        => PhotoStore::message( 'photo_size' ),
			'photo_type'        => PhotoStore::message( 'photo_type' ),
			'photo_invalid'     => PhotoStore::message( 'photo_invalid' ),
			'photo_unsupported' => PhotoStore::message( 'photo_unsupported' ),
		);
	}

	/**
	 * Message for an error code.
	 *
	 * @param string $code Code.
	 */
	public static function error_message( string $code ): string {
		return self::error_messages()[ $code ] ?? '';
	}

	/**
	 * Signed render timestamp "ts:sig".
	 *
	 * @param int $ts Unix time.
	 */
	public static function sign_timestamp( int $ts ): string {
		return $ts . ':' . wp_hash( 'rc_ts|' . $ts, 'nonce' );
	}

	/**
	 * Verify timestamp. Returns error code or null.
	 *
	 * @param string $value Value.
	 */
	public static function verify_timestamp( string $value ): ?string {
		if ( ! preg_match( '/^(\d{9,11}):([a-f0-9]{32,64})$/', $value, $m ) || ! hash_equals( wp_hash( 'rc_ts|' . $m[1], 'nonce' ), $m[2] ) ) {
			return 'expired';
		}
		$age = time() - (int) $m[1];
		if ( $age < self::min_seconds() ) {
			return 'too_fast';
		}
		return $age > self::MAX_AGE ? 'expired' : null;
	}

	/**
	 * Signature for the form context (prevents visitors from choosing arbitrary contexts).
	 *
	 * @param string $type Context type.
	 * @param string $id   Context ID.
	 */
	public static function sign_context( string $type, string $id ): string {
		return wp_hash( 'rc_ctx|' . $type . '|' . $id, 'nonce' );
	}

	/**
	 * Verify context: whitelist + signature.
	 *
	 * @param string $type Type.
	 * @param string $id   ID.
	 * @param string $sig  Signature.
	 */
	public static function verify_context( string $type, string $id, string $sig ): bool {
		if ( '' !== $type && ! in_array( $type, WidgetDefinition::CONTEXT_TYPES, true ) ) {
			return false;
		}
		if ( '' !== $id && ! preg_match( '/^[A-Za-z0-9_.:-]{1,64}$/', $id ) ) {
			return false;
		}
		if ( '' === $type && '' !== $id ) {
			return false;
		}
		return '' !== $sig && hash_equals( self::sign_context( $type, $id ), $sig );
	}

	/**
	 * Rate-limit key from the hashed IP (never the plain IP).
	 */
	public static function rate_key(): string {
		return 'review_captain_rl_' . substr( wp_hash( 'rc_ip|' . self::rate_subject( \ReviewCaptain\Security\RateLimiter::client_ip() ), 'nonce' ), 0, 24 );
	}

	/**
	 * Rate-limit subject for an address: IPv4 as is, IPv6 reduced to its /64 prefix. A single
	 * IPv6 connection usually owns a whole /64, so per-address counting could be bypassed by
	 * rotating addresses (security review 2026-11).
	 *
	 * @param string $ip Remote address.
	 */
	public static function rate_subject( string $ip ): string {
		if ( false === filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			return $ip;
		}
		$packed = inet_pton( $ip );
		if ( false === $packed || 16 !== strlen( $packed ) ) {
			return $ip;
		}
		// IPv4-mapped addresses (::ffff:a.b.c.d) count as the IPv4 address.
		if ( str_starts_with( $packed, str_repeat( "\0", 10 ) . "\xff\xff" ) ) {
			return (string) inet_ntop( substr( $packed, 12 ) );
		}
		return (string) inet_ntop( substr( $packed, 0, 8 ) . str_repeat( "\0", 8 ) ) . '/64';
	}

	/**
	 * Counter value in the current window.
	 *
	 * @param string $counter count|fails.
	 */
	private function rate_count( string $counter = 'count' ): int {
		$v = get_transient( self::rate_key() );
		return is_array( $v ) && isset( $v[ $counter ], $v['start'] ) && (int) $v['start'] > time() - HOUR_IN_SECONDS ? (int) $v[ $counter ] : 0;
	}

	/**
	 * Increment a counter (fixed one-hour window).
	 *
	 * @param string $counter count|fails.
	 */
	private function rate_hit( string $counter = 'count' ): void {
		$key = self::rate_key();
		$v   = get_transient( $key );
		if ( ! is_array( $v ) || ! isset( $v['start'] ) || (int) $v['start'] <= time() - HOUR_IN_SECONDS ) {
			$v = array(
				'count' => 0,
				'fails' => 0,
				'start' => time(),
			);
		}
		$v[ $counter ] = (int) ( $v[ $counter ] ?? 0 ) + 1;
		set_transient( $key, $v, max( 60, HOUR_IN_SECONDS - ( time() - (int) $v['start'] ) ) );
	}

	/**
	 * Notify moderators (SET-RC-09) – no review text, no e-mail address, only a hint and a link.
	 *
	 * @param Review $review Review.
	 */
	private function notify_admin( Review $review ): void {
		/**
		 * Filter the moderation URL used in notification mails.
		 *
		 * @param string $url    URL.
		 * @param Review $review Review.
		 */
		$url = (string) apply_filters( 'review_captain_moderation_url', admin_url( 'admin.php?page=review-captain' ), $review );
		( new Notifier( $this->plugin->settings() ) )->notify(
			Notifier::EVENT_REVIEW,
			array(
				'moderation_url' => $url,
				'approved'       => ReviewStatus::APPROVED === $review->status,
			)
		);
	}

	/**
	 * Validated return URL without previous rc_* parameters.
	 *
	 * @param array<string, mixed> $post POST.
	 */
	private function return_url( array $post ): string {
		$fallback = home_url( '/' );
		$url      = isset( $post['rc_return'] ) ? esc_url_raw( (string) $post['rc_return'] ) : '';
		$url      = '' !== $url ? wp_validate_redirect( $url, $fallback ) : $fallback;
		$url      = strtok( (string) $url, '#' );
		return remove_query_arg( array( 'rc_submitted', 'rc_error', 'rc_form', 'rc_draft' ), false !== $url ? $url : $fallback );
	}

	/**
	 * Redirect and stop.
	 *
	 * @param string $url URL.
	 */
	protected function redirect( string $url ): void {
		wp_safe_redirect( $url, 303 );
		exit;
	}
}
