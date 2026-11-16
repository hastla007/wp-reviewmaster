<?php
/**
 * Request planner registered by Review Captain Pro (RC-WF-03).
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

namespace ReviewCaptain\Workflow;

/**
 * Planning and cancelling review requests is a Pro feature. Pro registers its planner through
 * the filter `review_captain_request_planner_v1`; the core WorkflowService only reports the
 * capabilities and delegates. The planner must run the same rules as the native queue
 * (eligibility, consent, opt-out, suppression, frequency limits, platform target) – the core
 * passes the input through unchanged and never relaxes a result.
 *
 * Results use the service envelope `{ success, status, code, data }` (workflow-provider-v1.md).
 */
interface RequestPlannerInterface {

	/**
	 * Contract version implemented, e.g. "1.0".
	 */
	public function contract_version(): string;

	/**
	 * Plan a request (input review_captain.workflow.plan_request.v1).
	 *
	 * @param array<string, mixed> $input Input.
	 * @return array{success: bool, status: string, code: ?string, data: array<string, mixed>}
	 */
	public function plan_request( array $input ): array;

	/**
	 * Cancel a planned/eligible request.
	 *
	 * @param array<string, mixed> $input Input.
	 * @return array{success: bool, status: string, code: ?string, data: array<string, mixed>}
	 */
	public function cancel_request( array $input ): array;
}
