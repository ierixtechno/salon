<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Concerns\RunsCampaignAutomation;
use App\Domain\Core\Models\CampaignAutomation;
use App\Domain\Core\Models\Customer;
use App\Domain\Core\Scopes\TenantScope;
use Illuminate\Support\Collection;

/**
 * Same "exact day, not a window" reasoning as RunMembershipExpiryCampaign.
 */
class RunPackageExpiryCampaign
{
    use RunsCampaignAutomation;

    protected function type(): string
    {
        return 'package_expiry';
    }

    protected function label(): string
    {
        return 'Package Expiring Soon';
    }

    protected function resolveCustomers(int $tenantId, CampaignAutomation $automation): Collection
    {
        $targetDate = today()->addDays($automation->threshold_days ?? 7);

        return Customer::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereNull('erased_at')
            ->whereHas('customerPackages', function ($q) use ($tenantId, $targetDate) {
                $q->withoutGlobalScope(TenantScope::class)
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'active')
                    ->whereDate('expires_at', $targetDate);
            })
            ->get();
    }
}
