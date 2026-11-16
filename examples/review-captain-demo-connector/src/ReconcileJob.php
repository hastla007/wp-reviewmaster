<?php
/**
 * Demo job handler for review_captain_process_job_v1.
 *
 * @package CaptainDemo\BookingConnector
 */

declare(strict_types=1);

namespace CaptainDemo\BookingConnector;

use ReviewCaptain\Connectors\ConnectorException;

/**
 * Handles 'demo-booking.reconcile'. Idempotent: only reads fixture data.
 */
final class ReconcileJob {

	/**
	 * Constructor.
	 *
	 * @param string $fixture Fixture path.
	 */
	public function __construct( private string $fixture ) {}

	/**
	 * Handle a job.
	 *
	 * @param string               $type    Job type.
	 * @param array<string, mixed> $payload Payload.
	 * @return array{status: string}
	 * @throws ConnectorException On invalid jobs.
	 */
	public function handle( string $type, array $payload ): array {
		if ( 'demo-booking.reconcile' !== $type ) {
			throw new ConnectorException( 'rc.contract.incompatible', 'Unbekannter Jobtyp' );
		}
		if ( 1 !== ( $payload['v'] ?? null ) || ! isset( $payload['course_id'] ) ) {
			throw new ConnectorException( 'rc.data.invalid_payload', 'Payload-Version oder course_id fehlt' );
		}
		if ( ! is_readable( $this->fixture ) ) {
			throw new ConnectorException( 'rc.source.unavailable', 'Demo-Daten nicht lesbar' );
		}
		return array( 'status' => 'succeeded' );
	}
}
