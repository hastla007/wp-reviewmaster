<?php
/**
 * Configurable test connectors in a third-party namespace (reserved-key rules apply).
 *
 * @package ReviewCaptain\Tests
 */

declare(strict_types=1);

// phpcs:ignoreFile

namespace Acme\RcTest;

use ReviewCaptain\Connectors\Contract\ConnectorInterface;
use ReviewCaptain\Connectors\Contract\ExperienceSourceInterface;
use ReviewCaptain\Connectors\Contract\ReviewSourceInterface;

class ConfigurableConnector implements ConnectorInterface {
	public int $calls = 0;
	/** @param array<string, mixed> $meta */
	public function __construct( public array $meta = array() ) {
		$this->meta += array(
			'key'              => 'acme-booking',
			'label'            => 'Acme Booking',
			'version'          => '1.0.0',
			'contract_version' => '1.0',
			'capabilities'     => array( 'experience_source' ),
			'developer'        => 'Acme GmbH',
			'throw'            => null,
		);
	}
	private function v( string $k ) { if ( $this->meta['throw'] === $k ) { throw new \RuntimeException( 'boom' ); } return $this->meta[ $k ]; }
	public function key(): string { return $this->v( 'key' ); }
	public function label(): string { return $this->v( 'label' ); }
	public function version(): string { return $this->v( 'version' ); }
	public function contract_version(): string { return $this->v( 'contract_version' ); }
	public function capabilities(): array { return $this->v( 'capabilities' ); }
	public function developer(): string { return $this->v( 'developer' ); }
}

class ExperienceConnector extends ConfigurableConnector implements ExperienceSourceInterface {
	public function poll_experiences( string $cursor, int $limit ): array { ++$this->calls; return array( 'events' => array(), 'next_cursor' => null ); }
}

class ExperienceAndReviewConnector extends ExperienceConnector implements ReviewSourceInterface {
	public function fetch_reviews( string $cursor, int $limit ): array { ++$this->calls; return array( 'reviews' => array(), 'next_cursor' => null ); }
}
