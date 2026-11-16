<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Connectors;

use ReviewCaptain\Connectors\ExperienceEvent;
use ReviewCaptain\Support\ErrorCode;
use ReviewCaptain\Tests\TestCase;

final class ExperienceEventTest extends TestCase {

	/** @return array<string, mixed> */
	private function event( array $override = array() ): array {
		return array_merge(
			array(
				'contract'              => '1.0',
				'event_id'              => 'acme-enrollment-1-attended',
				'event_type'            => 'experience_completed',
				'experience_id'         => 'acme-enrollment-1',
				'occurred_at'           => '2026-11-15T10:00:00Z',
				'object_type'           => 'course',
				'object_id'             => '17',
				'recipient_ref'         => 'acme-customer:42',
				'locale'                => 'de_DE',
				'communication_allowed' => true,
				'consent'               => array( 'status' => 'granted', 'source' => 'checkout', 'at' => '2026-11-01T09:00:00+01:00' ),
				'idempotency_key'       => 'acme:1:attended',
				'context'               => array( 'campaign_hint' => 'course' ),
			),
			$override
		);
	}

	public function test_valid_connector_event(): void {
		$this->assertSame( array(), ExperienceEvent::validate( $this->event() ) );
		$this->assertNull( ExperienceEvent::error_code( array() ) );
	}

	public function test_ct_e_02_missing_required_fields(): void {
		foreach ( array( 'event_id', 'experience_id', 'occurred_at', 'communication_allowed', 'consent', 'event_type', 'object_type', 'object_id', 'contract' ) as $field ) {
			$e = $this->event();
			unset( $e[ $field ] );
			$errors = ExperienceEvent::validate( $e );
			$this->assertContains( $field . ':missing', $errors, $field );
			$this->assertSame( ErrorCode::DATA_INVALID_PAYLOAD, ExperienceEvent::error_code( $errors ) );
		}
	}

	public function test_recipient_ref_required_only_for_completed(): void {
		$e = $this->event();
		unset( $e['recipient_ref'] );
		$this->assertContains( 'recipient_ref:missing', ExperienceEvent::validate( $e ) );
		$e['event_type'] = 'experience_cancelled';
		$this->assertSame( array(), ExperienceEvent::validate( $e ) );
	}

	public function test_ct_e_03_unknown_event_type(): void {
		$this->assertContains( 'event_type:invalid', ExperienceEvent::validate( $this->event( array( 'event_type' => 'experience_started' ) ) ) );
	}

	public function test_ct_e_04_occurred_at_needs_iso8601_with_zone(): void {
		foreach ( array( '2026-11-15 10:00:00', '2026-11-15T10:00:00', '15.11.2026', '2026-02-30T10:00:00Z', 1700000000 ) as $value ) {
			$this->assertContains( 'occurred_at:invalid', ExperienceEvent::validate( $this->event( array( 'occurred_at' => $value ) ) ), (string) $value );
		}
		$this->assertSame( array(), ExperienceEvent::validate( $this->event( array( 'occurred_at' => '2026-11-15T10:00:00+01:00' ) ) ) );
	}

	public function test_ct_e_05_recipient_ref_with_at_is_rejected(): void {
		$this->assertContains( 'recipient_ref:pii', ExperienceEvent::validate( $this->event( array( 'recipient_ref' => 'max@example.com' ) ) ) );
	}

	public function test_forbidden_personal_fields(): void {
		foreach ( array( 'email', 'body', 'author_name', 'phone' ) as $field ) {
			$this->assertContains( $field . ':forbidden', ExperienceEvent::validate( $this->event( array( $field => 'x' ) ) ), $field );
		}
		$this->assertContains( 'context.email:forbidden', ExperienceEvent::validate( $this->event( array( 'context' => array( 'email' => 'x' ) ) ) ) );
		$this->assertContains( 'context.note:pii', ExperienceEvent::validate( $this->event( array( 'context' => array( 'note' => 'mail an max@example.com' ) ) ) ) );
	}

	public function test_unknown_harmless_fields_are_ignored(): void {
		$this->assertSame( array(), ExperienceEvent::validate( $this->event( array( 'future_field' => 'x' ) ) ) );
	}

	public function test_types_and_lengths(): void {
		$this->assertContains( 'communication_allowed:invalid', ExperienceEvent::validate( $this->event( array( 'communication_allowed' => 'yes' ) ) ) );
		$this->assertContains( 'consent.status:invalid', ExperienceEvent::validate( $this->event( array( 'consent' => array( 'status' => 'maybe', 'source' => 'x' ) ) ) ) );
		$this->assertContains( 'event_id:too_long', ExperienceEvent::validate( $this->event( array( 'event_id' => str_repeat( 'a', 192 ) ) ) ) );
		$this->assertContains( 'idempotency_key:too_long', ExperienceEvent::validate( $this->event( array( 'idempotency_key' => str_repeat( 'a', 192 ) ) ) ) );
		$this->assertContains( 'object_type:invalid', ExperienceEvent::validate( $this->event( array( 'object_type' => 'Course!' ) ) ) );
		$this->assertContains( 'context:invalid', ExperienceEvent::validate( $this->event( array( 'context' => array( 'a' => array( 1 ) ) ) ) ) );
		$this->assertContains( 'context:invalid', ExperienceEvent::validate( $this->event( array( 'context' => array_fill_keys( range( 'a', 'k' ), 'v' ) ) ) ) );
	}

	public function test_major_version_is_incompatible(): void {
		$errors = ExperienceEvent::validate( $this->event( array( 'contract' => '2.0' ) ) );
		$this->assertContains( 'contract:incompatible', $errors );
		$this->assertSame( ErrorCode::CONTRACT_INCOMPATIBLE, ExperienceEvent::error_code( $errors ) );
	}

	public function test_defaults(): void {
		$e = $this->event();
		unset( $e['idempotency_key'] );
		$d = ExperienceEvent::with_defaults( $e, 'acme-booking' );
		$this->assertSame( sha1( 'acme-booking|acme-enrollment-1-attended' ), $d['idempotency_key'] );
		$this->assertSame( 'acme-booking', $d['source'] );
	}

	/** @return array<string, mixed> */
	private function schema_event( array $override = array() ): array {
		return array_merge(
			array(
				'schema'          => 'review_captain.experience_event.v1',
				'contract'        => '1.0',
				'source'          => 'pass-captain',
				'event_id'        => '1b2c3d4e-5f60-4718-8a9b-0c1d2e3f4a5b',
				'experience_id'   => 'pass-captain:1:attendance:T-7781:OCC-55',
				'event_type'      => 'experience_completed',
				'occurred_at'     => '2026-11-14T18:05:00Z',
				'object_ref'      => array( 'type' => 'event_occurrence', 'id' => 'OCC-55', 'context_type' => 'event' ),
				'recipient_ref'   => 'pass-captain:attendee:9f8e7d6c',
				'locale'          => 'de-AT',
				'send_permission' => array( 'status' => 'granted', 'channel' => 'email', 'basis' => 'consent', 'recorded_at' => '2026-10-01T12:00:00Z', 'origin' => 'checkout_optin', 'evidence_ref' => 'consent-4471' ),
				'idempotency_key' => 'pass-captain:1:checkin:T-7781:OCC-55',
				'context'         => array( 'trigger_detail' => 'checkin' ),
				'producer'        => array( 'plugin' => 'pass-captain', 'version' => '2.4.0' ),
			),
			$override
		);
	}

	public function test_schema_v1_examples_are_valid(): void {
		$this->assertSame( array(), ExperienceEvent::validate( $this->schema_event() ) );
		$refund = $this->schema_event(
			array(
				'source'          => 'woocommerce',
				'event_id'        => '3d4e5f60-7182-4a3b-8cbd-2e3f4a5b6c7d',
				'experience_id'   => 'woocommerce:1:order:10452',
				'event_type'      => 'experience_refunded',
				'object_ref'      => array( 'type' => 'order', 'id' => '10452', 'context_type' => null ),
				'recipient_ref'   => null,
				'locale'          => null,
				'send_permission' => array( 'status' => 'unknown', 'channel' => 'email', 'basis' => null, 'recorded_at' => null, 'origin' => null, 'evidence_ref' => null ),
				'idempotency_key' => 'woocommerce:1:refund:10452:full',
				'context'         => array( 'trigger_detail' => 'refund_full' ),
			)
		);
		unset( $refund['producer'] );
		$this->assertSame( array(), ExperienceEvent::validate( $refund ) );
	}

	public function test_schema_v1_rules(): void {
		$this->assertContains( 'recipient_ref:pii', ExperienceEvent::validate( $this->schema_event( array( 'recipient_ref' => 'a@b.de' ) ) ) );
		$this->assertContains( 'recipient_ref:missing', ExperienceEvent::validate( $this->schema_event( array( 'recipient_ref' => null ) ) ) );
		$this->assertContains( 'experience_id:not_owner', ExperienceEvent::validate( $this->schema_event( array( 'experience_id' => 'other:1' ) ) ) );
		$this->assertContains( 'event_id:invalid', ExperienceEvent::validate( $this->schema_event( array( 'event_id' => 'not-a-uuid' ) ) ) );
		$this->assertContains( 'occurred_at:invalid', ExperienceEvent::validate( $this->schema_event( array( 'occurred_at' => '2026-11-14T18:05:00+01:00' ) ) ) );
		$this->assertContains( 'occurred_at:future', ExperienceEvent::validate( $this->schema_event( array( 'occurred_at' => gmdate( 'Y-m-d\TH:i:s\Z', time() + 7200 ) ) ) ) );
		$this->assertContains( 'contract:incompatible', ExperienceEvent::validate( $this->schema_event( array( 'contract' => '2.0' ) ) ) );
		$this->assertContains( 'context.customer:forbidden', ExperienceEvent::validate( $this->schema_event( array( 'context' => array( 'customer' => 'x' ) ) ) ) );
		$this->assertContains( 'send_permission:missing', ExperienceEvent::validate( array_diff_key( $this->schema_event(), array( 'send_permission' => 1 ) ) ) );
		$this->assertContains( 'idempotency_key:missing', ExperienceEvent::validate( array_diff_key( $this->schema_event(), array( 'idempotency_key' => 1 ) ) ) );
		$this->assertContains( 'locale:missing', ExperienceEvent::validate( array_diff_key( $this->schema_event(), array( 'locale' => 1 ) ) ) );
		$this->assertContains( 'object_ref:invalid', ExperienceEvent::validate( $this->schema_event( array( 'object_ref' => array( 'type' => 'x', 'id' => '1', 'context_type' => 'galaxy' ) ) ) ) );
		$this->assertContains( 'payload:too_long', ExperienceEvent::validate( $this->schema_event( array( 'producer' => array( 'plugin' => str_repeat( 'x', 17000 ), 'version' => '1' ) ) ) ) );
	}
}
