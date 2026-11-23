<?php
/**
 * Settings schema (SET-003, SET-005, SET-006).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Settings;

/**
 * Declarative definition of all core settings. Add-ons extend it via the
 * `review_captain_settings_schema` filter (fields with 'owner' => 'pro'); the core itself
 * defines no add-on fields and knows nothing about licenses (SET-RC-06).
 *
 * Field keys: type (string|int|bool|enum|array|time|email|email_list|textarea|color), default, choices
 * (enum; for array: allowed values), min/max (int), max_length (string/textarea, characters),
 * max_items (array/email_list), min_items (array: fewer → default), owner (core|pro),
 * privacy (none|personal|config), advanced (bool, progressive disclosure), scope (site|network),
 * internal (bool: written by the plugin itself, never rendered, imported, exported or set from input),
 * export (bool, default true: false keeps a field out of configuration exports and imports, e.g. the
 * uninstall switch – suite uninstall standard S10).
 * Fields added by an add-on may also carry: label, description, section (heading on the settings page)
 * and replaces_hint (id or list of ids of core hint lines this field replaces, see Admin\SettingsHints).
 */
final class SettingsSchema {

	public const VERSION = 1;

	/**
	 * Accepted confirmation words for privacy.delete_data_on_uninstall (SET-RC-20, as in Form Captain).
	 */
	public const CONFIRM_WORDS = array( 'LÖSCHEN', 'LOESCHEN', 'DELETE' );

	/**
	 * Tabs in display order (SET-002).
	 *
	 * @return array<string, string>
	 */
	public static function tabs(): array {
		return array(
			'general'     => __( 'Allgemein', 'review-captain' ),
			'sources'     => __( 'Quellen & Synchronisierung', 'review-captain' ),
			'requests'    => __( 'Anfragen & Zustellung', 'review-captain' ),
			'submissions' => __( 'Einreichungen & Moderation', 'review-captain' ),
			'display'     => __( 'Darstellung', 'review-captain' ),
			'privacy'     => __( 'Datenschutz & Aufbewahrung', 'review-captain' ),
			'access'      => __( 'Zugriff & Rollen', 'review-captain' ),
			'advanced'    => __( 'Erweitert', 'review-captain' ),
		);
	}

	/**
	 * Field definitions.
	 *
	 * @return array<string, array<string, array<string, mixed>>>
	 */
	public static function fields(): array {
		$core = array(
			'general'     => array(
				'brand_name'        => array( 'type' => 'string', 'default' => '' ),
				'date_format'       => array( 'type' => 'string', 'default' => '' ),
				'new_review_status' => array(
					'type'    => 'enum',
					'default' => 'pending',
					'choices' => array( 'pending', 'approved' ),
				),
				// SET-RC-06: grey hint lines for Review Captain Pro and the dashboard info (off = none at all).
				'show_pro_hints'    => array( 'type' => 'bool', 'default' => true ),
			),
			'sources'     => array(
				'sync_window_start'       => array( 'type' => 'time', 'default' => '02:00' ),
				'sync_window_end'         => array( 'type' => 'time', 'default' => '05:00' ),
				'max_pages_per_run'       => array(
					'type'     => 'int',
					'default'  => 5,
					'min'      => 1,
					'max'      => 50,
					'advanced' => true,
				),
				'pause_after_auth_errors' => array(
					'type'     => 'int',
					'default'  => 3,
					'min'      => 1,
					'max'      => 20,
					'advanced' => true,
				),
				'removed_review_behavior' => array(
					'type'    => 'enum',
					'default' => 'tombstone',
					'choices' => array( 'hide', 'tombstone' ),
				),
			),
			// Fields of this tab come from add-ons only (SET-RC-06); stored values are kept by Settings::merge().
			'requests'    => array(),
			'submissions' => array(
				'enabled'             => array( 'type' => 'bool', 'default' => true ),
				'require_consent'     => array( 'type' => 'bool', 'default' => true ),
				'honeypot'            => array( 'type' => 'bool', 'default' => true, 'advanced' => true ),
				'rate_limit_per_hour' => array( 'type' => 'int', 'default' => 5, 'min' => 1, 'max' => 1000, 'advanced' => true ),
				'min_text_length'     => array( 'type' => 'int', 'default' => 20, 'min' => 0, 'max' => 1000 ),
				'max_text_length'     => array( 'type' => 'int', 'default' => 5000, 'min' => 50, 'max' => 20000 ),
				'allow_title'         => array( 'type' => 'bool', 'default' => true ),
				'notify_admin'        => array( 'type' => 'bool', 'default' => true ),
				// M7 (post-MVP) – all off by default so the 1.0 scope is unchanged.
				'tags_enabled'               => array( 'type' => 'bool', 'default' => false ),
				'reports_enabled'            => array( 'type' => 'bool', 'default' => false ),
				'report_threshold'           => array( 'type' => 'int', 'default' => 1, 'min' => 1, 'max' => 20, 'advanced' => true ),
				'report_rate_limit_per_hour' => array( 'type' => 'int', 'default' => 5, 'min' => 1, 'max' => 100, 'advanced' => true ),
				'photos_enabled'             => array( 'type' => 'bool', 'default' => false ),
				'photo_max_kb'               => array( 'type' => 'int', 'default' => 2048, 'min' => 100, 'max' => 10240 ),
				'multi_product_enabled'      => array( 'type' => 'bool', 'default' => false ),
				'qa_enabled'                 => array( 'type' => 'bool', 'default' => false ),
				'feedback_campaigns_enabled' => array( 'type' => 'bool', 'default' => false ),
				// RC-125A (owner decision: part of 1.0) – off by default, the operator enables it.
				'corrections_enabled'        => array( 'type' => 'bool', 'default' => false ),
				// SET-RC-04: public form texts. Empty = translated default (consent text = legal text, review pending).
				'consent_text'               => array(
					'type'       => 'textarea',
					'default'    => '',
					'max_length' => 2000,
				),
				'name_label'                 => array(
					'type'       => 'string',
					'default'    => '',
					'max_length' => 100,
				),
				'email_hint'                 => array(
					'type'       => 'string',
					'default'    => '',
					'max_length' => 300,
				),
				'submit_label'               => array(
					'type'       => 'string',
					'default'    => '',
					'max_length' => 60,
				),
				// SET-RC-09: notifications. Empty recipients = admin_email; empty subject/body = default text.
				'notify_recipients'          => array(
					'type'      => 'email_list',
					'default'   => array(),
					'max_items' => 10,
					'privacy'   => 'config',
				),
				'notify_subject'             => array(
					'type'       => 'string',
					'default'    => '',
					'max_length' => 200,
				),
				'notify_body'                => array(
					'type'       => 'textarea',
					'default'    => '',
					'max_length' => 2000,
				),
				'notify_questions'           => array(
					'type'    => 'bool',
					'default' => true,
				),
				// SET-RC-26: public reports (reasons from the fixed list, who is notified, confirmation text).
				'report_reasons'             => array(
					'type'      => 'array',
					'default'   => array( 'spam', 'offensive', 'personal_data', 'fake', 'other' ),
					'choices'   => array( 'spam', 'offensive', 'personal_data', 'fake', 'other' ),
					'min_items' => 1,
				),
				'report_notify'              => array(
					'type'    => 'enum',
					'default' => 'recipients',
					'choices' => array( 'none', 'recipients' ),
				),
				'report_confirmation'        => array(
					'type'       => 'string',
					'default'    => '',
					'max_length' => 300,
				),
				// SET-RC-21: validity of the one-time correction link.
				'correction_link_hours'      => array( 'type' => 'int', 'default' => 1, 'min' => 1, 'max' => 72, 'advanced' => true ),
				// SET-RC-10: moderation rules. They only ever hold a review for moderation (status pending);
				// a blocked e-mail domain rejects the submission with a neutral message. Lists: one entry per line.
				'min_seconds'                => array( 'type' => 'int', 'default' => 3, 'min' => 1, 'max' => 120, 'advanced' => true ),
				'hold_words'                 => array( 'type' => 'textarea', 'default' => '' ),
				'hold_links'                 => array( 'type' => 'bool', 'default' => false ),
				'block_email_domains'        => array( 'type' => 'textarea', 'default' => '' ),
				// SET-RC-27: longest edge of re-encoded photos (one photo per review, see docs/settings/reference.md).
				'photo_max_edge'             => array( 'type' => 'int', 'default' => 1600, 'min' => 400, 'max' => 4000, 'advanced' => true ),
				// SET-RC-29: client address for the rate limits behind a reverse proxy (off = REMOTE_ADDR).
				'trusted_proxy_header'       => array(
					'type'     => 'enum',
					'default'  => 'none',
					'choices'  => array( 'none', 'x-forwarded-for', 'x-real-ip', 'cf-connecting-ip' ),
					'advanced' => true,
				),
			),
			'display'     => array(
				'show_stars'     => array( 'type' => 'bool', 'default' => true ),
				'show_date'      => array( 'type' => 'bool', 'default' => true ),
				'show_author'    => array( 'type' => 'bool', 'default' => true ),
				'show_avatar'    => array( 'type' => 'bool', 'default' => false ),
				'date_style'     => array(
					'type'    => 'enum',
					'default' => 'absolute',
					'choices' => array( 'relative', 'absolute' ),
				),
				'empty_text'     => array( 'type' => 'string', 'default' => '' ),
				'reduced_motion' => array( 'type' => 'bool', 'default' => true ),
				'schema_enabled' => array( 'type' => 'bool', 'default' => true ),
				'load_css'       => array( 'type' => 'bool', 'default' => true, 'advanced' => true ),
				'show_photos'    => array( 'type' => 'bool', 'default' => false ),
				// SET-RC-01: "So prüfen wir Bewertungen" (legal text, review pending). Empty text = neutral draft.
				'verification_notice_show' => array(
					'type'    => 'bool',
					'default' => true,
				),
				'verification_notice' => array(
					'type'       => 'textarea',
					'default'    => '',
					'max_length' => 3000,
				),
				'verification_notice_position' => array(
					'type'    => 'enum',
					'default' => 'below',
					'choices' => array( 'below', 'above' ),
				),
				// SET-RC-02: texts of the verification levels (the level itself is never configurable).
				'label_purchase_verified' => array(
					'type'       => 'string',
					'default'    => '',
					'max_length' => 60,
				),
				'label_attendance_confirmed' => array(
					'type'       => 'string',
					'default'    => '',
					'max_length' => 60,
				),
				'label_unverified' => array(
					'type'       => 'string',
					'default'    => '',
					'max_length' => 60,
				),
				'label_external' => array(
					'type'       => 'string',
					'default'    => '',
					'max_length' => 60,
				),
				'label_testimonial' => array(
					'type'       => 'string',
					'default'    => '',
					'max_length' => 60,
				),
				'show_unverified_label' => array(
					'type'    => 'bool',
					'default' => false,
				),
				// SET-RC-22: colours as CSS custom properties (empty = theme/CSS default); own reviews keep the 1–5 scale.
				'star_color'     => array( 'type' => 'color', 'default' => '' ),
				'accent_color'   => array( 'type' => 'color', 'default' => '' ),
				'rating_display' => array(
					'type'    => 'enum',
					'default' => 'both',
					'choices' => array( 'stars', 'number', 'both' ),
				),
				'decimal_places' => array( 'type' => 'int', 'default' => 1, 'min' => 0, 'max' => 2 ),
				// SET-RC-23: structured data can only be narrowed (testimonials never get markup).
				'schema_contexts'    => array(
					'type'    => 'array',
					'default' => array( 'product', 'post', 'event' ),
					'choices' => array( 'product', 'post', 'event' ),
				),
				'schema_min_reviews' => array( 'type' => 'int', 'default' => 1, 'min' => 1, 'max' => 50 ),
				// SET-RC-28: global defaults for widgets, blocks and shortcodes without their own value.
				'widget_limit'         => array( 'type' => 'int', 'default' => 6, 'min' => 1, 'max' => 50 ),
				'widget_layout'        => array(
					'type'    => 'enum',
					'default' => 'grid',
					'choices' => array( 'grid', 'list' ),
				),
				'widget_columns'       => array( 'type' => 'int', 'default' => 3, 'min' => 1, 'max' => 4 ),
				'widget_heading_level' => array( 'type' => 'int', 'default' => 2, 'min' => 2, 'max' => 6 ),
				'widget_new_tab'       => array( 'type' => 'bool', 'default' => false ),
			),
			'privacy'     => array(
				'retention_external_days'  => array( 'type' => 'int', 'default' => 30, 'min' => 1, 'max' => 3650 ),
				'retention_rejected_days'  => array( 'type' => 'int', 'default' => 90, 'min' => 1, 'max' => 3650 ),
				'retention_logs_days'      => array( 'type' => 'int', 'default' => 90, 'min' => 1, 'max' => 3650 ),
				'retention_jobs_days'      => array( 'type' => 'int', 'default' => 30, 'min' => 1, 'max' => 3650 ),
				// SET-RC-17: settings and role changes are kept longer than the other logs.
				'retention_audit_days'     => array( 'type' => 'int', 'default' => 365, 'min' => 1, 'max' => 3650 ),
				// SET-RC-08: 0 = unlimited (behaviour up to now). The future default awaits owner decision E5 and a legal review.
				'retention_contact_days'   => array( 'type' => 'int', 'default' => 0, 'min' => 0, 'max' => 3650 ),
				'retention_approved_days'  => array( 'type' => 'int', 'default' => 0, 'min' => 0, 'max' => 3650 ),
				// SET-RC-21: decided author-correction versions.
				'retention_corrections_days' => array( 'type' => 'int', 'default' => 90, 'min' => 1, 'max' => 3650 ),
				'erase_mode'               => array(
					'type'    => 'enum',
					'default' => 'anonymize',
					'choices' => array( 'anonymize', 'delete' ),
				),
				// S10 suite uninstall standard: never exported or imported, switched on only with the typed word.
				'delete_data_on_uninstall' => array( 'type' => 'bool', 'default' => false, 'export' => false ),
				// SET-RC-20: UTC time the operator typed the confirmation word; empty = not confirmed.
				'delete_data_confirmed_at' => array( 'type' => 'string', 'default' => '', 'internal' => true, 'export' => false ),
				'privacy_notice'           => array( 'type' => 'textarea', 'default' => '' ),
			),
			'access'      => array(
				// SET-RC-16: one role list per capability (Capabilities::ROLE_KEYS), shown as a matrix.
				'moderate_roles' => array( 'type' => 'array', 'default' => array( 'administrator', 'editor' ) ),
				'sources_roles'  => array( 'type' => 'array', 'default' => array( 'administrator' ) ),
				'requests_roles' => array( 'type' => 'array', 'default' => array( 'administrator' ) ),
				'reports_roles'  => array( 'type' => 'array', 'default' => array( 'administrator' ) ),
				'trust_roles'    => array( 'type' => 'array', 'default' => array( 'administrator' ) ),
				'privacy_roles'  => array( 'type' => 'array', 'default' => array( 'administrator' ) ),
				'settings_roles' => array( 'type' => 'array', 'default' => array( 'administrator' ) ),
			),
			'advanced'    => array(
				'job_time_budget'      => array( 'type' => 'int', 'default' => 20, 'min' => 5, 'max' => 120, 'advanced' => true ),
				'lease_seconds'        => array( 'type' => 'int', 'default' => 300, 'min' => 60, 'max' => 3600, 'advanced' => true ),
				'debug_logging'        => array( 'type' => 'bool', 'default' => false, 'advanced' => true ),
				'use_action_scheduler' => array( 'type' => 'bool', 'default' => true, 'advanced' => true ),
			),
		);

		foreach ( $core as $tab => $fields ) {
			foreach ( $fields as $key => $field ) {
				$core[ $tab ][ $key ] = $field + array(
					'owner'    => 'core',
					'privacy'  => 'none',
					'advanced' => false,
					'scope'    => 'site',
				);
			}
		}

		/**
		 * Filter the settings schema. Pro may add fields; core fields must not be removed.
		 *
		 * @param array $core Schema.
		 */
		$filtered = apply_filters( 'review_captain_settings_schema', $core );
		if ( ! is_array( $filtered ) ) {
			return $core;
		}
		// Add-on fields: drop malformed entries, fill the same defaults (owner defaults to 'pro').
		$out = $core;
		foreach ( $filtered as $tab => $fields ) {
			if ( ! is_string( $tab ) || ! is_array( $fields ) ) {
				continue;
			}
			foreach ( $fields as $key => $field ) {
				if ( ! is_string( $key ) || isset( $core[ $tab ][ $key ] ) || ! is_array( $field ) || ! isset( $field['type'] ) || ! array_key_exists( 'default', $field ) ) {
					continue; // Core fields are protected from removal or redefinition.
				}
				$out[ $tab ][ $key ] = $field + array(
					'owner'    => 'pro',
					'privacy'  => 'none',
					'advanced' => false,
					'scope'    => 'site',
				);
			}
		}
		return $out;
	}

	/**
	 * Defaults as nested array.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function defaults(): array {
		$out = array();
		foreach ( self::fields() as $tab => $fields ) {
			foreach ( $fields as $key => $field ) {
				$out[ $tab ][ $key ] = $field['default'];
			}
		}
		return $out;
	}

	/**
	 * Sanitize one value according to its field definition. Invalid values fall back to default.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @param mixed                $value Raw value.
	 */
	public static function sanitize_value( array $field, mixed $value ): mixed {
		$default = $field['default'];
		switch ( $field['type'] ) {
			case 'bool':
				return in_array( $value, array( true, 1, '1', 'on', 'yes', 'true' ), true );
			case 'int':
				if ( ! is_numeric( $value ) ) {
					return $default;
				}
				$v = (int) $value;
				if ( isset( $field['min'] ) ) {
					$v = max( (int) $field['min'], $v );
				}
				if ( isset( $field['max'] ) ) {
					$v = min( (int) $field['max'], $v );
				}
				return $v;
			case 'enum':
				return in_array( $value, $field['choices'], true ) ? $value : $default;
			case 'time':
				return is_string( $value ) && preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $value ) ? $value : $default;
			case 'email':
				if ( '' === $value || null === $value ) {
					return '';
				}
				$v = sanitize_email( (string) $value );
				return is_email( $v ) ? $v : $default;
			case 'array':
				if ( ! is_array( $value ) ) {
					return $default;
				}
				$list = array_values( array_unique( array_filter( array_map( 'sanitize_key', array_map( 'strval', array_filter( $value, 'is_scalar' ) ) ) ) ) );
				if ( isset( $field['choices'] ) && is_array( $field['choices'] ) ) {
					$list = array_values( array_intersect( $list, array_map( 'strval', $field['choices'] ) ) );
				}
				if ( isset( $field['max_items'] ) ) {
					$list = array_slice( $list, 0, max( 0, (int) $field['max_items'] ) );
				}
				return count( $list ) < (int) ( $field['min_items'] ?? 0 ) ? $default : $list;
			case 'email_list':
				return self::sanitize_email_list( $field, $value );
			case 'color':
				// Hex colour (#rgb or #rrggbb); empty = theme/CSS default.
				$v = is_scalar( $value ) ? strtolower( trim( (string) $value ) ) : '';
				return '' === $v || preg_match( '/^#(?:[0-9a-f]{3}){1,2}$/', $v ) ? $v : $default;
			case 'textarea':
				return is_scalar( $value ) ? self::limit_length( $field, sanitize_textarea_field( (string) $value ) ) : $default;
			case 'string':
			default:
				return is_scalar( $value ) ? self::limit_length( $field, sanitize_text_field( (string) $value ) ) : $default;
		}
	}

	/**
	 * List of e-mail addresses from an array or a comma/semicolon/line separated string.
	 * Invalid entries and duplicates are dropped; at most `max_items` addresses are kept.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @param mixed                $value Raw value.
	 * @return list<string>
	 */
	public static function sanitize_email_list( array $field, mixed $value ): array {
		if ( is_array( $value ) ) {
			$parts = array_map( 'strval', array_filter( $value, 'is_scalar' ) );
		} elseif ( is_scalar( $value ) ) {
			$parts = (array) preg_split( '/[\s,;]+/', (string) $value );
		} else {
			return is_array( $field['default'] ) ? array_values( $field['default'] ) : array();
		}
		$out = array();
		foreach ( $parts as $part ) {
			$email = sanitize_email( trim( (string) $part ) );
			if ( '' !== $email && is_email( $email ) && ! in_array( strtolower( $email ), array_map( 'strtolower', $out ), true ) ) {
				$out[] = $email;
			}
		}
		return array_slice( $out, 0, max( 1, (int) ( $field['max_items'] ?? 10 ) ) );
	}

	/**
	 * Cut a text to the field's `max_length` (characters).
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @param string               $text  Sanitized text.
	 */
	private static function limit_length( array $field, string $text ): string {
		if ( isset( $field['max_length'] ) && mb_strlen( $text ) > (int) $field['max_length'] ) {
			return trim( mb_substr( $text, 0, max( 0, (int) $field['max_length'] ) ) );
		}
		return $text;
	}

	/**
	 * Validate one submitted value without correcting it (SET-RC-19). Used by the settings page and
	 * WP-CLI; import and programmatic paths keep using sanitize_value().
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @param mixed                $value Raw value.
	 * @return array{0: mixed, 1: string|null} Sanitized value and null, or null and an error message.
	 */
	public static function validate_value( array $field, mixed $value ): array {
		switch ( $field['type'] ) {
			case 'bool':
				return array( self::sanitize_value( $field, $value ), null );
			case 'int':
				$given = is_scalar( $value ) ? trim( (string) $value ) : '';
				if ( ! preg_match( '/^-?\d+$/', $given ) ) {
					return array( null, __( 'Bitte gib eine ganze Zahl ein.', 'review-captain' ) );
				}
				$v   = (int) $given;
				$min = isset( $field['min'] ) ? (int) $field['min'] : null;
				$max = isset( $field['max'] ) ? (int) $field['max'] : null;
				if ( ( null !== $min && $v < $min ) || ( null !== $max && $v > $max ) ) {
					return array( null, self::range_message( $min, $max ) );
				}
				return array( $v, null );
			case 'enum':
				$given = is_scalar( $value ) ? (string) $value : null;
				foreach ( (array) ( $field['choices'] ?? array() ) as $choice ) {
					if ( (string) $choice === $given ) {
						return array( $choice, null );
					}
				}
				return array( null, __( 'Bitte wähle einen der angebotenen Werte.', 'review-captain' ) );
			case 'time':
				$given = is_scalar( $value ) ? trim( (string) $value ) : '';
				if ( ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $given ) ) {
					return array( null, __( 'Bitte gib eine Uhrzeit im Format HH:MM ein, z. B. 09:30.', 'review-captain' ) );
				}
				return array( $given, null );
			case 'email':
				$given = is_scalar( $value ) ? trim( (string) $value ) : '';
				if ( '' === $given ) {
					return array( '', null );
				}
				if ( sanitize_email( $given ) !== $given || ! is_email( $given ) ) {
					return array( null, __( 'Bitte gib eine gültige E-Mail-Adresse ein oder lass das Feld leer.', 'review-captain' ) );
				}
				return array( $given, null );
			case 'array':
				if ( ! is_array( $value ) ) {
					return array( null, __( 'Ungültiger Wert.', 'review-captain' ) );
				}
				return array( self::sanitize_value( $field, $value ), null );
			case 'color':
				$given = is_scalar( $value ) ? strtolower( trim( (string) $value ) ) : '';
				if ( '' !== $given && ! preg_match( '/^#(?:[0-9a-f]{3}){1,2}$/', $given ) ) {
					return array( null, __( 'Bitte gib einen Hex-Farbwert wie #e0a800 ein oder lass das Feld leer.', 'review-captain' ) );
				}
				return array( $given, null );
			case 'email_list':
				$list  = self::sanitize_email_list( $field, $value );
				$parts = is_array( $value ) ? array_map( 'strval', array_filter( $value, 'is_scalar' ) ) : ( is_scalar( $value ) ? (array) preg_split( '/[\s,;]+/', (string) $value ) : array() );
				$given = array_unique( array_map( 'strtolower', array_filter( array_map( 'trim', $parts ) ) ) );
				if ( count( $given ) !== count( $list ) ) {
					/* translators: %s: maximum number of addresses */
					return array( null, sprintf( __( 'Bitte gib nur gültige E-Mail-Adressen ein, höchstens %s.', 'review-captain' ), number_format_i18n( max( 1, (int) ( $field['max_items'] ?? 10 ) ) ) ) );
				}
				return array( $list, null );
			default:
				if ( ! is_scalar( $value ) && null !== $value ) {
					return array( null, __( 'Ungültiger Wert.', 'review-captain' ) );
				}
				return array( self::sanitize_value( $field, (string) $value ), null );
		}
	}

	/**
	 * Error text for a number outside its range.
	 *
	 * @param int|null $min Minimum.
	 * @param int|null $max Maximum.
	 */
	private static function range_message( ?int $min, ?int $max ): string {
		if ( null !== $min && null !== $max ) {
			/* translators: 1: minimum, 2: maximum */
			return sprintf( __( 'Erlaubt sind Werte von %1$s bis %2$s.', 'review-captain' ), number_format_i18n( $min ), number_format_i18n( $max ) );
		}
		if ( null !== $min ) {
			/* translators: %s: minimum */
			return sprintf( __( 'Der Wert muss mindestens %s sein.', 'review-captain' ), number_format_i18n( $min ) );
		}
		/* translators: %s: maximum */
		return sprintf( __( 'Der Wert darf höchstens %s sein.', 'review-captain' ), number_format_i18n( (int) $max ) );
	}

	/**
	 * Cross-field checks of one tab (SET-RC-19). Each failed check names all fields involved, so the
	 * caller can keep their stored values together.
	 *
	 * @param string               $tab    Tab.
	 * @param array<string, mixed> $values Values of that tab after validation.
	 * @return list<array{fields: list<string>, message: string}>
	 */
	public static function cross_check( string $tab, array $values ): array {
		$out = array();
		if ( 'submissions' === $tab && isset( $values['min_text_length'], $values['max_text_length'] ) && (int) $values['min_text_length'] >= (int) $values['max_text_length'] ) {
			$out[] = array(
				'fields'  => array( 'min_text_length', 'max_text_length' ),
				'message' => __( 'Die Mindestlänge muss kleiner als die Höchstlänge sein.', 'review-captain' ),
			);
		}
		if ( 'requests' === $tab && isset( $values['send_window_start'], $values['send_window_end'] ) && $values['send_window_start'] === $values['send_window_end'] ) {
			$out[] = array(
				'fields'  => array( 'send_window_start', 'send_window_end' ),
				'message' => __( 'Beginn und Ende des Versandfensters dürfen nicht gleich sein.', 'review-captain' ),
			);
		}
		if ( 'sources' === $tab && isset( $values['sync_window_start'], $values['sync_window_end'] ) && $values['sync_window_start'] === $values['sync_window_end'] ) {
			$out[] = array(
				'fields'  => array( 'sync_window_start', 'sync_window_end' ),
				'message' => __( 'Beginn und Ende des Synchronisierungsfensters dürfen nicht gleich sein.', 'review-captain' ),
			);
		}
		if ( 'advanced' === $tab && isset( $values['lease_seconds'], $values['job_time_budget'] ) && (int) $values['lease_seconds'] <= (int) $values['job_time_budget'] ) {
			$out[] = array(
				'fields'  => array( 'lease_seconds', 'job_time_budget' ),
				'message' => __( 'Die Freigabe hängender Aufgaben muss länger dauern als das Zeitbudget je Lauf.', 'review-captain' ),
			);
		}
		/**
		 * Filter the failed cross-field checks of a settings tab (e.g. for fields added by an add-on).
		 *
		 * @param list<array{fields: list<string>, message: string}> $out    Failed checks.
		 * @param string                                             $tab    Tab.
		 * @param array<string, mixed>                               $values Tab values.
		 */
		$filtered = apply_filters( 'review_captain_settings_cross_check', $out, $tab, $values );
		return is_array( $filtered ) ? array_values( $filtered ) : $out;
	}

	/**
	 * Whether a typed word confirms "Beim Löschen des Plugins alle Daten entfernen" (SET-RC-20).
	 *
	 * @param mixed $word Typed word.
	 */
	public static function is_confirm_word( mixed $word ): bool {
		return is_string( $word ) && in_array( mb_strtoupper( trim( $word ) ), self::CONFIRM_WORDS, true );
	}

	/**
	 * Apply the confirmation rule to new privacy values (SET-RC-20): the data deletion switch only
	 * turns on together with a confirmation time. Turning it off clears the time; a switch that is
	 * already on and confirmed stays confirmed.
	 *
	 * @param array<string, mixed> $privacy New privacy values.
	 * @param array<string, mixed> $current Stored privacy values.
	 * @param mixed                $word    Typed confirmation word (null = none given, e.g. import).
	 * @return array{0: array<string, mixed>, 1: string|null} Values and an error message if refused.
	 */
	public static function guard_uninstall( array $privacy, array $current, mixed $word = null ): array {
		if ( empty( $privacy['delete_data_on_uninstall'] ) ) {
			$privacy['delete_data_on_uninstall'] = false;
			$privacy['delete_data_confirmed_at'] = '';
			return array( $privacy, null );
		}
		if ( ! empty( $current['delete_data_on_uninstall'] ) && ! empty( $current['delete_data_confirmed_at'] ) ) {
			$privacy['delete_data_confirmed_at'] = (string) $current['delete_data_confirmed_at'];
			return array( $privacy, null );
		}
		if ( self::is_confirm_word( $word ) ) {
			$privacy['delete_data_confirmed_at'] = gmdate( 'Y-m-d H:i:s' );
			return array( $privacy, null );
		}
		$privacy['delete_data_on_uninstall'] = false;
		$privacy['delete_data_confirmed_at'] = '';
		return array( $privacy, __( 'Tippe zur Bestätigung LÖSCHEN in das Feld unter dem Schalter. Ohne Bestätigung bleiben die Daten beim Deinstallieren erhalten.', 'review-captain' ) );
	}
}
