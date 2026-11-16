<?php
/**
 * Review provider contract v1 (RC-020). See docs/contracts/provider-v1.md.
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Providers\Contract;

use ReviewCaptain\Domain\Platform;
use ReviewCaptain\Providers\ProviderResult;
use ReviewCaptain\Providers\SyncPage;

/**
 * Read-only adapter for one review platform API. Providers never persist data or credentials;
 * persistence, dedupe, tombstones and jobs belong to the core (SyncService) or the Pro runtime.
 * Methods except metadata are only called from jobs, WP-CLI or admin actions – never from a
 * frontend request. Errors are thrown as ProviderException (a ConnectorException).
 */
interface ProviderInterface {

	public const CAPABILITIES = array( 'read_reviews', 'list_profiles', 'summary', 'invite', 'reply', 'delete_sync' );

	/**
	 * Stable key, regex ^[a-z0-9][a-z0-9_.-]{1,63}$ (official providers: key === platform value).
	 */
	public function key(): string;

	/**
	 * Translatable display name.
	 */
	public function label(): string;

	/**
	 * Implementation version (SemVer).
	 */
	public function version(): string;

	/**
	 * Platform the reviews belong to.
	 */
	public function platform(): Platform;

	/**
	 * Implemented contract version, "1.<minor>" or "1.x".
	 */
	public function contract_version(): string;

	/**
	 * Capabilities, subset of self::CAPABILITIES.
	 *
	 * @return list<string>
	 */
	public function capabilities(): array;

	/**
	 * Check credentials and permissions without importing data. Must not throw for invalid credentials;
	 * returns ProviderResult::error() instead.
	 *
	 * @param array<string, string> $credentials Decrypted credentials (in memory only).
	 */
	public function test_connection( array $credentials ): ProviderResult;

	/**
	 * List accessible profiles/locations/apps.
	 *
	 * @param array<string, string> $credentials Credentials.
	 * @param string|null           $cursor      Cursor.
	 * @return array{profiles: list<array{external_ref: string, label: string, public_url: ?string, selectable: bool, reason: ?string}>, next_cursor: ?string}
	 */
	public function list_profiles( array $credentials, ?string $cursor = null ): array;

	/**
	 * Fetch one page of normalised reviews.
	 *
	 * @param array<string, mixed> $profile Profile: id (int), external_ref (string), credentials (array, in memory only), settings (array).
	 * @param string|null          $cursor  Opaque cursor (null = first page).
	 * @param int                  $limit   Max. items (1..100).
	 * @throws \ReviewCaptain\Providers\ProviderException On provider errors.
	 */
	public function sync_page( array $profile, ?string $cursor, int $limit ): SyncPage;

	/**
	 * Current quota/rate-limit state (may be cached from the last response).
	 *
	 * @return array{state: string, remaining: ?int, reset_at: ?string, retry_after_seconds: ?int}
	 */
	public function quota_status(): array;
}
