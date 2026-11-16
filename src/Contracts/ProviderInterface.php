<?php
/**
 * Provider contract v1 (RC-020, docs/m1/vertraege.md). API providers are implemented by Review Captain Pro.
 *
 * @package ReviewCaptain
 */

namespace ReviewCaptain\Contracts;

defined( 'ABSPATH' ) || exit;

interface ProviderInterface {

	public const PROVIDER_CONTRACT = '1.0';

	public function key(): string;

	public function label(): string;

	/**
	 * Keys: read_reviews, read_summary, delta_by_updated_at, full_listing, reports_deletions, deletion_by_absence, reply, invite (bool),
	 * max_cache_hours (int), requires_attribution (string), rating_scale (int[2]).
	 *
	 * @return array<string,mixed>
	 */
	public function capabilities(): array;

	/**
	 * @param array<string,mixed> $input Connection input (OAuth return or credentials).
	 * @return array{status:string, code:string, redirect?:string, connection_ref?:string}
	 */
	public function connect( array $input ): array;

	/**
	 * @return array{status:string, code:string}
	 */
	public function test( string $connection_ref ): array;

	/**
	 * @return array<int,array{ref:string,label:string,url?:string,locale?:string}>
	 */
	public function list_profiles( string $connection_ref ): array;

	/**
	 * @param array<string,mixed> $opts limit, since.
	 * @return array{items: array<int,array<string,mixed>>, next_cursor: ?string, complete: bool, deleted_external_ids: string[], rate_limit?: array<string,mixed>}
	 */
	public function sync_page( string $connection_ref, string $profile_ref, ?string $cursor, array $opts ): array;

	/**
	 * Map a raw item to the normalized review fields (RC-018). Must include external_id.
	 *
	 * @param array<string,mixed> $raw Raw provider item.
	 * @return array<string,mixed>
	 */
	public function normalize( array $raw, string $profile_ref ): array;

	/**
	 * @return array{remaining:?int, reset_at:?string, warn:bool}
	 */
	public function quota_status( string $connection_ref ): array;

	/**
	 * @return array{status:string, code:string}
	 */
	public function disconnect( string $connection_ref ): array;
}
