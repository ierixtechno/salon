<?php

namespace App\Domain\Platform\Actions;

use App\Domain\Core\Models\Branch;
use App\Domain\Core\Scopes\TenantScope;
use App\Domain\Platform\Models\BranchReductionRequest;
use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Platform\Models\Tenant;
use App\Domain\Platform\Models\TenantSubscription;
use Illuminate\Support\Facades\DB;

/**
 * Super Admin approves or rejects a tenant's request to lower its branch
 * count (RequestBranchReduction). Approval takes effect immediately on the
 * subscription's `branch_count` — no refund for the already-paid current
 * period (confirmed decision); the lower price only shows up on the NEXT
 * renewal quotation, since ProcessSubscriptionRenewals prices that from
 * TenantSubscription::currentBranchCount() at the time it runs.
 */
class DecideBranchReductionRequest
{
    public function approve(BranchReductionRequest $request, PlatformAdmin $decidedBy): BranchReductionRequest
    {
        return DB::transaction(function () use ($request, $decidedBy) {
            $request = BranchReductionRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            abort_unless($request->status === 'pending', 409, 'This request has already been decided.');

            $tenant = Tenant::findOrFail($request->tenant_id);
            $subscription = $tenant->currentSubscription();
            abort_unless($subscription, 422, 'This tenant no longer has an active subscription.');

            // Re-check now, not just at request time — the tenant may have
            // reactivated/added a branch in the meantime, or the request may
            // be stale against a subscription that has since changed.
            $activeBranches = Branch::withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->count();

            abort_if(
                $activeBranches > $request->requested_branch_count,
                409,
                "{$tenant->name} now has {$activeBranches} active branches, more than the {$request->requested_branch_count} requested. Ask them to deactivate more first.",
            );

            TenantSubscription::whereKey($subscription->id)->update(['branch_count' => $request->requested_branch_count]);
            Tenant::forgetSubscriptionCache($tenant->id);

            $request->update([
                'status' => 'approved',
                'decided_by' => $decidedBy->id,
                'decided_at' => now(),
            ]);

            $this->notifyTenant($request, "Your branch reduction request was approved. Your subscription now covers {$request->requested_branch_count} ".str('branch')->plural($request->requested_branch_count).", reflected from your next renewal onward.");

            return $request;
        });
    }

    public function reject(BranchReductionRequest $request, PlatformAdmin $decidedBy, ?string $reason): BranchReductionRequest
    {
        abort_unless($request->status === 'pending', 409, 'This request has already been decided.');

        $request->update([
            'status' => 'rejected',
            'decided_by' => $decidedBy->id,
            'decided_at' => now(),
            'decision_reason' => $reason,
        ]);

        $this->notifyTenant($request, 'Your branch reduction request was not approved.'.($reason ? " Reason: {$reason}" : ''));

        return $request;
    }

    private function notifyTenant(BranchReductionRequest $request, string $message): void
    {
        try {
            $tenant = Tenant::findOrFail($request->tenant_id);

            app(NotifyTenantBillingContacts::class)->branchReductionDecided($tenant, $message);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
