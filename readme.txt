=== Review Captain ===
Contributors: captainsuite
Tags: reviews, testimonials, social proof, ratings, woocommerce
Requires at least: 6.5
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Collect, moderate and display authentic reviews with clear source attribution – without scraping and without review gating.

== Description ==

Review Captain helps you show honest social proof on your WordPress site:

* Collect your own reviews with an accessible form (spam protection, moderation queue).
* Add reviews from other platforms manually – always with original rating scale, date, source note and link to the original.
* Show reviews with a block or shortcode in grid or list layout, rendered on the server (fast, no tracking, works without JavaScript).
* Text testimonials, clearly labelled and separate from platform reviews, with documented permission.
* Neutral “Review us on …” buttons – the same for every visitor, no star pre-selection.
* CSV export (protected against spreadsheet formula injection) and CSV import with a check run, duplicate detection and undo.
* WordPress privacy export/erasure, retention rules and roles.
* Structured data only for eligible reviews collected on your site; works together with Yoast SEO.

Review Captain never scrapes platforms, never filters who is asked for a review by expected rating, and never modifies review texts.

= Review Captain Pro =

The separately licensed Pro add-on adds official API connections (where the platform grants access), automatic review invitations after real customer experiences, additional layouts and Captain Suite integrations. The free plugin works fully without Pro, without a license and without external services.

== External services ==

The free plugin does not contact external services. Links to review platforms are only followed when a visitor clicks them. Official platform widgets or videos you embed are loaded only after a click (or when your consent tool allows it).

== Installation ==

1. Install and activate the plugin.
2. Follow the setup assistant (Review Captain → Setup).
3. Add the “Review Captain Review Form” or “Review Captain Widget” block to a page.

For reliable background tasks on busy sites, run `wp review-captain queue run --time=50` every minute via a server cron job.

== Frequently Asked Questions ==

= Can I import reviews from Google or Trustpilot automatically? =

Not in the free plugin. You can add reviews you are allowed to show manually, or import a CSV file. Automatic synchronisation via official APIs is part of Review Captain Pro and depends on the access the platform grants to your account.

= Does Review Captain add star rich snippets for platform reviews? =

No. Structured data is only output for eligible reviews collected on your own site. Reviews from other platforms never receive review markup.

== Changelog ==

= 0.1.0 =
* First development version of the free core.
