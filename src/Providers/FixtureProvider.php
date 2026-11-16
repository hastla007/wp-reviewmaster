<?php
/**
 * Fixture-backed demo provider for tests and local development (RC-083).
 *
 * Never registered in production: ProvidersModule only registers it when the constant
 * REVIEW_CAPTAIN_DEV_FIXTURES is true and the environment type is local/development.
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Providers;

use ReviewCaptain\Domain\Kind;
use ReviewCaptain\Domain\Origin;
use ReviewCaptain\Domain\Platform;
use ReviewCaptain\Domain\Review;
use ReviewCaptain\Domain\Verification;
use ReviewCaptain\Providers\Contract\ProviderInterface;
use ReviewCaptain\Support\ErrorCode;

/**
 * Reads JSON fixtures from tests/fixtures/providers/<key>/ (format: docs/contracts/provider-v1.md).
 */
final class FixtureProvider implements ProviderInterface {

	/**
	 * Fixture pages in use (file names without .json).
	 *
	 * @var list<string>
	 */
	private array $pages = array( 'sync_page_1', 'sync_page_2' );

	private ?string $error = null;

	/**
	 * Last quota state.
	 *
	 * @var array{state: string, remaining: ?int, reset_at: ?string, retry_after_seconds: ?int}
	 */
	private array $quota = array(
		'state'               => 'unknown',
		'remaining'           => null,
		'reset_at'            => null,
		'retry_after_seconds' => null,
	);

	/**
	 * Constructor.
	 *
	 * @param string $dir Fixture directory (e.g. tests/fixtures/providers/demo).
	 * @param string $key Provider key.
	 */
	public function __construct( private string $dir, private string $key = 'demo' ) {
		$this->dir = rtrim( $dir, '/\\' );
	}

	/**
	 * Copy using other fixture pages, e.g. array( 'sync_page_changed' ).
	 *
	 * @param list<string> $pages Page fixture names.
	 */
	public function with_pages( array $pages ): self {
		$clone        = clone $this;
		$clone->pages = array_values( $pages );
		return $clone;
	}

	/**
	 * Copy that fails every sync_page() call with the given error fixture (e.g. error_429).
	 *
	 * @param string $fixture Error fixture name.
	 */
	public function with_error( string $fixture ): self {
		$clone        = clone $this;
		$clone->error = $fixture;
		return $clone;
	}

	/** {@inheritDoc} */
	public function key(): string {
		return $this->key;
	}

	/** {@inheritDoc} */
	public function label(): string {
		return __( 'Demo-Bewertungen (Fixture)', 'review-captain' );
	}

	/** {@inheritDoc} */
	public function version(): string {
		return '1.0.0';
	}

	/** {@inheritDoc} */
	public function platform(): Platform {
		return Platform::OTHER;
	}

	/** {@inheritDoc} */
	public function contract_version(): string {
		return '1.0';
	}

	/** {@inheritDoc} */
	public function capabilities(): array {
		$caps = $this->fixture( 'capabilities' )['capabilities'] ?? array( 'read_reviews' );
		return array_values( array_filter( (array) $caps, 'is_string' ) );
	}

	/** {@inheritDoc} */
	public function test_connection( array $credentials ): ProviderResult {
		$key = (string) ( $credentials['api_key'] ?? '' );
		if ( '' === $key || 'invalid' === $key ) {
			return ProviderResult::error( ErrorCode::AUTH_INVALID, __( 'API-Schlüssel fehlt oder wurde abgelehnt.', 'review-captain' ) );
		}
		return ProviderResult::ok(
			array(
				'account' => array(
					'external_ref' => 'acct-demo-1',
					'label'        => 'Demo GmbH',
				),
				'checks'  => array(
					array(
						'key' => 'auth',
						'ok'  => true,
					),
				),
			)
		);
	}

	/** {@inheritDoc} */
	public function list_profiles( array $credentials, ?string $cursor = null ): array {
		$data     = $this->fixture( 'list_profiles' );
		$profiles = array();
		foreach ( (array) ( $data['profiles'] ?? array() ) as $p ) {
			$profiles[] = array(
				'external_ref' => (string) ( $p['external_ref'] ?? '' ),
				'label'        => (string) ( $p['label'] ?? '' ),
				'public_url'   => isset( $p['public_url'] ) ? (string) $p['public_url'] : null,
				'selectable'   => ! empty( $p['selectable'] ),
				'reason'       => isset( $p['reason'] ) ? (string) $p['reason'] : null,
			);
		}
		return array(
			'profiles'    => $profiles,
			'next_cursor' => null,
		);
	}

	/** {@inheritDoc} */
	public function sync_page( array $profile, ?string $cursor, int $limit ): SyncPage {
		if ( null !== $this->error ) {
			$err = $this->fixture( $this->error );
			throw ProviderException::from_http( (int) ( $err['http_status'] ?? 500 ), isset( $err['headers']['Retry-After'] ) ? (int) $err['headers']['Retry-After'] : null, ! empty( $err['token_expired'] ) );
		}
		$limit    = max( 1, min( 100, $limit ) );
		$response = null;
		foreach ( $this->pages as $name ) {
			$page = $this->fixture( $name );
			if ( ( $page['request']['cursor'] ?? null ) === $cursor ) {
				$response = (array) ( $page['response'] ?? array() );
				break;
			}
		}
		if ( null === $response ) {
			throw new ProviderException( ErrorCode::DATA_INVALID_PAYLOAD, __( 'Unbekannter Cursor.', 'review-captain' ) );
		}
		if ( isset( $response['quota'] ) && is_array( $response['quota'] ) ) {
			$this->quota = array(
				'state'               => (string) ( $response['quota']['state'] ?? 'unknown' ),
				'remaining'           => isset( $response['quota']['remaining'] ) ? (int) $response['quota']['remaining'] : null,
				'reset_at'            => isset( $response['quota']['reset_at'] ) ? (string) $response['quota']['reset_at'] : null,
				'retry_after_seconds' => isset( $response['quota']['retry_after_seconds'] ) ? (int) $response['quota']['retry_after_seconds'] : null,
			);
		}
		$reviews = array();
		foreach ( array_slice( (array) ( $response['items'] ?? array() ), 0, $limit ) as $raw ) {
			$review = is_array( $raw ) ? $this->normalize( $raw, $profile ) : null;
			if ( null !== $review ) {
				$reviews[] = $review;
			}
		}
		$removed = array();
		foreach ( (array) ( $response['removed'] ?? array() ) as $item ) {
			if ( is_array( $item ) && isset( $item['external_id'] ) && is_string( $item['external_id'] ) && '' !== $item['external_id'] ) {
				$removed[] = $item['external_id'];
			}
		}
		$next = $response['next_cursor'] ?? null;
		return new SyncPage( $reviews, is_string( $next ) && '' !== $next ? $next : null, $removed );
	}

	/** {@inheritDoc} */
	public function quota_status(): array {
		return $this->quota;
	}

	/**
	 * Normalise a raw demo item (pure, no I/O). Items without ID are dropped.
	 *
	 * @param array<string, mixed> $raw     Raw item.
	 * @param array<string, mixed> $profile Profile.
	 */
	public function normalize( array $raw, array $profile ): ?Review {
		if ( empty( $raw['id'] ) || ! is_string( $raw['id'] ) ) {
			return null;
		}
		$r                   = new Review();
		$r->kind             = Kind::REVIEW;
		$r->origin           = Origin::PROVIDER_SYNC;
		$r->platform         = $this->platform();
		$r->verification     = Verification::EXTERNAL_PLATFORM;
		$r->profile_id       = isset( $profile['id'] ) ? (int) $profile['id'] : null;
		$r->external_id      = $raw['id'];
		$r->rating_value     = isset( $raw['stars'] ) && is_numeric( $raw['stars'] ) ? (float) $raw['stars'] : null;
		$r->rating_scale_min = 1.0;
		$r->rating_scale_max = 5.0;
		$r->title            = wp_strip_all_tags( (string) ( $raw['headline'] ?? '' ) );
		$r->body             = wp_strip_all_tags( (string) ( $raw['text'] ?? '' ) );
		$r->language         = substr( (string) ( $raw['lang'] ?? '' ), 0, 20 );
		$r->author_name      = wp_strip_all_tags( (string) ( $raw['reviewer']['display'] ?? '' ) );
		$r->source_url       = isset( $raw['link'] ) ? (string) $raw['link'] : null;
		$created             = isset( $raw['created'] ) ? strtotime( (string) $raw['created'] ) : false;
		$r->published_at     = false !== $created ? gmdate( 'Y-m-d H:i:s', $created ) : null;
		$r->meta             = array(
			'provider_key'      => $this->key,
			'remote_updated_at' => isset( $raw['updated'] ) ? (string) $raw['updated'] : null,
		);
		if ( isset( $raw['reply']['text'] ) ) {
			$r->meta['reply'] = array(
				'body'         => wp_strip_all_tags( (string) $raw['reply']['text'] ),
				'published_at' => (string) ( $raw['reply']['date'] ?? '' ),
			);
		}
		return $r;
	}

	/**
	 * Load a fixture file.
	 *
	 * @param string $name Name without extension.
	 * @return array<string, mixed>
	 */
	private function fixture( string $name ): array {
		$name = preg_replace( '/[^a-z0-9_]/', '', $name );
		$file = $this->dir . '/' . $name . '.json';
		if ( ! is_readable( $file ) ) {
			return array();
		}
		$data = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return is_array( $data ) ? $data : array();
	}
}
