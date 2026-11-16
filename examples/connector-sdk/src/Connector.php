<?php
/**
 * Connector skeleton: experience_source + recipient_resolver + context_provider.
 *
 * @package MyVendor\MyConnector
 */

declare(strict_types=1);

namespace MyVendor\MyConnector;

use ReviewCaptain\Connectors\ConnectorException;
use ReviewCaptain\Connectors\Contract\ConnectorInterface;
use ReviewCaptain\Connectors\Contract\ContextProviderInterface;
use ReviewCaptain\Connectors\Contract\ExperienceSourceInterface;
use ReviewCaptain\Connectors\Contract\RecipientResolverInterface;

/**
 * Remove the interfaces (and capabilities) you do not need.
 */
final class Connector implements ConnectorInterface, ExperienceSourceInterface, RecipientResolverInterface, ContextProviderInterface {

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $config Configuration (see config.example.json).
	 * @param callable|null        $source Data source callable( string $cursor, int $limit ): list<array>. Default: my_source_get_experiences().
	 */
	public function __construct( private array $config = array(), private $source = null ) {}

	public function key(): string {
		return 'my-connector';
	}

	public function label(): string {
		return __( 'Mein Quellsystem', 'my-review-connector' );
	}

	public function version(): string {
		return '0.1.0';
	}

	public function contract_version(): string {
		return '1.0';
	}

	public function developer(): string {
		return 'TODO Ihr Name / Ihre Firma';
	}

	public function capabilities(): array {
		return array( 'experience_source', 'recipient_resolver', 'context_provider' );
	}

	/**
	 * Map rows of your system to contract events. Cursor = last processed ID (opaque for Review Captain).
	 *
	 * @param string $cursor Cursor.
	 * @param int    $limit  Limit.
	 * @return array{events: list<array<string,mixed>>, next_cursor: ?string}
	 * @throws ConnectorException When the source is temporarily unavailable.
	 */
	public function poll_experiences( string $cursor, int $limit ): array {
		$source = $this->source ?? ( function_exists( 'my_source_get_experiences' ) ? 'my_source_get_experiences' : null );
		if ( null === $source ) {
			throw new ConnectorException( 'rc.source.unavailable', 'Quellsystem ist nicht aktiv' );
		}
		$rows   = (array) call_user_func( $source, $cursor, $limit );
		$events = array();
		$last   = $cursor;
		foreach ( array_slice( $rows, 0, $limit ) as $row ) {
			$events[] = array(
				'contract'              => '1.0',
				'event_id'              => 'my-connector-' . $row['id'] . '-' . $row['status'],
				'event_type'            => 'cancelled' === $row['status'] ? 'experience_cancelled' : 'experience_completed',
				'experience_id'         => 'my-connector:' . $row['id'],
				'occurred_at'           => (string) $row['occurred_at'],
				'object_type'           => (string) ( $this->config['object_type'] ?? 'course' ),
				'object_id'             => (string) $row['object_id'],
				'recipient_ref'         => 'my-connector:customer:' . $row['customer_id'], // Niemals eine E-Mail!
				'locale'                => $row['locale'] ?? null,
				'communication_allowed' => ! empty( $row['opt_in'] ),
				'consent'               => array(
					'status' => ! empty( $row['opt_in'] ) ? 'granted' : 'unknown',
					'source' => (string) ( $this->config['consent_source'] ?? 'checkout' ),
					'at'     => $row['opt_in_at'] ?? null,
				),
			);
			$last     = (string) $row['id'];
		}
		return array(
			'events'      => $events,
			'next_cursor' => count( $events ) < $limit ? null : $last,
		);
	}

	/**
	 * Resolve contact data right before sending. Never store the result.
	 *
	 * @param string $recipient_ref Reference.
	 * @param string $purpose       Purpose.
	 * @return array{email: string, locale: ?string, permission: string}|null
	 */
	public function resolve( string $recipient_ref, string $purpose ): ?array {
		if ( ! in_array( $purpose, array( 'review_request', 'review_request_reminder' ), true ) ) {
			return null;
		}
		if ( ! preg_match( '/^my-connector:customer:([A-Za-z0-9_-]+)$/', $recipient_ref, $m ) || ! function_exists( 'my_source_get_customer' ) ) {
			return null;
		}
		$customer = my_source_get_customer( $m[1] );
		if ( ! is_array( $customer ) || ! is_email( (string) ( $customer['email'] ?? '' ) ) ) {
			return null;
		}
		return array(
			'email'      => (string) $customer['email'],
			'locale'     => $customer['locale'] ?? null,
			'permission' => ! empty( $customer['opt_in'] ) && empty( $customer['unsubscribed'] ) ? 'allowed' : 'denied',
		);
	}

	/**
	 * Public label/URL of a context object.
	 *
	 * @param string $context_type Type.
	 * @param string $context_id   ID.
	 * @return array{label: string, url: ?string}|null
	 */
	public function describe_context( string $context_type, string $context_id ): ?array {
		if ( ( $this->config['object_type'] ?? 'course' ) !== $context_type || ! function_exists( 'my_source_get_object' ) ) {
			return null;
		}
		$object = my_source_get_object( $context_id );
		return is_array( $object ) && ! empty( $object['public'] ) ? array(
			'label' => (string) $object['title'],
			'url'   => $object['url'] ?? null,
		) : null;
	}
}
