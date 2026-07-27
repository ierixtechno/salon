<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Concerns\RunsCampaignAutomation;
use App\Domain\Core\Models\Appointment;
use App\Domain\Core\Models\CampaignAutomation;
use App\Domain\Core\Models\Customer;
use App\Domain\Core\Scopes\TenantScope;
use Illuminate\Support\Collection;

/**
 * Yesterday's completed appointments, not "today's" — gives the customer
 * a day before asking, and keeps the trigger window unambiguous (a same-
 * day trigger would need to know how far through the day it's running).
 */
class RunFeedbackRequestCampaign
{
    use RunsCampaignAutomation;

    protected function type(): string
    {
        return 'feedback_request';
    }

    protected function label(): string
    {
        return 'How Was Your Visit';
    }

    protected function resolveCustomers(int $tenantId, CampaignAutomation $automation): Collection
    {
        $customerIds = Appointment::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereDate('completed_at', today()->subDay())
            ->pluck('customer_id')
            ->unique();

        return Customer::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereNull('erased_at')
            ->whereIn('id', $customerIds)
            ->get();
    }
}
