<?php

namespace App\Console\Commands;

use App\Domain\Core\Actions\RunBirthdayCampaign;
use App\Domain\Core\Actions\RunFeedbackRequestCampaign;
use App\Domain\Core\Actions\RunMembershipExpiryCampaign;
use App\Domain\Core\Actions\RunPackageExpiryCampaign;
use App\Domain\Core\Actions\RunReEngagementCampaign;
use App\Domain\Core\Actions\SendCampaign;
use App\Domain\Core\Models\Campaign;
use App\Domain\Core\Scopes\TenantScope;
use App\Domain\Platform\Models\Tenant;
use Illuminate\Console\Command;

/**
 * The app's first scheduled task (see bootstrap/app.php) — runs daily
 * across every active tenant. Each of the five automation actions is
 * independently idempotent per tenant per day (RunsCampaignAutomation),
 * so a missed or doubled cron firing never duplicates a send.
 *
 * Also dispatches any manual campaigns a tenant scheduled for a past-due
 * `scheduled_at`, since that's the only other path that needs a
 * console-context (no authenticated session) send.
 */
class RunMarketingAutomations extends Command
{
    protected $signature = 'marketing:run-automations';

    protected $description = 'Run scheduled marketing automations (birthday, expiry reminders, re-engagement, feedback requests) and dispatch any due scheduled campaigns, for every active tenant.';

    public function handle(
        RunBirthdayCampaign $birthday,
        RunMembershipExpiryCampaign $membershipExpiry,
        RunPackageExpiryCampaign $packageExpiry,
        RunReEngagementCampaign $reEngagement,
        RunFeedbackRequestCampaign $feedbackRequest,
        SendCampaign $sendCampaign,
    ): int {
        $tenants = Tenant::all()->filter->isActive();

        foreach ($tenants as $tenant) {
            $birthday->execute($tenant);
            $membershipExpiry->execute($tenant);
            $packageExpiry->execute($tenant);
            $reEngagement->execute($tenant);
            $feedbackRequest->execute($tenant);

            $dueCampaigns = Campaign::withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', $tenant->id)
                ->where('status', 'scheduled')
                ->where('scheduled_at', '<=', now())
                ->get();

            foreach ($dueCampaigns as $campaign) {
                $sendCampaign->execute($campaign);
            }
        }

        $this->info('Marketing automations run for '.$tenants->count().' active tenant(s).');

        return self::SUCCESS;
    }
}
