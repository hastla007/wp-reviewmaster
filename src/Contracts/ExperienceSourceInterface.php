<?php
/**
 * Capability experience_source (CON-003). Events are submitted via wp_review_captain_submit_experience_event_v1().
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Contracts;

defined( 'ABSPATH' ) || exit;

interface ExperienceSourceInterface {

	/**
	 * @return string[] Object types this connector reports (product, event, course, booking …).
	 */
	public function object_types(): array;
}
