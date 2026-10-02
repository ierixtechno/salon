<?php

namespace App\Domain\Platform\Actions;

use App\Domain\Platform\Models\EmployeeReductionRequest;
use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Platform\Models\Tenant;
use App\Domain\Platform\Models\TenantSubscription;
use Illuminate\Support\Facades\DB;

/**
 * Super Admin approves or rejects a tenant's request to lower its
 * directly-purchased employee count (RequestEmployeeReduction). Approval
 * takes effect immediately on the subscription's `extra_user_count` — no
 * refund for the already-paid current period; the lower price only shows
 * up on the NEXT renewal quotation (ProcessSubscriptionRenewals prices that
 * from TenantSubscription::currentExtraUserCount() at the time it runs).
 */
class DecideEmployeeReductionRequest
{
    public function approve(EmployeeReductionRequest $request, PlatformAdmin $decidedBy): EmployeeReductionRequest
    {
        return DB::transaction(function () use ($request, $decidedBy) {
            $request = EmployeeReductionRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            abort_unless($request->status === 'pending', 409, 'This request has already been decided.');

            $tenant = Tenant::findOrFail($request->tenant_id);
            $subscription = $tenant->currentSubscription();
            abort_unless($subscription, 422, 'This tenant no longer has an active subscription.');

            // Re-check now, not just at request time — the tenant may have
            // reactivated/hired staff in the meantime, or branches may have
            // changed the base limit since the request was made.
            $newLimit = $subscription->plan->totalUserLimit($subscription->currentBranchCount(), $request->requested_extra_user_count);
            $activeEmployees = $tenant->activeUserCount();

            abort_if(
                $newLimit !== null && $activeEmployees > $newLimit,
                409,
                "{$tenant->name} now has {$activeEmployees} active employees, more than the {$newLimit} the new total would allow. Ask them to deactivate more first.",
            );

            TenantSubscription::whereKey($subscription->id)->update(['extra_user_count' => $request->requested_extra_user_count]);
            Tenant::forgetSubscriptionCache($tenant->id);

            $request->update([
                'status' => 'approved',
                'decided_by' => $decidedBy->id,
                'decided_at' => now(),
            ]);

            $this->notifyTenant($request, "Your employee reduction request was approved. Your subscription now covers {$request->requested_extra_user_count} directly-purchased employee ".str('slot')->plural($request->requested_extra_user_count).", reflected from your next renewal onward.");

            return $request;
        });
    }

    public function reject(EmployeeReductionRequest $request, PlatformAdmin $decidedBy, ?string $reason): EmployeeReductionRequest
    {
        abort_unless($request->status === 'pending', 409, 'This request has already been decided.');

        $request->update([
            'status' => 'rejected',
            'decided_by' => $decidedBy->id,
            'decided_at' => now(),
            'decision_reason' => $reason,
        ]);

        $this->notifyTenant($request, 'Your employee reduction request was not approved.'.($reason ? " Reason: {$reason}" : ''));

        return $request;
    }

    private function notifyTenant(EmployeeReductionRequest $request, string $message): void
    {
        try {
            $tenant = Tenant::findOrFail($request->tenant_id);

            app(NotifyTenantBillingContacts::class)->employeeReductionDecided($tenant, $message);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
