<?php
/**
 * Deactivation: stop scheduled events, keep all data.
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Install;

use ReviewCaptain\Jobs\Runner;
use ReviewCaptain\Maintenance\Retention;

defined( 'ABSPATH' ) || exit;

final class Deactivator {

	public static function deactivate(): void {
		Runner::unschedule();
		Retention::unschedule();
	}
}
