<?php
/**
 * Declarative settings schema (SET-003, docs/settings/einstellungsinventar.md).
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Settings;

defined( 'ABSPATH' ) || exit;

final class Schema {

	public const GROUPS = array( 'general', 'sync', 'requests', 'submissions', 'display', 'privacy', 'access', 'advanced' );

	/** Groups whose fields only take effect with a Pro entitlement (values are still stored by Core). */
	public const GROUP_ENTITLEMENTS = array(
		'sync'     => 'review.platform_sync',
		'requests' => 'review.review_requests',
	);

	/**
	 * Group labels (translated at runtime).
	 *
	 * @return array<string,string>
	 */
	public static function group_labels(): array {
		return array(
			'general'     => __( 'General', 'review-captain' ),
			'sync'        => __( 'Sources & sync', 'review-captain' ),
			'requests'    => __( 'Requests & delivery', 'review-captain' ),
			'submissions' => __( 'Submissions & moderation', 'review-captain' ),
			'display'     => __( 'Display', 'review-captain' ),
			'privacy'     => __( 'Privacy & retention', 'review-captain' ),
			'access'      => __( 'Access & roles', 'review-captain' ),
			'advanced'    => __( 'Advanced', 'review-captain' ),
		);
	}

	/**
	 * Field definitions. Keys: group, type, default, level, options (enum), min/max (int), locked (bool), overridable.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function fields(): array {
		return array(
			// General.
			'brand_name'                            => array(
				'group'       => 'general',
				'type'        => 'text',
				'default'     => '',
				'level'       => 'basic',
				'overridable' => array( 'campaign' ),
			),
			'default_locale'                        => array(
				'group'       => 'general',
				'type'        => 'text',
				'default'     => '',
				'level'       => 'basic',
				'overridable' => array( 'campaign' ),
			),
			'date_format'                           => array(
				'group'       => 'general',
				'type'        => 'text',
				'default'     => '',
				'level'       => 'advanced',
				'overridable' => array( 'widget' ),
			),
			'new_review_type_default'               => array(
				'group'   => 'general',
				'type'    => 'enum',
				'default' => 'service',
				'options' => array( 'service', 'product', 'app', 'event', 'voucher', 'general' ),
				'level'   => 'basic',
			),

			// Sources & sync (Pro effect).
			'sync_interval_hours'                   => array(
				'group'         => 'sync',
				'type'          => 'int',
				'default'       => 12,
				'min'           => 1,
				'max'           => 24,
				'level'         => 'basic',
				'overridable'   => array( 'profile' ),
				'override_rule' => 'lower',
			),
			'sync_lookback_days'                    => array(
				'group'       => 'sync',
				'type'        => 'int',
				'default'     => 365,
				'min'         => 1,
				'max'         => 3650,
				'level'       => 'basic',
				'overridable' => array( 'profile' ),
			),
			'sync_max_items_per_run'                => array(
				'group'       => 'sync',
				'type'        => 'int',
				'default'     => 200,
				'min'         => 10,
				'max'         => 1000,
				'level'       => 'advanced',
				'overridable' => array( 'profile' ),
			),
			'sync_quota_warn_percent'               => array(
				'group'       => 'sync',
				'type'        => 'int',
				'default'     => 80,
				'min'         => 10,
				'max'         => 100,
				'level'       => 'advanced',
				'overridable' => array( 'profile' ),
			),

			// Requests & delivery (Pro effect).
			'sender_name'                           => array(
				'group'       => 'requests',
				'type'        => 'text',
				'default'     => '',
				'level'       => 'basic',
				'privacy'     => 1,
				'overridable' => array( 'campaign' ),
			),
			'sender_email'                          => array(
				'group'       => 'requests',
				'type'        => 'email',
				'default'     => '',
				'level'       => 'basic',
				'privacy'     => 1,
				'overridable' => array( 'campaign' ),
			),
			'reply_to'                              => array(
				'group'       => 'requests',
				'type'        => 'email',
				'default'     => '',
				'level'       => 'advanced',
				'privacy'     => 1,
				'show_if'     => 'sender_email',
				'overridable' => array( 'campaign' ),
			),
			'test_recipient'                        => array(
				'group'   => 'requests',
				'type'    => 'email',
				'default' => '',
				'level'   => 'basic',
				'privacy' => 1,
			),
			'send_window_start'                     => array(
				'group'         => 'requests',
				'type'          => 'time',
				'default'       => '09:00',
				'level'         => 'basic',
				'overridable'   => array( 'campaign' ),
				'override_rule' => 'narrower',
			),
			'send_window_end'                       => array(
				'group'         => 'requests',
				'type'          => 'time',
				'default'       => '19:00',
				'level'         => 'basic',
				'overridable'   => array( 'campaign' ),
				'override_rule' => 'narrower',
			),
			'daily_send_limit'                      => array(
				'group'   => 'requests',
				'type'    => 'int',
				'default' => 200,
				'min'     => 1,
				'max'     => 10000,
				'level'   => 'advanced',
			),
			'cooldown_days'                         => array(
				'group'         => 'requests',
				'type'          => 'int',
				'default'       => 30,
				'min'           => 0,
				'max'           => 365,
				'level'         => 'basic',
				'overridable'   => array( 'campaign' ),
				'override_rule' => 'higher',
			),
			'reminders_default'                     => array(
				'group'       => 'requests',
				'type'        => 'bool',
				'default'     => false,
				'level'       => 'advanced',
				'overridable' => array( 'campaign' ),
			),
			'existing_customer_basis'               => array(
				'group'         => 'requests',
				'type'          => 'bool',
				'default'       => false,
				'level'         => 'advanced',
				'overridable'   => array( 'campaign' ),
				'override_rule' => 'only_disable',
			),
			'bounce_behavior'                       => array(
				'group'   => 'requests',
				'type'    => 'enum',
				'default' => 'suppress',
				'options' => array( 'suppress', 'log_only' ),
				'level'   => 'advanced',
			),

			// Submissions & moderation.
			'default_status'                        => array(
				'group'         => 'submissions',
				'type'          => 'enum',
				'default'       => 'pending',
				'options'       => array( 'pending', 'approved' ),
				'level'         => 'basic',
				'overridable'   => array( 'form' ),
				'override_rule' => 'stricter',
			),
			'honeypot_enabled'                      => array(
				'group'   => 'submissions',
				'type'    => 'bool',
				'default' => true,
				'level'   => 'advanced',
			),
			'rate_limit_per_hour'                   => array(
				'group'       => 'submissions',
				'type'        => 'int',
				'default'     => 3,
				'min'         => 1,
				'max'         => 100,
				'level'       => 'advanced',
				'overridable' => array( 'form' ),
			),
			'min_text_length'                       => array(
				'group'       => 'submissions',
				'type'        => 'int',
				'default'     => 0,
				'min'         => 0,
				'max'         => 1000,
				'level'       => 'advanced',
				'overridable' => array( 'form' ),
			),
			'max_text_length'                       => array(
				'group'       => 'submissions',
				'type'        => 'int',
				'default'     => 5000,
				'min'         => 100,
				'max'         => 20000,
				'level'       => 'advanced',
				'overridable' => array( 'form' ),
			),
			'require_email'                         => array(
				'group'       => 'submissions',
				'type'        => 'bool',
				'default'     => false,
				'level'       => 'basic',
				'overridable' => array( 'form' ),
			),
			'consent_text'                          => array(
				'group'       => 'submissions',
				'type'        => 'textarea',
				'default'     => '',
				'level'       => 'basic',
				'overridable' => array( 'form' ),
			),
			'notify_moderators'                     => array(
				'group'   => 'submissions',
				'type'    => 'bool',
				'default' => true,
				'level'   => 'basic',
			),

			// Display.
			'show_date'                             => array(
				'group'       => 'display',
				'type'        => 'bool',
				'default'     => true,
				'level'       => 'basic',
				'overridable' => array( 'widget' ),
			),
			'author_mode'                           => array(
				'group'       => 'display',
				'type'        => 'enum',
				'default'     => 'first_name_initial',
				'options'     => array( 'full', 'first_name_initial', 'initials', 'anonymous' ),
				'level'       => 'basic',
				'overridable' => array( 'widget' ),
			),
			'show_avatar'                           => array(
				'group'       => 'display',
				'type'        => 'bool',
				'default'     => false,
				'level'       => 'basic',
				'overridable' => array( 'widget' ),
			),
			'empty_state'                           => array(
				'group'       => 'display',
				'type'        => 'enum',
				'default'     => 'hide',
				'options'     => array( 'hide', 'message' ),
				'level'       => 'advanced',
				'overridable' => array( 'widget' ),
			),
			'load_css'                              => array(
				'group'   => 'display',
				'type'    => 'bool',
				'default' => true,
				'level'   => 'advanced',
			),
			'source_attribution'                    => array(
				'group'   => 'display',
				'type'    => 'bool',
				'default' => true,
				'level'   => 'basic',
				'locked'  => true,
			),

			// Privacy & retention.
			'retention_requests_days'               => array(
				'group'   => 'privacy',
				'type'    => 'int',
				'default' => 365,
				'min'     => 30,
				'max'     => 1825,
				'level'   => 'basic',
			),
			'retention_pending_requests_days'       => array(
				'group'   => 'privacy',
				'type'    => 'int',
				'default' => 60,
				'min'     => 1,
				'max'     => 365,
				'level'   => 'advanced',
			),
			'retention_logs_days'                   => array(
				'group'   => 'privacy',
				'type'    => 'int',
				'default' => 90,
				'min'     => 7,
				'max'     => 730,
				'level'   => 'basic',
			),
			'retention_rejected_reviews_days'       => array(
				'group'   => 'privacy',
				'type'    => 'int',
				'default' => 30,
				'min'     => 1,
				'max'     => 365,
				'level'   => 'basic',
			),
			'retention_media_after_revocation_days' => array(
				'group'   => 'privacy',
				'type'    => 'int',
				'default' => 30,
				'min'     => 0,
				'max'     => 365,
				'level'   => 'advanced',
			),
			'erase_review_text_on_request'          => array(
				'group'   => 'privacy',
				'type'    => 'bool',
				'default' => true,
				'level'   => 'advanced',
			),
			'external_services_consent_mode'        => array(
				'group'   => 'privacy',
				'type'    => 'enum',
				'default' => 'click_to_load',
				'options' => array( 'click_to_load', 'consent_hook' ),
				'level'   => 'basic',
			),
			'delete_data_on_uninstall'              => array(
				'group'   => 'privacy',
				'type'    => 'bool',
				'default' => false,
				'level'   => 'basic',
			),

			// Advanced.
			'job_runner'                            => array(
				'group'   => 'advanced',
				'type'    => 'enum',
				'default' => 'auto',
				'options' => array( 'auto', 'wp_cron', 'system_cron' ),
				'level'   => 'basic',
			),
			'job_time_budget_seconds'               => array(
				'group'   => 'advanced',
				'type'    => 'int',
				'default' => 20,
				'min'     => 5,
				'max'     => 120,
				'level'   => 'advanced',
			),
			'debug_logging_until'                   => array(
				'group'    => 'advanced',
				'type'     => 'int',
				'default'  => 0,
				'min'      => 0,
				'max'      => PHP_INT_MAX,
				'level'    => 'advanced',
				'internal' => true,
			),
		);
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	public static function fields_for_group( string $group ): array {
		return array_filter(
			self::fields(),
			static function ( $field ) use ( $group ) {
				return $field['group'] === $group;
			}
		);
	}
}
