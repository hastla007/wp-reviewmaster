<?php
/**
 * Daily maintenance entry point (RC-036).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Maintenance;

use ReviewCaptain\Jobs\JobQueue;
use ReviewCaptain\Plugin;

/**
 * Runs retention and a non-destructive reconciliation. Used by the daily cron hook and WP-CLI.
 */
class Maintenance {

	/**
	 * Constructor.
	 *
	 * @param Plugin   $plugin Plugin.
	 * @param JobQueue $queue  Queue.
	 */
	public function __construct( private Plugin $plugin, private JobQueue $queue ) {}

	/**
	 * Run daily maintenance.
	 *
	 * @param float $time_budget Seconds.
	 * @return array{retention: array<string, mixed>, reconcile: array<string, mixed>}
	 */
	public function run( float $time_budget = 20.0 ): array {
		$retention = ( new Retention( $this->plugin, $this->queue ) )->run( $time_budget );
		$reconcile = ( new Reconciler( $this->plugin, $this->queue ) )->run( true, 'manual' );
		update_option( 'review_captain_last_maintenance', time(), false );
		return array(
			'retention' => $retention,
			'reconcile' => $reconcile,
		);
	}
}
