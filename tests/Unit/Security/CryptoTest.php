<?php
declare(strict_types=1);

namespace ReviewCaptain\Tests\Unit\Security;

use ReviewCaptain\Security\Crypto;
use ReviewCaptain\Tests\TestCase;

final class CryptoTest extends TestCase {

	private static function key(): string {
		return base64_encode( random_bytes( 32 ) );
	}

	public function test_roundtrip_with_explicit_key_and_fresh_nonce(): void {
		$c = new Crypto( array( 'key' => self::key() ) );
		$this->assertSame( Crypto::STATUS_EXPLICIT, $c->status() );
		$this->assertFalse( $c->is_fallback_key() );
		$a = $c->encrypt( 'refresh-token-123' );
		$b = $c->encrypt( 'refresh-token-123' );
		$this->assertNotSame( $a, $b );
		$this->assertMatchesRegularExpression( '/^rc1:[0-9a-f]{16}:[A-Za-z0-9+\/]+=*$/', $a );
		$this->assertStringStartsWith( 'rc1:' . $c->key_id() . ':', $a );
		$this->assertStringNotContainsString( 'refresh-token', $a );
		$this->assertSame( 'refresh-token-123', $c->decrypt( $a ) );
		$this->assertSame( $c->key_id(), Crypto::key_id_of( $a ) );
	}

	public function test_fallback_key_from_salt(): void {
		$c = new Crypto( array() );
		$this->assertTrue( $c->is_fallback_key() );
		$this->assertSame( 'x', $c->decrypt( $c->encrypt( 'x' ) ) );
		$this->assertSame( ( new Crypto( array() ) )->key_id(), $c->key_id() );
	}

	public function test_invalid_constant_disables_crypto_without_fallback(): void {
		$c = new Crypto( array( 'key' => 'not-base64-32-bytes' ) );
		$this->assertSame( Crypto::STATUS_INVALID, $c->status() );
		$this->assertFalse( $c->is_available() );
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'rc.crypto.key_missing' );
		$c->encrypt( 'x' );
	}

	public function test_tampering_and_wrong_key_are_detected(): void {
		$c      = new Crypto( array( 'key' => self::key() ) );
		$cipher = $c->encrypt( 'secret' );
		[ $p, $id, $b64 ] = explode( ':', $cipher );
		$raw     = base64_decode( $b64 );
		$raw[30] = chr( ord( $raw[30] ) ^ 1 );
		$tampered = $p . ':' . $id . ':' . base64_encode( $raw );

		foreach ( array( $tampered, 'rc1:garbage', 'plain', $p . ':0000000000000000:' . $b64 ) as $bad ) {
			try {
				$c->decrypt( $bad );
				$this->fail( 'Expected failure' );
			} catch ( \RuntimeException $e ) {
				$this->assertSame( 'rc.crypto.decrypt_failed', $e->getMessage() );
			}
		}
		$other = new Crypto( array( 'key' => self::key() ) );
		$this->expectExceptionMessage( 'rc.crypto.decrypt_failed' );
		$other->decrypt( $cipher );
	}

	public function test_context_binding(): void {
		$c      = new Crypto( array( 'key' => self::key() ) );
		$ctx    = array( 'blog_id' => 1, 'profile_uuid' => 'p-1', 'platform' => 'google' );
		$cipher = $c->encrypt( 'tok', $ctx );
		$this->assertSame( 'tok', $c->decrypt( $cipher, array( 'platform' => 'google', 'blog_id' => '1', 'profile_uuid' => 'p-1' ) ) );
		$this->expectExceptionMessage( 'rc.crypto.decrypt_failed' );
		$c->decrypt( $cipher, array( 'blog_id' => 2, 'profile_uuid' => 'p-1', 'platform' => 'google' ) );
	}

	public function test_rotation_with_previous_key(): void {
		$old_key = self::key();
		$new_key = self::key();
		$old     = new Crypto( array( 'key' => $old_key ) );
		$cipher  = $old->encrypt( 'tok', array( 'profile_uuid' => 'p' ) );

		$new = new Crypto( array( 'key' => $new_key, 'previous' => $old_key ) );
		$this->assertTrue( $new->needs_rotation( $cipher ) );
		$this->assertSame( 'tok', $new->decrypt( $cipher ) );
		$rotated = $new->rotate( $cipher );
		$this->assertFalse( $new->needs_rotation( $rotated ) );
		$this->assertSame( $rotated, $new->rotate( $rotated ) );
		$this->assertSame( 'tok', $new->decrypt( $rotated, array( 'profile_uuid' => 'p' ) ) );

		$this->expectExceptionMessage( 'rc.crypto.decrypt_failed' );
		( new Crypto( array( 'key' => $new_key ) ) )->decrypt( $cipher );
	}

	public function test_fallback_counts_as_previous_when_switching_to_explicit(): void {
		$cipher = ( new Crypto( array() ) )->encrypt( 'tok' );
		$new    = new Crypto( array( 'key' => self::key() ) );
		$this->assertTrue( $new->needs_rotation( $cipher ) );
		$this->assertSame( 'tok', $new->decrypt( $new->rotate( $cipher ) ) );
	}

	public function test_key_check_and_no_key_in_debug_output(): void {
		$key = self::key();
		$c   = new Crypto( array( 'key' => $key ) );
		$this->assertNull( $c->key_check_matches() );
		$c->store_key_check();
		$this->assertTrue( $c->key_check_matches() );
		$this->assertFalse( ( new Crypto( array( 'key' => self::key() ) ) )->key_check_matches() );
		$dump = print_r( $c, true );
		$this->assertStringNotContainsString( base64_decode( $key ), $dump );
		$this->assertSame( 44, strlen( Crypto::generate_key() ) );
	}
}
