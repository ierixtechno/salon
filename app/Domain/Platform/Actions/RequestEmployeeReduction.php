<?php

namespace App\Domain\Platform\Actions;

use App\Domain\Platform\Models\EmployeeReductionRequest;
use App\Domain\Platform\Models\Tenant;
use App\Models\User;

/**
 * A tenant asking Super Admin to lower how many directly-purchased employee
 * slots their subscription is billed for — the employee-only counterpart
 * to RequestBranchReduction. The tenant must already have deactivated
 * enough staff themselves (EmployeeController::destroy) to fit under the
 * new total limit — this never picks which employee to deactivate
 * (CLAUDE.md §70). No refund for the current, already-paid period; the
 * lower price only applies from the next renewal
 * (DecideEmployeeReductionRequest).
 */
class RequestEmployeeReduction
{
    public function execute(Tenant $tenant, User $requestedBy, int $requestedExtraUserCount, ?string $reason): EmployeeReductionRequest
    {
        abort_if(
            EmployeeReductionRequest::where('tenant_id', $tenant->id)->where('status', 'pending')->exists(),
            409,
            'You already have a pending employee reduction request.',
        );

        $subscription = $tenant->currentSubscription();
        abort_unless($subscription, 422, 'You need an active subscription to request a change.');

        $plan = $subscription->plan;
        $currentExtra = $subscription->currentExtraUserCount();

        abort_if($requestedExtraUserCount < 0, 422, 'The requested number cannot be negative.');
        abort_unless($requestedExtraUserCount < $currentExtra, 422, 'The requested number must be lower than what you currently have purchased.');

        $newLimit = $plan->totalUserLimit($subscription->currentBranchCount(), $requestedExtraUserCount);
        $activeEmployees = $tenant->activeUserCount();

        abort_if(
            $newLimit !== null && $activeEmployees > $newLimit,
            422,
            "You still have {$activeEmployees} active employees. Deactivate enough (Employees > Deactivate) to bring that to {$newLimit} or fewer before requesting this.",
        );

        $request = EmployeeReductionRequest::create([
            'tenant_id' => $tenant->id,
            'requested_by' => $requestedBy->id,
            'current_extra_user_count' => $currentExtra,
            'requested_extra_user_count' => $requestedExtraUserCount,
            'reason' => $reason,
            'status' => 'pending',
        ]);

        // The request itself is already saved — a notification hiccup must
        // never turn that into a failed request (CLAUDE.md §37).
        try {
            app(NotifyPlatformAdmins::class)->execute(
                subject: "Employee reduction requested: {$tenant->name}",
                body: "{$tenant->name} has asked to reduce its purchased employee slots from {$currentExtra} to {$requestedExtraUserCount}"
                    .($reason ? "\n\nReason given: {$reason}" : '')
                    ."\n\nReview it here:\n".route('platform.tenants.show', $tenant),
            );
        } catch (\Throwable $e) {
            report($e);
        }

        return $request;
    }
}
