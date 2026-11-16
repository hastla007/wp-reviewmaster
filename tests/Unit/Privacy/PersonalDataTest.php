<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Privacy;

use ReviewCaptain\Domain\Origin;
use ReviewCaptain\Domain\Review;
use ReviewCaptain\Plugin;
use ReviewCaptain\Privacy\PersonalData;
use ReviewCaptain\Privacy\PrivacyModule;
use ReviewCaptain\Settings\Settings;
use ReviewCaptain\Tests\TestCase;

final class PersonalDataTest extends TestCase {

	private function review( array $props = array() ): Review {
		$r               = new Review();
		$r->origin       = Origin::LOCAL_SUBMISSION;
		$r->body         = 'Mein persönlicher Text';
		$r->title        = 'Titel';
		$r->rating_value = 4;
		$r->author_name  = 'Max Mustermann';
		$r->author_email = 'max@example.test';
		$r->meta         = array( 'ip_hash' => 'abc', 'user_agent' => 'UA', 'source_form' => 'f1' );
		foreach ( $props as $k => $v ) {
			$r->$k = $v;
		}
		return Plugin::instance()->reviews()->insert( $r );
	}

	private function service(): PersonalData {
		return new PersonalData( Plugin::instance()->reviews(), Plugin::instance()->settings() );
	}

	private function callbacks(): array {
		( new PrivacyModule() )->register( Plugin::instance() );
		return array(
			apply_filters( 'wp_privacy_personal_data_exporters', array() ),
			apply_filters( 'wp_privacy_personal_data_erasers', array() ),
		);
	}

	public function test_registers_exporter_and_eraser_group(): void {
		[ $exporters, $erasers ] = $this->callbacks();
		$this->assertSame( 'Review Captain', $exporters['review-captain']['exporter_friendly_name'] );
		$this->assertSame( 'Review Captain', $erasers['review-captain']['eraser_friendly_name'] );
		$this->assertIsCallable( $exporters['review-captain']['callback'] );
		$this->assertIsCallable( $erasers['review-captain']['callback'] );
	}

	public function test_export_by_email_and_user_id(): void {
		$this->review();
		$this->review( array( 'author_email' => null, 'author_user_id' => 5, 'body' => 'Als Benutzer 5' ) );
		$this->review( array( 'author_email' => 'other@example.test', 'body' => 'Fremd' ) );

		[ $exporters ] = $this->callbacks();
		$out           = ( $exporters['review-captain']['callback'] )( 'max@example.test', 1 );
		$this->assertTrue( $out['done'] );
		$this->assertCount( 1, $out['data'] );
		$this->assertSame( 'review-captain', $out['data'][0]['group_id'] );
		$this->assertStringStartsWith( 'review-captain-review-', $out['data'][0]['item_id'] );
		$values = array_column( $out['data'][0]['data'], 'value', 'name' );
		$this->assertSame( 'Mein persönlicher Text', $values['Text'] );
		$this->assertSame( 'max@example.test', $values['E-Mail-Adresse'] );

		$user = $this->service()->export( 'user5@example.test' );
		$this->assertCount( 1, $user['data'] );
		$this->assertStringNotContainsString( 'Fremd', (string) wp_json_encode( $user ) );
	}

	public function test_export_is_paginated(): void {
		for ( $i = 0; $i < PersonalData::PAGE_SIZE + 5; $i++ ) {
			$this->review( array( 'body' => 'Text ' . $i ) );
		}
		$p1 = $this->service()->export( 'max@example.test', 1 );
		$this->assertCount( PersonalData::PAGE_SIZE, $p1['data'] );
		$this->assertFalse( $p1['done'] );
		$p2 = $this->service()->export( 'max@example.test', 2 );
		$this->assertCount( 5, $p2['data'] );
		$this->assertTrue( $p2['done'] );
	}

	public function test_erase_anonymize_mode(): void {
		$no_consent = $this->review();
		$consent    = $this->review( array( 'consent_publish' => true, 'consent_at' => '2026-11-01 10:00:00', 'body' => 'Öffentlich freigegeben', 'author_user_id' => 9 ) );
		$other      = $this->review( array( 'author_email' => 'other@example.test' ) );

		[ , $erasers ] = $this->callbacks();
		$out           = ( $erasers['review-captain']['callback'] )( 'max@example.test', 1 );
		$this->assertTrue( $out['items_removed'] );
		$this->assertFalse( $out['items_retained'] );
		$this->assertTrue( $out['done'] );

		$a = Plugin::instance()->reviews()->find( (int) $no_consent->id );
		$this->assertSame( 'Anonym', $a->author_name );
		$this->assertNull( $a->author_email );
		$this->assertSame( '[deleted]', $a->body, 'text replaced without consent' );
		$this->assertSame( '[deleted]', $a->title );
		$this->assertArrayNotHasKey( 'ip_hash', $a->meta );
		$this->assertArrayNotHasKey( 'user_agent', $a->meta );
		$this->assertSame( 'f1', $a->meta['source_form'] );

		$b = Plugin::instance()->reviews()->find( (int) $consent->id );
		$this->assertSame( 'Öffentlich freigegeben', $b->body, 'text kept with publication consent' );
		$this->assertNull( $b->author_user_id );
		$this->assertSame( 'Anonym', $b->author_name );

		$this->assertSame( 'max@example.test', Plugin::instance()->reviews()->find( (int) $other->id )->author_email === 'other@example.test' ? 'max@example.test' : 'x' );
		$this->assertSame( 3, $this->rows( 'reviews' ) );
		$this->assertSame( array(), $this->service()->export( 'max@example.test' )['data'], 'nothing left to find' );
	}

	public function test_erase_delete_mode(): void {
		update_option( Settings::OPTION, array( 'privacy' => array( 'erase_mode' => 'delete' ) ) );
		$this->review();
		$this->review( array( 'author_email' => null, 'author_user_id' => 5 ) );
		$keep = $this->review( array( 'author_email' => 'other@example.test' ) );

		$out = $this->service()->erase( 'max@example.test' );
		$this->assertTrue( $out['items_removed'] );
		$this->assertSame( 2, $this->rows( 'reviews' ) );
		$this->service()->erase( 'user5@example.test' );
		$this->assertSame( 1, $this->rows( 'reviews' ) );
		$this->assertNotNull( Plugin::instance()->reviews()->find( (int) $keep->id ) );
	}

	public function test_erase_without_matches(): void {
		$out = $this->service()->erase( 'nobody@example.test' );
		$this->assertFalse( $out['items_removed'] );
		$this->assertFalse( $out['items_retained'] );
		$this->assertTrue( $out['done'] );
	}

	public function test_policy_text_suggestion(): void {
		( new PrivacyModule() )->register( Plugin::instance() );
		do_action( 'admin_init' );
		$text = $GLOBALS['rc_test_privacy_policy']['Review Captain'] ?? '';
		$this->assertStringContainsString( 'Bewertung', $text );
		$this->assertStringContainsString( 'Anonym', $text );
	}
}
