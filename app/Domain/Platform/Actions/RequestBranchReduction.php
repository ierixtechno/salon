<?php

namespace App\Domain\Platform\Actions;

use App\Domain\Core\Models\Branch;
use App\Domain\Platform\Models\BranchReductionRequest;
use App\Domain\Platform\Models\Tenant;
use App\Models\User;

/**
 * A tenant asking Super Admin to lower how many branches (and, with them,
 * how many employees — SubscriptionPlan::userLimitFor) their subscription
 * is billed for. The tenant must already have deactivated enough branches
 * themselves (BranchController::destroy) to fit under the requested count
 * — this never picks which branch to shut down (CLAUDE.md §70). There is
 * no refund for the current, already-paid period; the lower price only
 * applies from the next renewal (DecideBranchReductionRequest).
 */
class RequestBranchReduction
{
    public function execute(Tenant $tenant, User $requestedBy, int $requestedBranchCount, ?string $reason): BranchReductionRequest
    {
        abort_if(
            BranchReductionRequest::where('tenant_id', $tenant->id)->where('status', 'pending')->exists(),
            409,
            'You already have a pending branch reduction request.',
        );

        $subscription = $tenant->currentSubscription();
        abort_unless($subscription, 422, 'You need an active subscription to request a change.');

        $currentCount = $subscription->currentBranchCount();
        $plan = $subscription->plan;

        abort_unless($requestedBranchCount < $currentCount, 422, 'The requested number of branches must be lower than what you currently have.');
        abort_if($requestedBranchCount < $plan->branch_limit, 422, "Your plan includes {$plan->branch_limit} ".str('branch')->plural($plan->branch_limit)." as a minimum — to go lower, switch to a different plan.");

        $activeBranches = Branch::where('is_active', true)->count();
        abort_if(
            $activeBranches > $requestedBranchCount,
            422,
            "You still have {$activeBranches} active branches. Deactivate enough branches (Branches > Deactivate) to bring that to {$requestedBranchCount} or fewer before requesting this.",
        );

        $request = BranchReductionRequest::create([
            'tenant_id' => $tenant->id,
            'requested_by' => $requestedBy->id,
            'current_branch_count' => $currentCount,
            'requested_branch_count' => $requestedBranchCount,
            'reason' => $reason,
            'status' => 'pending',
        ]);

        // The request itself is already saved — a notification hiccup must
        // never turn that into a failed request (CLAUDE.md §37).
        try {
            app(NotifyPlatformAdmins::class)->execute(
                subject: "Branch reduction requested: {$tenant->name}",
                body: "{$tenant->name} has asked to reduce its branch count from {$currentCount} to {$requestedBranchCount}"
                    .($reason ? "\n\nReason given: {$reason}" : '')
                    ."\n\nReview it here:\n".route('platform.tenants.show', $tenant),
            );
        } catch (\Throwable $e) {
            report($e);
        }

        return $request;
    }
}
