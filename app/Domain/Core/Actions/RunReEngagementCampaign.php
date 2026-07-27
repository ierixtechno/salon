<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Concerns\RunsCampaignAutomation;
use App\Domain\Core\Models\CampaignAutomation;
use App\Domain\Core\Models\Customer;
use App\Domain\Core\Scopes\TenantScope;
use Illuminate\Support\Collection;

/**
 * "Inactive" requires at least one past appointment — a customer with no
 * appointment history at all isn't lapsed, they're simply new, and
 * shouldn't be re-engaged the day after they're created.
 */
class RunReEngagementCampaign
{
    use RunsCampaignAutomation;

    protected function type(): string
    {
        return 're_engagement';
    }

    protected function label(): string
    {
        return 'We Miss You';
    }

    protected function resolveCustomers(int $tenantId, CampaignAutomation $automation): Collection
    {
        $cutoff = today()->subDays($automation->threshold_days ?? 90);

        return Customer::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereNull('erased_at')
            ->whereHas('appointments', function ($q) use ($tenantId) {
                $q->withoutGlobalScope(TenantScope::class)->where('tenant_id', $tenantId);
            })
            ->whereDoesntHave('appointments', function ($q) use ($tenantId, $cutoff) {
                $q->withoutGlobalScope(TenantScope::class)
                    ->where('tenant_id', $tenantId)
                    ->where('starts_at', '>=', $cutoff);
            })
            ->get();
    }
}
