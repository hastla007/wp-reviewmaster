<?php
/**
 * Settings schema (SET-003, SET-005, SET-006).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Settings;

/**
 * Declarative definition of all core settings. Pro can extend via the
 * `review_captain_settings_schema` filter (fields with 'owner' => 'pro').
 *
 * Field keys: type (string|int|bool|enum|array|time|email|textarea), default, choices (enum),
 * min/max (int), owner (core|pro), entitlement (pro only), privacy (none|personal|config),
 * advanced (bool, progressive disclosure), scope (site|network).
 */
final class SettingsSchema {

	public const VERSION = 1;

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
				'default_language'  => array( 'type' => 'string', 'default' => '' ),
				'date_format'       => array( 'type' => 'string', 'default' => '' ),
				'new_review_status' => array(
					'type'    => 'enum',
					'default' => 'pending',
					'choices' => array( 'pending', 'approved' ),
				),
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
			'requests'    => array(
				'sender_name'       => array( 'type' => 'string', 'default' => '', 'owner' => 'pro', 'entitlement' => 'review.review_requests', 'privacy' => 'config' ),
				'sender_email'      => array( 'type' => 'email', 'default' => '', 'owner' => 'pro', 'entitlement' => 'review.review_requests', 'privacy' => 'config' ),
				'send_window_start' => array( 'type' => 'time', 'default' => '09:00', 'owner' => 'pro', 'entitlement' => 'review.review_requests' ),
				'send_window_end'   => array( 'type' => 'time', 'default' => '18:00', 'owner' => 'pro', 'entitlement' => 'review.review_requests' ),
				'daily_limit'       => array( 'type' => 'int', 'default' => 200, 'min' => 1, 'max' => 100000, 'owner' => 'pro', 'entitlement' => 'review.review_requests' ),
				'cooldown_days'     => array( 'type' => 'int', 'default' => 90, 'min' => 0, 'max' => 3650, 'owner' => 'pro', 'entitlement' => 'review.review_requests' ),
				'reminders_enabled' => array( 'type' => 'bool', 'default' => false, 'owner' => 'pro', 'entitlement' => 'review.review_requests' ),
			),
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
				// RC-077: Trusted Shops ID for the consent-gated trustbadge; empty = off.
				'trustedshops_id' => array( 'type' => 'string', 'default' => '' ),
			),
			'privacy'     => array(
				'retention_external_days'  => array( 'type' => 'int', 'default' => 30, 'min' => 1, 'max' => 3650 ),
				'retention_rejected_days'  => array( 'type' => 'int', 'default' => 90, 'min' => 1, 'max' => 3650 ),
				'retention_logs_days'      => array( 'type' => 'int', 'default' => 90, 'min' => 1, 'max' => 3650 ),
				'retention_jobs_days'      => array( 'type' => 'int', 'default' => 30, 'min' => 1, 'max' => 3650 ),
				'erase_mode'               => array(
					'type'    => 'enum',
					'default' => 'anonymize',
					'choices' => array( 'anonymize', 'delete' ),
				),
				'delete_data_on_uninstall' => array( 'type' => 'bool', 'default' => false ),
				'privacy_notice'           => array( 'type' => 'textarea', 'default' => '' ),
			),
			'access'      => array(
				'moderate_roles' => array( 'type' => 'array', 'default' => array( 'administrator', 'editor' ) ),
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
		// Protect core fields from removal or redefinition.
		foreach ( $core as $tab => $fields ) {
			foreach ( $fields as $key => $field ) {
				$filtered[ $tab ][ $key ] = $field;
			}
		}
		return $filtered;
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
				return array_values( array_unique( array_filter( array_map( 'sanitize_key', array_map( 'strval', $value ) ) ) ) );
			case 'textarea':
				return is_scalar( $value ) ? sanitize_textarea_field( (string) $value ) : $default;
			case 'string':
			default:
				return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : $default;
		}
	}
}
