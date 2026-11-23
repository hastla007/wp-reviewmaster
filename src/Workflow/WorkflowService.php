<?php
/**
 * Public workflow services (RC-WF-03, docs/contracts/workflow-provider-v1.md).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Workflow;

use ReviewCaptain\Domain\Kind;
use ReviewCaptain\Domain\Origin;
use ReviewCaptain\Domain\Platform;
use ReviewCaptain\Domain\Review;
use ReviewCaptain\Domain\ReviewStatus;
use ReviewCaptain\Domain\Verification;
use ReviewCaptain\Plugin;
use ReviewCaptain\Support\ErrorCode;

/**
 * Container service `workflow` (`Plugin::instance()->get( 'workflow' )`).
 *
 * Licence-free core contract for Workflow Captain adapters, Form Captain and other suite plugins:
 *
 * - create_draft(): exactly one local, pending, unverified review (origin `connector`),
 *   idempotent via the caller's idempotency key (UNIQUE `dedupe_key`), never published, never an
 *   invitation;
 * - cancel_draft(): deletes such a draft only while it is still pending (CAS delete);
 * - aggregates(): privacy-preserving counters per platform/status/period, small cells masked;
 * - request_capabilities() / plan_request() / cancel_request(): planning review requests stays
 *   Pro. Pro registers a {@see RequestPlannerInterface} via `review_captain_request_planner_v1`;
 *   without it the methods answer `rc.contract.unavailable`.
 *
 * None of the methods can approve, verify or publish anything: moderation and consent stay with
 * the moderators (negative tests in tests/Unit/Workflow/WorkflowServiceTest.php).
 *
 * All methods return the envelope `{ success, status, code, data }` except cancel_draft() (bool)
 * and request_capabilities() (descriptor array).
 */
class WorkflowService {

	public const CONTRACT = '1.0';

	/**
	 * Minimum cell size for aggregates; smaller counts are reported as "<5". Callers may raise it.
	 */
	public const MIN_SAMPLE = 5;

	public const PLANNER_FILTER = 'review_captain_request_planner_v1';

	/**
	 * Input keys a workflow may never set: they would bypass moderation, verification or consent
	 * rules or carry contact data the draft contract does not accept.
	 */
	public const FORBIDDEN_FIELDS = array(
		'status',
		'approved',
		'publish',
		'published',
		'verification',
		'platform',
		'origin',
		'kind',
		'consent_publish',
		'consent_at',
		'author_email',
		'email',
		'experience_id',
		'profile_id',
		'source_url',
	);

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( private Plugin $plugin ) {}

	/**
	 * Create a local review draft (`review_captain.workflow.create_draft_review.v1`).
	 *
	 * Input: contract, idempotency_key (required, ≤ 191, [A-Za-z0-9_.:-]), rating (1–5),
	 * review_text, display_name, title?, context_type?, context_id?, source_plugin?,
	 * source_entry_id?, review_type?, verified? (must be false), consent? ({ publish: bool,
	 * given_at: ISO 8601 } – the documented publication consent of the author; it is stored, but
	 * never approves anything).
	 *
	 * @param array<string, mixed> $draft Input.
	 * @return array{success: bool, status: string, code: ?string, data: array<string, mixed>}
	 */
	public function create_draft( array $draft ): array {
		$error = $this->check_contract( $draft );
		if ( null !== $error ) {
			return $error;
		}
		foreach ( self::FORBIDDEN_FIELDS as $field ) {
			if ( array_key_exists( $field, $draft ) ) {
				return self::result( false, 'rejected', ErrorCode::DATA_INVALID_PAYLOAD, array( 'field' => $field ) );
			}
		}
		if ( array_key_exists( 'verified', $draft ) && false !== $draft['verified'] ) {
			return self::result( false, 'rejected', ErrorCode::DATA_INVALID_PAYLOAD, array( 'field' => 'verified' ) );
		}

		$key = is_string( $draft['idempotency_key'] ?? null ) ? $draft['idempotency_key'] : '';
		if ( '' === $key || strlen( $key ) > 191 || ! preg_match( '/^[A-Za-z0-9_.:-]+$/', $key ) ) {
			return self::result( false, 'rejected', ErrorCode::DATA_INVALID_PAYLOAD, array( 'field' => 'idempotency_key' ) );
		}

		$settings = $this->plugin->settings();
		$rating   = $draft['rating'] ?? null;
		if ( ! is_int( $rating ) && ! ( is_string( $rating ) && ctype_digit( $rating ) ) ) {
			return self::result( false, 'rejected', ErrorCode::DATA_INVALID_PAYLOAD, array( 'field' => 'rating' ) );
		}
		$rating = (int) $rating;
		if ( $rating < 1 || $rating > 5 ) {
			return self::result( false, 'rejected', ErrorCode::DATA_INVALID_PAYLOAD, array( 'field' => 'rating' ) );
		}
		$text   = sanitize_textarea_field( is_scalar( $draft['review_text'] ?? null ) ? (string) $draft['review_text'] : '' );
		$len    = mb_strlen( $text );
		$limits = \ReviewCaptain\Frontend\FormHandler::text_limits( $settings );
		if ( $len < $limits[0] || $len > $limits[1] ) {
			return self::result( false, 'rejected', ErrorCode::DATA_INVALID_PAYLOAD, array( 'field' => 'review_text' ) );
		}
		$name = sanitize_text_field( is_scalar( $draft['display_name'] ?? null ) ? (string) $draft['display_name'] : '' );
		if ( '' === $name || mb_strlen( $name ) > 100 ) {
			return self::result( false, 'rejected', ErrorCode::DATA_INVALID_PAYLOAD, array( 'field' => 'display_name' ) );
		}
		$title = sanitize_text_field( is_scalar( $draft['title'] ?? null ) ? (string) $draft['title'] : '' );
		if ( mb_strlen( $title ) > 150 ) {
			return self::result( false, 'rejected', ErrorCode::DATA_INVALID_PAYLOAD, array( 'field' => 'title' ) );
		}
		$type = is_string( $draft['context_type'] ?? null ) ? $draft['context_type'] : '';
		$cid  = is_scalar( $draft['context_id'] ?? null ) ? (string) $draft['context_id'] : '';
		if ( ( '' !== $type && ! in_array( $type, Review::CONTEXT_TYPES, true ) ) || ( '' !== $cid && ( '' === $type || ! preg_match( '/^[A-Za-z0-9_.:-]{1,64}$/', $cid ) ) ) ) {
			return self::result( false, 'rejected', ErrorCode::DATA_INVALID_PAYLOAD, array( 'field' => 'context' ) );
		}

		$review                  = new Review();
		$review->kind            = Kind::REVIEW;
		$review->origin          = Origin::CONNECTOR;
		$review->platform        = Platform::LOCAL;
		$review->status          = ReviewStatus::PENDING; // Forced: ignores general.new_review_status.
		$review->verification    = Verification::UNVERIFIED;
		$review->external_id     = $key;
		$review->rating_value    = (float) $rating;
		$review->title           = $title;
		$review->body            = $text;
		$review->author_name     = $name;
		$review->context_type    = '' !== $type ? $type : null;
		$review->context_id      = '' !== $type && '' !== $cid ? $cid : null;
		$review->language        = substr( (string) determine_locale(), 0, 20 );
		$review->consent_publish = false;
		$consent                 = $draft['consent'] ?? null;
		if ( is_array( $consent ) && true === ( $consent['publish'] ?? null ) ) {
			$at = is_string( $consent['given_at'] ?? null ) ? strtotime( $consent['given_at'] ) : false;
			if ( false === $at || $at > time() + 300 ) {
				return self::result( false, 'rejected', ErrorCode::DATA_INVALID_PAYLOAD, array( 'field' => 'consent' ) );
			}
			$review->consent_publish = true;
			$review->consent_at      = gmdate( 'Y-m-d H:i:s', $at );
		}
		$review->meta = array_filter(
			array(
				'source_plugin'   => substr( sanitize_key( (string) ( $draft['source_plugin'] ?? '' ) ), 0, 60 ),
				'source_entry_id' => substr( sanitize_text_field( (string) ( is_scalar( $draft['source_entry_id'] ?? null ) ? $draft['source_entry_id'] : '' ) ), 0, 64 ),
				'review_type'     => substr( sanitize_key( (string) ( $draft['review_type'] ?? '' ) ), 0, 40 ),
				'idempotency_key' => $key,
				'workflow_draft'  => true,
			),
			static fn( $v ) => '' !== $v
		);

		try {
			$review = $this->plugin->reviews()->insert( $review );
		} catch ( \InvalidArgumentException $e ) {
			return self::result( false, 'rejected', ErrorCode::DATA_INVALID_PAYLOAD, array( 'field' => 'review' ) );
		} catch ( \RuntimeException $e ) {
			// UNIQUE dedupe_key: the same idempotency key was used before (or in parallel).
			$existing = $this->plugin->reviews()->find_by_dedupe_key( (string) $review->dedupe_key() );
			if ( null === $existing || Origin::CONNECTOR !== $existing->origin ) {
				return self::result( false, 'rejected', null === $existing ? ErrorCode::INTERNAL : ErrorCode::DATA_DUPLICATE );
			}
			return self::result(
				true,
				'duplicate',
				null,
				array(
					'review_id'   => (int) $existing->id,
					'review_uuid' => $existing->uuid,
					'status'      => $existing->status->value,
				)
			);
		}
		$this->plugin->audit()->record(
			'workflow.draft_created',
			'review',
			(int) $review->id,
			array( 'status' => $review->status->value )
		);
		return self::result(
			true,
			'created',
			null,
			array(
				'review_id'   => (int) $review->id,
				'review_uuid' => $review->uuid,
				'status'      => $review->status->value,
			)
		);
	}

	/**
	 * Delete a workflow draft. Only drafts created through create_draft() (origin `connector`)
	 * and only while still pending; a draft a moderator approved/rejected in the meantime stays
	 * (CAS delete on status).
	 *
	 * @param string $uuid Review UUID.
	 */
	public function cancel_draft( string $uuid ): bool {
		$review = $this->plugin->reviews()->find_by_uuid( strtolower( trim( $uuid ) ) );
		if ( null === $review || null === $review->id || Origin::CONNECTOR !== $review->origin || ReviewStatus::PENDING !== $review->status ) {
			return false;
		}
		if ( ! $this->plugin->reviews()->delete_if_status( $review->id, ReviewStatus::PENDING ) ) {
			return false;
		}
		$this->plugin->audit()->record( 'workflow.draft_cancelled', 'review', $review->id, array( 'status' => 'deleted' ) );
		return true;
	}

	/**
	 * Privacy-preserving counters.
	 *
	 * Filter: context_type, context_id, kind (review|testimonial), platform (string|list),
	 * period_from / period_to (Y-m-d or ISO 8601, inclusive), granularity (month|year|all,
	 * default month – no finer resolution), min_sample (≥ 5).
	 *
	 * Cells below min_sample are reported as "<5". If exactly one cell of a platform is masked,
	 * the next smallest cell is masked too, so it cannot be derived from the platform total
	 * (complementary suppression). Averages only from approved reviews, per platform on the
	 * original scale, and only with at least min_sample approved ratings. Never texts, names or
	 * IDs of single reviews.
	 *
	 * @param array<string, mixed> $filter Filter.
	 * @return array{success: bool, status: string, code: ?string, data: array<string, mixed>}
	 */
	public function aggregates( array $filter = array() ): array {
		global $wpdb;
		$error = $this->check_contract( $filter );
		if ( null !== $error ) {
			return $error;
		}
		$min         = max( self::MIN_SAMPLE, (int) ( $filter['min_sample'] ?? self::MIN_SAMPLE ) );
		$granularity = in_array( $filter['granularity'] ?? 'month', array( 'month', 'year', 'all' ), true ) ? (string) ( $filter['granularity'] ?? 'month' ) : 'month';
		$date_expr   = 'COALESCE(published_at, created_at)';
		$period_expr = match ( $granularity ) {
			'year'  => "SUBSTR({$date_expr}, 1, 4)",
			'all'   => "'all'",
			default => "SUBSTR({$date_expr}, 1, 7)",
		};

		$args = array();
		foreach ( array( 'context_type', 'context_id', 'platform' ) as $key ) {
			if ( isset( $filter[ $key ] ) && '' !== $filter[ $key ] ) {
				$args[ $key ] = is_array( $filter[ $key ] ) ? array_map( 'strval', $filter[ $key ] ) : (string) $filter[ $key ];
			}
		}
		if ( isset( $filter['kind'] ) && in_array( $filter['kind'], Kind::values(), true ) ) {
			$args['kind'] = $filter['kind'];
		}
		$repo               = $this->plugin->reviews();
		[ $where, $params ] = $repo->build_where( $args );
		foreach ( array( 'period_from' => '>=', 'period_to' => '<=' ) as $key => $op ) {
			$value = self::parse_period( $filter[ $key ] ?? null, 'period_to' === $key );
			if ( null !== $value ) {
				$where   .= " AND {$date_expr} {$op} %s";
				$params[] = $value;
			}
		}
		$table = $repo->table();
		$sql   = "SELECT platform, status, {$period_expr} AS period, COUNT(*) AS cnt FROM {$table} WHERE {$where} GROUP BY platform, status, period ORDER BY platform ASC, period ASC, status ASC";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		$rows = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );

		$cells  = array();
		$totals = array();
		foreach ( (array) $rows as $row ) {
			$platform              = (string) $row['platform'];
			$cells[ $platform ][]  = array(
				'platform' => $platform,
				'status'   => (string) $row['status'],
				'period'   => (string) $row['period'],
				'count'    => (int) $row['cnt'],
			);
			$totals[ $platform ] = ( $totals[ $platform ] ?? 0 ) + (int) $row['cnt'];
		}

		$masked  = '<' . $min;
		$buckets = array();
		foreach ( $cells as $platform => $list ) {
			$hide = array();
			foreach ( $list as $i => $cell ) {
				if ( $cell['count'] < $min ) {
					$hide[ $i ] = true;
				}
			}
			if ( 1 === count( $hide ) && count( $list ) > 1 ) {
				$smallest = null;
				foreach ( $list as $i => $cell ) {
					if ( ! isset( $hide[ $i ] ) && ( null === $smallest || $cell['count'] < $list[ $smallest ]['count'] ) ) {
						$smallest = $i;
					}
				}
				if ( null !== $smallest ) {
					$hide[ $smallest ] = true;
				}
			}
			foreach ( $list as $i => $cell ) {
				if ( isset( $hide[ $i ] ) ) {
					$cell['count'] = $masked;
				}
				$buckets[] = $cell;
			}
		}

		$by_platform = array();
		$approved    = $args;
		$approved['status'] = ReviewStatus::APPROVED->value;
		$averages    = array();
		foreach ( $repo->summary_by_platform( $approved ) as $row ) {
			$averages[ $row['platform'] ][] = $row;
		}
		foreach ( $totals as $platform => $total ) {
			$rating = null;
			$scale  = array( 1.0, 5.0 );
			$rows   = $averages[ $platform ] ?? array();
			if ( 1 === count( $rows ) ) {
				$scale = array( $rows[0]['scale_min'], $rows[0]['scale_max'] );
				if ( $rows[0]['count'] >= $min ) {
					$rating = $rows[0]['average'];
				}
			}
			$by_platform[] = array(
				'platform'         => $platform,
				'count'            => $total < $min ? $masked : $total,
				'rating_value'     => $rating,
				'rating_scale_min' => $scale[0],
				'rating_scale_max' => $scale[1],
				'sample_ok'        => null !== $rating,
			);
		}

		return self::result(
			true,
			'ok',
			null,
			array(
				'as_of'       => gmdate( 'Y-m-d\TH:i:s\Z' ),
				'min_sample'  => $min,
				'granularity' => $granularity,
				'by_platform' => $by_platform,
				'buckets'     => $buckets,
			)
		);
	}

	/**
	 * The registered Pro planner, or null (missing or incompatible contract).
	 */
	public function planner(): ?RequestPlannerInterface {
		/**
		 * Register the request planner (Review Captain Pro). Return an object implementing
		 * ReviewCaptain\Workflow\RequestPlannerInterface with contract version 1.x.
		 *
		 * @param RequestPlannerInterface|null $planner Planner (null = none).
		 */
		$planner = apply_filters( self::PLANNER_FILTER, null );
		if ( ! $planner instanceof RequestPlannerInterface || ! self::compatible( $planner->contract_version() ) ) {
			return null;
		}
		return $planner;
	}

	/**
	 * Which request actions are available.
	 *
	 * @return array{contract: string, planner: bool, plan_request: bool, cancel_request: bool, planner_contract: ?string, status: string}
	 */
	public function request_capabilities(): array {
		$raw     = apply_filters( self::PLANNER_FILTER, null );
		$planner = $this->planner();
		$status  = 'available';
		if ( null === $planner ) {
			$status = $raw instanceof RequestPlannerInterface ? 'incompatible_contract' : 'source_unavailable';
		}
		return array(
			'contract'         => self::CONTRACT,
			'planner'          => null !== $planner,
			'plan_request'     => null !== $planner,
			'cancel_request'   => null !== $planner,
			'planner_contract' => $raw instanceof RequestPlannerInterface ? $raw->contract_version() : null,
			'status'           => $status,
		);
	}

	/**
	 * Plan a request through the Pro planner (same rules as the native queue).
	 *
	 * @param array<string, mixed> $input Input.
	 * @return array{success: bool, status: string, code: ?string, data: array<string, mixed>}
	 */
	public function plan_request( array $input ): array {
		return $this->delegate( 'plan_request', $input );
	}

	/**
	 * Cancel a request through the Pro planner.
	 *
	 * @param array<string, mixed> $input Input.
	 * @return array{success: bool, status: string, code: ?string, data: array<string, mixed>}
	 */
	public function cancel_request( array $input ): array {
		return $this->delegate( 'cancel_request', $input );
	}

	/**
	 * Delegate to the planner and normalise its envelope.
	 *
	 * @param string               $method Method.
	 * @param array<string, mixed> $input  Input.
	 * @return array{success: bool, status: string, code: ?string, data: array<string, mixed>}
	 */
	private function delegate( string $method, array $input ): array {
		$error = $this->check_contract( $input );
		if ( null !== $error ) {
			return $error;
		}
		$planner = $this->planner();
		if ( null === $planner ) {
			return self::result( false, 'unavailable', ErrorCode::CONTRACT_UNAVAILABLE );
		}
		try {
			$out = $planner->$method( $input );
		} catch ( \Throwable $e ) {
			return self::result( false, 'failed', ErrorCode::INTERNAL );
		}
		if ( ! is_array( $out ) || ! isset( $out['success'], $out['status'] ) ) {
			return self::result( false, 'failed', ErrorCode::CONTRACT_INVALID );
		}
		return self::result( (bool) $out['success'], (string) $out['status'], isset( $out['code'] ) ? (string) $out['code'] : null, is_array( $out['data'] ?? null ) ? $out['data'] : array() );
	}

	/**
	 * Parse a period bound (Y-m-d = whole day, otherwise strtotime; naive times are UTC).
	 *
	 * @param mixed $value Value.
	 * @param bool  $end   Upper bound.
	 */
	private static function parse_period( mixed $value, bool $end ): ?string {
		if ( ! is_string( $value ) || '' === $value ) {
			return null;
		}
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			$value .= $end ? ' 23:59:59' : ' 00:00:00';
		}
		$ts = strtotime( preg_match( '/(Z|[+-]\d{2}:?\d{2})$/', $value ) ? $value : $value . ' UTC' );
		return false === $ts ? null : gmdate( 'Y-m-d H:i:s', $ts );
	}

	/**
	 * Whether a contract version string is compatible with major 1.
	 *
	 * @param string $version Version.
	 */
	public static function compatible( string $version ): bool {
		return (bool) preg_match( '/^1(\.\d+)?$/', $version );
	}

	/**
	 * Optional `contract` input field must be major 1.
	 *
	 * @param array<string, mixed> $input Input.
	 * @return array{success: bool, status: string, code: ?string, data: array<string, mixed>}|null
	 */
	private function check_contract( array $input ): ?array {
		if ( isset( $input['contract'] ) && ! self::compatible( (string) $input['contract'] ) ) {
			return self::result( false, 'rejected', ErrorCode::CONTRACT_INCOMPATIBLE );
		}
		return null;
	}

	/**
	 * Result envelope.
	 *
	 * @param bool                 $success Success.
	 * @param string               $status  Status.
	 * @param string|null          $code    Error code.
	 * @param array<string, mixed> $data    Data.
	 * @return array{success: bool, status: string, code: ?string, data: array<string, mixed>}
	 */
	private static function result( bool $success, string $status, ?string $code = null, array $data = array() ): array {
		return array(
			'success' => $success,
			'status'  => $status,
			'code'    => $code,
			'data'    => $data,
		);
	}
}
