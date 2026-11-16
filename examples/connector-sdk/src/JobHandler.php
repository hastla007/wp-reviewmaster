<?php
/**
 * Job handler skeleton for review_captain_process_job_v1.
 *
 * @package MyVendor\MyConnector
 */

declare(strict_types=1);

namespace MyVendor\MyConnector;

use ReviewCaptain\Connectors\ConnectorException;

/**
 * Job types: my-connector.{name}. Handlers must be idempotent.
 */
final class JobHandler {

	/**
	 * Handle a job.
	 *
	 * @param string               $type    Type.
	 * @param array<string, mixed> $payload Payload (field v = payload version; no secrets/e-mails/texts).
	 * @return array{status: string}
	 * @throws ConnectorException On errors.
	 */
	public function handle( string $type, array $payload ): array {
		if ( 'my-connector.reconcile' !== $type ) {
			throw new ConnectorException( 'rc.contract.incompatible', 'Unbekannter Jobtyp' );
		}
		if ( 1 !== ( $payload['v'] ?? null ) ) {
			throw new ConnectorException( 'rc.data.invalid_payload', 'Unbekannte Payload-Version' );
		}
		// TODO: Arbeit erledigen (idempotent!).
		return array( 'status' => 'succeeded' );
	}
}
