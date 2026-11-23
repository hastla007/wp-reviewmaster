<?php
/**
 * German labels, help texts and sections for the settings schema (derived from docs/settings/reference.md).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Admin;

use ReviewCaptain\Moderation\ReportStore;

/**
 * Presentation metadata only. The schema (types, defaults, validation) stays in SettingsSchema.
 * Fields added via `review_captain_settings_schema` may carry their own 'label', 'description' and
 * 'section' (heading on the settings page; see SettingsPage::render()).
 */
final class SettingsFields {

	/**
	 * Label + description per tab/key.
	 *
	 * @return array<string, array<string, array{0: string, 1: string}>>
	 */
	public static function texts(): array {
		return array(
			'general'     => array(
				'brand_name'        => array( __( 'Brand or company name', 'review-captain' ), __( 'Appears in e-mails to reviewers (e.g. the correction link) and, with Review Captain Pro, as the sender of invitations. Empty = your site title.', 'review-captain' ) ),
				'date_format'       => array( __( 'Date format', 'review-captain' ), __( 'PHP date format, e.g. d.m.Y. Empty = WordPress date format. The WordPress time zone applies.', 'review-captain' ) ),
				'new_review_status' => array( __( 'Status of new reviews', 'review-captain' ), __( 'Applies to submissions via the form. “Approve immediately” publishes unverified texts – recommended only with active moderation.', 'review-captain' ) ),
				'show_pro_hints'    => array( __( 'Show Pro hints', 'review-captain' ), __( 'Shows grey hint lines about Review Captain Pro features at the end of some settings sections and the box “In Review Captain Pro” on the dashboard. Off = no hints. This does not change any features.', 'review-captain' ) ),
			),
			'sources'     => array(
				'sync_window_start'       => array( __( 'Sync from', 'review-captain' ), __( 'Start of the time window for automatic syncs (site time).', 'review-captain' ) ),
				'sync_window_end'         => array( __( 'Sync until', 'review-captain' ), __( 'End of the time window. End before start = window spans midnight.', 'review-captain' ) ),
				'removed_review_behavior' => array( __( 'Reviews deleted at the source', 'review-captain' ), __( 'What happens when the platform reports that a review was deleted. In both cases it is no longer visible right away and is deleted for good after the retention period for external reviews (Privacy).', 'review-captain' ) ),
				'max_pages_per_run'       => array( __( 'Pages per fetch', 'review-captain' ), __( 'Higher values fetch large collections faster but put more load on the server and provider quota.', 'review-captain' ) ),
				'pause_after_auth_errors' => array( __( 'Pause after sign-in errors', 'review-captain' ), __( 'After this many consecutive sign-in errors, the source is paused. Existing reviews remain visible.', 'review-captain' ) ),
			),
			'submissions' => array(
				'enabled'             => array( __( 'Submissions enabled', 'review-captain' ), __( 'Master switch for the review form. Off = forms show a neutral notice.', 'review-captain' ) ),
				'allow_title'         => array( __( 'Offer title field', 'review-captain' ), __( 'Shows an optional “Title” field in the form. Applies to all forms on the site.', 'review-captain' ) ),
				'min_text_length'     => array( __( 'Minimum text length', 'review-captain' ), __( 'Characters. Must be less than the maximum length.', 'review-captain' ) ),
				'max_text_length'     => array( __( 'Maximum text length', 'review-captain' ), __( 'Characters.', 'review-captain' ) ),
				'require_consent'     => array( __( 'Ask for consent to publish', 'review-captain' ), __( 'Required checkbox in the form. Off = you must ensure the legal basis for publishing another way.', 'review-captain' ) ),
				'notify_admin'        => array( __( 'Notify about new reviews', 'review-captain' ), __( 'Email with a link to moderation – without review text and without contact details.', 'review-captain' ) ),
				'notify_recipients'   => array( __( 'Notification recipients', 'review-captain' ), __( 'One email address per line (or separated by commas), at most 10. Empty = the site’s administration email address. Applies to new reviews, reports and questions.', 'review-captain' ) ),
				'notify_subject'      => array( __( 'Notification subject', 'review-captain' ), __( 'For new reviews. Placeholders: {site}, {moderation_url}, {status}. Empty = “[Site] New review received”.', 'review-captain' ) ),
				'notify_body'         => array( __( 'Notification text', 'review-captain' ), __( 'For new reviews. Placeholders: {site}, {moderation_url}, {status}. Empty = default text. Review text and contact details are never included.', 'review-captain' ) ),
				'notify_questions'    => array( __( 'Notify about new questions', 'review-captain' ), __( 'Only takes effect with product questions (Q&A) switched on. Email with a link to moderation, without question text or contact details.', 'review-captain' ) ),
				'consent_text'        => array( __( 'Consent text', 'review-captain' ), __( 'Legal text – please have it reviewed. Shown next to the required checkbox. Placeholder {privacy_link} = link to your privacy page. Empty = default text.', 'review-captain' ) ),
				'name_label'          => array( __( 'Name field label', 'review-captain' ), __( 'Empty = “Name (will be published)”. Make clear that the name appears publicly.', 'review-captain' ) ),
				'email_hint'          => array( __( 'Hint below the email field', 'review-captain' ), __( 'Legal text – please have it reviewed. Empty = “Never published. We only use it for questions and privacy requests.”', 'review-captain' ) ),
				'submit_label'        => array( __( 'Submit button label', 'review-captain' ), __( 'Empty = “Submit review”.', 'review-captain' ) ),
				'honeypot'            => array( __( 'Spam trap', 'review-captain' ), __( 'Invisible field that detects automated submissions. Nonce check and rate limit always remain active.', 'review-captain' ) ),
				'rate_limit_per_hour' => array( __( 'Submissions per hour', 'review-captain' ), __( 'Per sender. A hashed value is stored briefly for counting, never the plain IP.', 'review-captain' ) ),
				'tags_enabled'               => array( __( 'Internal tags', 'review-captain' ), __( 'Moderators can add internal tags to reviews and filter by them. Tags are never displayed publicly or exported.', 'review-captain' ) ),
				'reports_enabled'            => array( __( 'Public reporting', 'review-captain' ), __( 'Shows a “Report” link below each review. Reported reviews are hidden until checked. Nothing is stored about the people reporting.', 'review-captain' ) ),
				'report_threshold'           => array( __( 'Reports before hiding', 'review-captain' ), __( 'A review is set to “Reported” once it has this many open reports.', 'review-captain' ) ),
				'report_rate_limit_per_hour' => array( __( 'Reports per hour', 'review-captain' ), __( 'Per sender (hashed value, no plain IP).', 'review-captain' ) ),
				'photos_enabled'             => array( __( 'Photo upload in form', 'review-captain' ), __( 'Optionally one photo (JPG, PNG, WebP) per review. Images are re-encoded (metadata such as GPS is removed) and only shown after approval.', 'review-captain' ) ),
				'photo_max_kb'               => array( __( 'Maximum photo size (KB)', 'review-captain' ), __( 'Larger files are rejected. The server limit (upload_max_filesize) also applies.', 'review-captain' ) ),
				'multi_product_enabled'      => array( __( 'Multi-product form', 'review-captain' ), __( 'A form with context_ids rates several products at once. Each person can then leave only one review per product.', 'review-captain' ) ),
				'qa_enabled'                 => array( __( 'Product questions (Q&A)', 'review-captain' ), __( 'Questions and answers via [review_captain_qa]. Separate from reviews, moderated, without schema markup.', 'review-captain' ) ),
				'feedback_campaigns_enabled' => array( __( 'Feedback links and QR codes', 'review-captain' ), __( 'Generates feedback links with a QR code (SVG) and an optional WhatsApp click-to-chat link.', 'review-captain' ) ),
				'report_reasons'             => array( __( 'Report reasons', 'review-captain' ), __( 'These reasons can be chosen in the “Report” form. At least one reason always stays active.', 'review-captain' ) ),
				'report_notify'              => array( __( 'Notify about reports', 'review-captain' ), __( 'Email to the notification recipients for each accepted report, with reason and link to moderation – without details about the reporting person.', 'review-captain' ) ),
				'report_confirmation'        => array( __( 'Message to reporters', 'review-captain' ), __( 'Appears after a report is sent. Empty = “Thank you for your report. We will review it.”', 'review-captain' ) ),
				'corrections_enabled'        => array( __( 'Corrections by authors', 'review-captain' ), __( 'Whoever wrote a review can correct or withdraw it through a one-time link by email (or while logged in). Corrections become visible only after you approve them; the card then shows “edited on …”.', 'review-captain' ) ),
				'correction_link_hours'      => array( __( 'Correction link validity (hours)', 'review-captain' ), __( 'The link works once and then expires automatically.', 'review-captain' ) ),
				'min_seconds'                => array( __( 'Minimum time to fill in (seconds)', 'review-captain' ), __( 'Forms sent faster are treated as automated and rejected with a neutral message.', 'review-captain' ) ),
				'hold_words'                 => array( __( 'Words that hold a review for moderation', 'review-captain' ), __( 'One word or phrase per line, not case-sensitive. A review containing an entry waits for moderation – even if new reviews are approved immediately. Nothing is ever deleted.', 'review-captain' ) ),
				'hold_links'                 => array( __( 'Hold reviews with links', 'review-captain' ), __( 'Reviews with a web address (http, www.) in the title or text wait for moderation.', 'review-captain' ) ),
				'block_email_domains'        => array( __( 'Blocked email domains', 'review-captain' ), __( 'One domain per line, e.g. throwaway.example. Submissions with addresses at these domains (including subdomains) are rejected with a neutral message and not stored.', 'review-captain' ) ),
				'photo_max_edge'             => array( __( 'Longest photo edge (pixels)', 'review-captain' ), __( 'Larger photos are scaled down when re-encoded. Each review keeps a single photo.', 'review-captain' ) ),
				'captcha_provider'           => array( __( 'Spam protection (captcha)', 'review-captain' ), __( '“Built-in computation check” lets the browser compute briefly in the background (proof of work) before reviews, reports and questions are accepted. No puzzle, no cookies, no request to third parties, no personal data. Without JavaScript the form cannot be submitted; visitors see a notice. External providers only via filter and only after a legal review.', 'review-captain' ) ),
				'trusted_proxy_header'       => array( __( 'Sender address behind a proxy', 'review-captain' ), __( 'Only set this if your site sits behind a reverse proxy or CDN that sets this header itself. Otherwise senders can bypass the rate limit. The last address in the header is used and stored only as a hash.', 'review-captain' ) ),
			),
			'display'     => array(
				'show_stars'     => array( __( 'Show stars', 'review-captain' ), __( 'Stars are always also output as text for screen readers.', 'review-captain' ) ),
				'show_date'      => array( __( 'Show date', 'review-captain' ), __( 'Shows the publication date, otherwise the date received. Individual widgets may differ.', 'review-captain' ) ),
				'date_style'     => array( __( 'Date style', 'review-captain' ), __( 'Relative (“3 days ago”) goes stale faster on cached pages.', 'review-captain' ) ),
				'show_author'    => array( __( 'Show author', 'review-captain' ), __( 'Personal data.', 'review-captain' ) ),
				'show_avatar'    => array( __( 'Show profile picture', 'review-captain' ), __( 'Personal data; depending on your WordPress settings, Gravatar is contacted.', 'review-captain' ) ),
				'empty_text'     => array( __( 'Empty list text', 'review-captain' ), __( 'Empty = “No reviews yet.”', 'review-captain' ) ),
				'reduced_motion' => array( __( 'Reduce motion', 'review-captain' ), __( 'No automatic animations or carousel rotation. The visitor’s system setting is always respected.', 'review-captain' ) ),
				'load_css'       => array( __( 'Load plugin CSS', 'review-captain' ), __( 'Off = your theme must style the output itself (rc-… classes).', 'review-captain' ) ),
				'show_photos'    => array( __( 'Show photos', 'review-captain' ), __( 'Shows photos of approved reviews in the cards. May contain personal data.', 'review-captain' ) ),
				'schema_enabled' => array( __( 'Output review markup (schema.org)', 'review-captain' ), __( 'Structured data for approved reviews submitted on this site. External reviews never get markup. Off = no review markup; the visible output stays the same.', 'review-captain' ) ),
				'verification_notice_show'     => array( __( 'Show the notice “How we check reviews”', 'review-captain' ), __( 'Expandable notice on every review widget and on the form. Explains whether and how reviews are checked.', 'review-captain' ) ),
				'verification_notice_title'    => array( __( 'Notice heading', 'review-captain' ), __( 'Text of the expandable link, e.g. “How we check reviews”. Empty = this default text.', 'review-captain' ) ),
				'verification_notice'          => array( __( 'Text “How we check reviews”', 'review-captain' ), __( 'Legal text – please have it reviewed (transparency duty, per country). Empty = neutral draft that only describes the check levels that occur on your site. Only describe what you actually check.', 'review-captain' ) ),
				'verification_notice_position' => array( __( 'Position of the notice', 'review-captain' ), __( 'Above or below the list or form.', 'review-captain' ) ),
				'label_purchase_verified'      => array( __( 'Label “Purchase verified”', 'review-captain' ), __( 'Empty = “Verified purchase”. Only the text can be changed; the level is set only by a connected, trusted source.', 'review-captain' ) ),
				'label_attendance_confirmed'   => array( __( 'Label “Attendance confirmed”', 'review-captain' ), __( 'Empty = “Attendance confirmed”. Only the text can be changed; the level is set only by a connected, trusted source.', 'review-captain' ) ),
				'label_external'               => array( __( 'Label “External platform”', 'review-captain' ), __( 'Empty = “External platform review”. Source and link to the original always stay visible.', 'review-captain' ) ),
				'label_testimonial'            => array( __( 'Label “Testimonial”', 'review-captain' ), __( 'Empty = “Testimonial from this site”.', 'review-captain' ) ),
				'label_unverified'             => array( __( 'Label “Unverified”', 'review-captain' ), __( 'Empty = “Unverified”. Only appears when “Label unverified reviews” is on.', 'review-captain' ) ),
				'show_unverified_label'        => array( __( 'Label unverified reviews', 'review-captain' ), __( 'Visibly shows the label “Unverified” on reviews without proof of purchase or attendance.', 'review-captain' ) ),
				'star_color'     => array( __( 'Star colour', 'review-captain' ), __( 'Hex colour value, e.g. #e0a800. Empty = colour from the theme or plugin CSS. Make sure the contrast is sufficient.', 'review-captain' ) ),
				'accent_color'   => array( __( 'Accent colour', 'review-captain' ), __( 'For buttons, links and focus outlines. Hex colour value; empty = theme colour.', 'review-captain' ) ),
				'rating_display' => array( __( 'Show rating as', 'review-captain' ), __( 'Screen readers always get the rating as text. Scales other than 1–5 are never converted into stars.', 'review-captain' ) ),
				'decimal_places' => array( __( 'Decimal places for averages', 'review-captain' ), __( 'Applies to averages in the summary. Reviews from this site stay whole stars from 1 to 5.', 'review-captain' ) ),
				'schema_contexts'    => array( __( 'Markup for', 'review-captain' ), __( 'Structured data is output only for these content types.', 'review-captain' ) ),
				'schema_min_reviews' => array( __( 'Minimum number of reviews for markup', 'review-captain' ), __( 'Structured data only from this many eligible, visible reviews on the page.', 'review-captain' ) ),
				'widget_limit'         => array( __( 'Number of reviews', 'review-captain' ), __( 'Default when a widget, block or shortcode has no number of its own (at most 50).', 'review-captain' ) ),
				'widget_layout'        => array( __( 'Appearance', 'review-captain' ), __( 'Grid shows reviews as cards side by side, list shows them one below the other. Applies when a widget, block or shortcode has no layout of its own.', 'review-captain' ) ),
				'widget_columns'       => array( __( 'Grid columns', 'review-captain' ), __( 'Narrow screens automatically show fewer.', 'review-captain' ) ),
				'widget_heading_level' => array( __( 'Heading level of the cards', 'review-captain' ), __( 'h2 to h6. Choose the level that fits the outline of your page.', 'review-captain' ) ),
				'widget_new_tab'       => array( __( 'Links to the original in a new tab', 'review-captain' ), __( 'Opens links to the review on the platform in a new tab. The link announces this to screen readers.', 'review-captain' ) ),
			),
			'privacy'     => array(
				'retention_external_days'  => array( __( 'Unconfirmed external reviews (days)', 'review-captain' ), __( 'How long a platform review stays stored once the source no longer confirms it.', 'review-captain' ) ),
				'retention_rejected_days'  => array( __( 'Rejected reviews (days)', 'review-captain' ), __( 'Then permanently deleted, including history.', 'review-captain' ) ),
				'privacy_notice'           => array( __( 'Privacy notice in form', 'review-captain' ), __( 'Empty = short default text with a link to your privacy page.', 'review-captain' ) ),
				'erase_mode'               => array( __( 'Erasure request behavior', 'review-captain' ), __( 'What “Erase Personal Data” (Tools) does with reviews from this email address.', 'review-captain' ) ),
				'retention_logs_days'      => array( __( 'Logs (days)', 'review-captain' ), __( 'Logs contain no review texts or credentials.', 'review-captain' ) ),
				'retention_jobs_days'      => array( __( 'Completed background tasks (days)', 'review-captain' ), __( 'Completed background tasks are deleted after this. They contain no review texts.', 'review-captain' ) ),
				'retention_audit_days'     => array( __( 'Settings change log (days)', 'review-captain' ), __( 'How long entries about settings and role rights are kept with old and new value. Texts are shortened, email addresses masked, credentials never included.', 'review-captain' ) ),
				'retention_contact_days'   => array( __( 'Contact address of reviews (days)', 'review-captain' ), __( 'After this the email address of the review is removed; the review stays. 0 = unlimited. Without an address, correction links are no longer possible for this review. Please have the period legally reviewed.', 'review-captain' ) ),
				'retention_approved_days'  => array( __( 'Approved reviews from this site (days)', 'review-captain' ), __( 'After this, reviews from this site are deleted for good (counted from receipt). 0 = unlimited. Platform reviews are not affected. Please have the period legally reviewed.', 'review-captain' ) ),
				'retention_corrections_days' => array( __( 'Decided correction versions (days)', 'review-captain' ), __( 'Earlier and rejected versions of corrected reviews are deleted after this.', 'review-captain' ) ),
				'delete_data_on_uninstall' => array( __( 'Remove all data when the plugin is deleted', 'review-captain' ), __( 'Off (default) = all data is kept. On = when the plugin is deleted, the following is removed from this site: all reviews with photos, replies, correction versions, reports and tags, profiles and sources, questions, imports, background tasks, logs, all settings and the Review Captain capabilities of the roles. To switch it on, type DELETE (or LÖSCHEN); the time of the confirmation is stored. The setting is not exported. Scheduled tasks are always removed on deletion. Data at Google, Trustpilot etc. is never changed.', 'review-captain' ) ),
				'delete_data_confirmed_at' => array( __( 'Deletion confirmed on (UTC)', 'review-captain' ), __( 'Set when confirming with DELETE and cannot be changed directly.', 'review-captain' ) ),
			),
			'access'      => array(
				'moderate_roles' => array( __( 'Moderate reviews', 'review-captain' ), __( 'Check submitted reviews, questions and corrections. Moderators see submitted reviews including the contact address.', 'review-captain' ) ),
				'sources_roles'  => array( __( 'Manage sources', 'review-captain' ), __( 'Create, connect and sync profiles and platform sources.', 'review-captain' ) ),
				'requests_roles' => array( __( 'Manage requests', 'review-captain' ), __( 'Manage feedback links and (with Review Captain Pro) review requests.', 'review-captain' ) ),
				'reports_roles'  => array( __( 'View reports & diagnostics', 'review-captain' ), __( 'View diagnostics, logs and analytics.', 'review-captain' ) ),
				'trust_roles'    => array( __( 'Manage trust badges', 'review-captain' ), __( 'Manage trust signs and badges (Review Captain Pro).', 'review-captain' ) ),
				'privacy_roles'  => array( __( 'Manage privacy', 'review-captain' ), __( 'Change the tab “Privacy & retention”: periods, erasure requests, uninstall.', 'review-captain' ) ),
				'settings_roles' => array( __( 'Change settings', 'review-captain' ), __( 'Change, export and import the Review Captain settings.', 'review-captain' ) ),
			),
			'advanced'    => array(
				'use_action_scheduler' => array( __( 'Use Action Scheduler', 'review-captain' ), __( 'If available (e.g. via WooCommerce), otherwise WP-Cron. For low-traffic sites we recommend a server cron.', 'review-captain' ) ),
				'job_time_budget'      => array( __( 'Time budget per run (seconds)', 'review-captain' ), __( 'Only change if diagnostics report timeouts.', 'review-captain' ) ),
				'lease_seconds'        => array( __( 'Release stuck tasks after (seconds)', 'review-captain' ), __( 'Must be longer than the time budget.', 'review-captain' ) ),
				'debug_logging'        => array( __( 'Error log', 'review-captain' ), __( 'Also writes failed background tasks (number, type, error code) to the server’s PHP error log. Contains no credentials, review texts or email addresses.', 'review-captain' ) ),
			),
		);
	}

	/**
	 * Sections per tab (heading => keys). Keys not listed land in "Weitere Optionen".
	 *
	 * @return array<string, array<string, list<string>>>
	 */
	public static function sections(): array {
		return array(
			'general'     => array(
				__( 'Identity', 'review-captain' )       => array( 'brand_name' ),
				__( 'Date', 'review-captain' )           => array( 'date_format' ),
				__( 'New reviews', 'review-captain' )    => array( 'new_review_status' ),
				__( 'Hints', 'review-captain' )        => array( 'show_pro_hints' ),
			),
			'sources'     => array(
				__( 'Time window', 'review-captain' )       => array( 'sync_window_start', 'sync_window_end' ),
				__( 'Deleted reviews', 'review-captain' ) => array( 'removed_review_behavior' ),
			),
			'submissions' => array(
				__( 'Form', 'review-captain' )          => array( 'enabled', 'allow_title', 'min_text_length', 'max_text_length' ),
				__( 'Form texts', 'review-captain' )     => array( 'name_label', 'email_hint', 'submit_label' ),
				__( 'Consent', 'review-captain' )      => array( 'require_consent', 'consent_text' ),
				__( 'Notification', 'review-captain' )  => array( 'notify_admin', 'notify_recipients', 'notify_subject', 'notify_body', 'notify_questions' ),
				__( 'Moderation rules', 'review-captain' ) => array( 'hold_words', 'hold_links', 'block_email_domains', 'min_seconds' ),
				__( 'Abuse protection', 'review-captain' ) => array( 'honeypot', 'captcha_provider', 'rate_limit_per_hour', 'trusted_proxy_header' ),
				__( 'Corrections', 'review-captain' )       => array( 'corrections_enabled', 'correction_link_hours' ),
				__( 'Advanced features (after 1.0)', 'review-captain' ) => array( 'tags_enabled', 'reports_enabled', 'report_threshold', 'report_rate_limit_per_hour', 'photos_enabled', 'photo_max_kb', 'photo_max_edge', 'multi_product_enabled', 'qa_enabled', 'feedback_campaigns_enabled' ),
				__( 'Reports', 'review-captain' )         => array( 'report_reasons', 'report_notify', 'report_confirmation' ),
			),
			'display'     => array(
				__( 'Card contents', 'review-captain' )          => array( 'show_stars', 'show_date', 'date_style', 'show_author', 'show_avatar' ),
				__( 'Verification & transparency', 'review-captain' ) => array( 'verification_notice_show', 'verification_notice_title', 'verification_notice', 'verification_notice_position', 'show_unverified_label', 'label_purchase_verified', 'label_attendance_confirmed', 'label_external', 'label_testimonial', 'label_unverified' ),
				__( 'Rating and colours', 'review-captain' ) => array( 'rating_display', 'decimal_places', 'star_color', 'accent_color' ),
				__( 'Widget defaults', 'review-captain' )  => array( 'widget_limit', 'widget_layout', 'widget_columns', 'widget_heading_level', 'widget_new_tab' ),
				__( 'Structured data', 'review-captain' ) => array( 'schema_enabled', 'schema_contexts', 'schema_min_reviews' ),
				__( 'Empty state', 'review-captain' )   => array( 'empty_text' ),
				__( 'Accessibility', 'review-captain' ) => array( 'reduced_motion' ),
				__( 'Photos', 'review-captain' )            => array( 'show_photos' ),
			),
			'privacy'     => array(
				__( 'Retention', 'review-captain' )       => array( 'retention_external_days', 'retention_rejected_days', 'retention_contact_days', 'retention_approved_days', 'retention_corrections_days', 'retention_logs_days', 'retention_audit_days', 'retention_jobs_days' ),
				__( 'Privacy notice', 'review-captain' ) => array( 'privacy_notice' ),
				__( 'Erasure requests', 'review-captain' )      => array( 'erase_mode' ),
				__( 'Uninstall', 'review-captain' )     => array( 'delete_data_on_uninstall' ),
			),
			'access'      => array(
				__( 'Rights per role', 'review-captain' ) => array( 'moderate_roles', 'sources_roles', 'requests_roles', 'reports_roles', 'trust_roles', 'privacy_roles', 'settings_roles' ),
			),
			'advanced'    => array(
				__( 'Background tasks', 'review-captain' ) => array( 'use_action_scheduler', 'job_time_budget', 'lease_seconds' ),
				__( 'Debugging', 'review-captain' )         => array( 'debug_logging' ),
			),
		);
	}

	/**
	 * Enum choice labels.
	 *
	 * @param string $tab   Tab.
	 * @param string $key   Key.
	 * @param string $value Choice.
	 */
	public static function choice_label( string $tab, string $key, string $value ): string {
		$map = array(
			'general.new_review_status'       => array(
				'pending'  => __( 'Pending review (recommended)', 'review-captain' ),
				'approved' => __( 'Approve immediately', 'review-captain' ),
			),
			'sources.removed_review_behavior' => array(
				'tombstone' => __( 'Remove content immediately, keep only a placeholder (recommended)', 'review-captain' ),
				'hide'      => __( 'Only hide, keep content until the period ends', 'review-captain' ),
			),
			'submissions.trusted_proxy_header' => array(
				'none'             => __( 'None – direct connection address (recommended)', 'review-captain' ),
				'x-forwarded-for'  => 'X-Forwarded-For',
				'x-real-ip'        => 'X-Real-IP',
				'cf-connecting-ip' => 'CF-Connecting-IP (Cloudflare)',
			),
			'submissions.captcha_provider'    => array(
				'none' => _x( 'None', 'spam protection provider', 'review-captain' ),
				'pow'  => __( 'Built-in computation check (no third parties)', 'review-captain' ),
			),
			'display.rating_display'          => array(
				'both'   => __( 'Stars and number', 'review-captain' ),
				'stars'  => __( 'Stars only', 'review-captain' ),
				'number' => __( 'Number only', 'review-captain' ),
			),
			'display.widget_layout'           => array(
				'grid' => __( 'Grid', 'review-captain' ),
				'list' => __( 'List', 'review-captain' ),
			),
			'display.date_style'              => array(
				'absolute' => __( 'Absolute (12.11.2026)', 'review-captain' ),
				'relative' => __( 'Relative (3 days ago)', 'review-captain' ),
			),
			'submissions.report_reasons'      => ReportStore::reason_labels(),
			'display.schema_contexts'         => array(
				'product' => __( 'Products', 'review-captain' ),
				'post'    => __( 'Posts', 'review-captain' ),
				'event'   => __( 'Events', 'review-captain' ),
			),
			'submissions.report_notify'       => array(
				'recipients' => __( 'Notification recipients', 'review-captain' ),
				'none'       => __( 'Nobody', 'review-captain' ),
			),
			'display.verification_notice_position' => array(
				'below' => __( 'Below the list or form', 'review-captain' ),
				'above' => __( 'Above the list or form', 'review-captain' ),
			),
			'privacy.erase_mode'              => array(
				'anonymize' => __( 'Anonymize (text and rating remain)', 'review-captain' ),
				'delete'    => __( 'Delete review completely', 'review-captain' ),
			),
		);
		return $map[ $tab . '.' . $key ][ $value ] ?? $value;
	}

	/**
	 * Label of a field.
	 *
	 * @param string               $tab   Tab.
	 * @param string               $key   Key.
	 * @param array<string, mixed> $field Field definition.
	 */
	public static function label( string $tab, string $key, array $field = array() ): string {
		$texts = self::texts();
		if ( isset( $texts[ $tab ][ $key ] ) ) {
			return $texts[ $tab ][ $key ][0];
		}
		return isset( $field['label'] ) && is_string( $field['label'] ) ? $field['label'] : $key;
	}

	/**
	 * Description of a field.
	 *
	 * @param string               $tab   Tab.
	 * @param string               $key   Key.
	 * @param array<string, mixed> $field Field definition.
	 */
	public static function description( string $tab, string $key, array $field = array() ): string {
		$texts = self::texts();
		if ( isset( $texts[ $tab ][ $key ] ) ) {
			return $texts[ $tab ][ $key ][1];
		}
		return isset( $field['description'] ) && is_string( $field['description'] ) ? $field['description'] : '';
	}
}
