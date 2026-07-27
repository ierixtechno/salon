<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Concerns\RunsCampaignAutomation;
use App\Domain\Core\Models\CampaignAutomation;
use App\Domain\Core\Models\Customer;
use App\Domain\Core\Scopes\TenantScope;
use Illuminate\Support\Collection;

class RunBirthdayCampaign
{
    use RunsCampaignAutomation;

    protected function type(): string
    {
        return 'birthday';
    }

    protected function label(): string
    {
        return 'Birthday Wishes';
    }

    protected function resolveCustomers(int $tenantId, CampaignAutomation $automation): Collection
    {
        $today = today();

        return Customer::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereNull('erased_at')
            ->whereNotNull('date_of_birth')
            ->whereRaw('MONTH(date_of_birth) = ? AND DAY(date_of_birth) = ?', [$today->month, $today->day])
            ->get();
    }
}
