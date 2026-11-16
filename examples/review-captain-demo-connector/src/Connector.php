<?php
/**
 * Demo connector: experience source + context provider backed by a local JSON fixture.
 *
 * @package CaptainDemo\BookingConnector
 */

declare(strict_types=1);

namespace CaptainDemo\BookingConnector;

use ReviewCaptain\Connectors\ConnectorException;
use ReviewCaptain\Connectors\Contract\ConnectorInterface;
use ReviewCaptain\Connectors\Contract\ContextProviderInterface;
use ReviewCaptain\Connectors\Contract\ExperienceSourceInterface;

/**
 * Reports completed course attendances ("Kursbesuche") from fixtures/enrollments.json.
 * No access to Review Captain tables or any other plugin's tables.
 */
final class Connector implements ConnectorInterface, ExperienceSourceInterface, ContextProviderInterface {

	/**
	 * Constructor.
	 *
	 * @param string $fixture Path to enrollments.json.
	 */
	public function __construct( private string $fixture ) {}

	public function key(): string {
		return 'demo-booking';
	}

	public function label(): string {
		return __( 'Demo-Kursbuchung', 'review-captain-demo-connector' );
	}

	public function version(): string {
		return '1.0.0';
	}

	public function contract_version(): string {
		return '1.0';
	}

	public function developer(): string {
		return 'Captain Suite (Beispiel)';
	}

	public function capabilities(): array {
		return array( 'experience_source', 'context_provider' );
	}

	/**
	 * Cursor = last processed enrollment ID.
	 *
	 * @param string $cursor Cursor.
	 * @param int    $limit  Limit.
	 * @return array{events: list<array<string,mixed>>, next_cursor: ?string}
	 */
	public function poll_experiences( string $cursor, int $limit ): array {
		$after  = ctype_digit( $cursor ) ? (int) $cursor : 0;
		$data   = $this->data();
		$events = array();
		$last   = $after;
		foreach ( $data['enrollments'] as $row ) {
			if ( (int) $row['id'] <= $after || 'attended' !== $row['status'] ) {
				continue;
			}
			if ( count( $events ) >= $limit ) {
				break;
			}
			$events[] = array(
				'contract'              => '1.0',
				'event_id'              => 'demo-enrollment-' . $row['id'] . '-attended',
				'event_type'            => 'experience_completed',
				'experience_id'         => 'demo-booking:enrollment:' . $row['id'],
				'occurred_at'           => gmdate( 'Y-m-d\TH:i:s\Z', (int) $row['attended_ts'] ),
				'object_type'           => 'course',
				'object_id'             => (string) $row['course_id'],
				'recipient_ref'         => 'demo-booking:participant:' . $row['participant_ref'],
				'locale'                => $row['locale'] ?? null,
				'communication_allowed' => (bool) $row['review_mail_opt_in'],
				'consent'               => array(
					'status' => $row['review_mail_opt_in'] ? 'granted' : 'unknown',
					'source' => 'demo_booking_checkout',
					'at'     => null,
				),
				'context'               => array( 'campaign_hint' => 'course_feedback' ),
			);
			$last     = max( $last, (int) $row['id'] );
		}
		return array(
			'events'      => $events,
			'next_cursor' => count( $events ) < $limit ? null : (string) $last,
		);
	}

	/**
	 * Public course label.
	 *
	 * @param string $context_type Type.
	 * @param string $context_id   ID.
	 * @return array{label: string, url: ?string}|null
	 */
	public function describe_context( string $context_type, string $context_id ): ?array {
		if ( 'course' !== $context_type ) {
			return null;
		}
		foreach ( $this->data()['courses'] as $course ) {
			if ( (string) $course['id'] === $context_id && ! empty( $course['public'] ) ) {
				return array(
					'label' => (string) $course['title'],
					'url'   => isset( $course['url'] ) ? (string) $course['url'] : null,
				);
			}
		}
		return null;
	}

	/**
	 * Fixture data.
	 *
	 * @return array{enrollments: list<array<string,mixed>>, courses: list<array<string,mixed>>}
	 * @throws ConnectorException When the fixture is missing.
	 */
	private function data(): array {
		$raw  = is_readable( $this->fixture ) ? file_get_contents( $this->fixture ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$data = false === $raw ? null : json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			throw new ConnectorException( 'rc.source.unavailable', 'Demo-Daten nicht lesbar' );
		}
		return array(
			'enrollments' => (array) ( $data['enrollments'] ?? array() ),
			'courses'     => (array) ( $data['courses'] ?? array() ),
		);
	}
}
