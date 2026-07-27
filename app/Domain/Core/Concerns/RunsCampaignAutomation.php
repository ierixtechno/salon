<?php

namespace App\Domain\Core\Concerns;

use App\Domain\Core\Actions\SendCampaign;
use App\Domain\Core\Models\Campaign;
use App\Domain\Core\Models\CampaignAutomation;
use App\Domain\Core\Models\NotificationTemplate;
use App\Domain\Core\Scopes\TenantScope;
use App\Domain\Platform\Models\Tenant;
use Illuminate\Support\Collection;

/**
 * Shared shape for every scheduled automation (birthday, membership
 * expiry, package expiry, re-engagement, feedback request): load the
 * tenant's config, skip if not enabled/no template, skip if already run
 * today (RunMarketingAutomations runs daily — a second run the same day
 * must be a no-op, CLAUDE.md §44), resolve the precise target population,
 * create today's Campaign row, hand off to SendCampaign.
 *
 * Every query is explicitly tenant-scoped (`withoutGlobalScope` +
 * `where('tenant_id', ...)`) rather than relying on the ambient session
 * scope — this runs from a console command with no authenticated user
 * (CLAUDE.md §11).
 */
trait RunsCampaignAutomation
{
    abstract protected function type(): string;

    abstract protected function label(): string;

    abstract protected function resolveCustomers(int $tenantId, CampaignAutomation $automation): Collection;

    public function execute(Tenant $tenant): ?Campaign
    {
        $automation = CampaignAutomation::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->where('type', $this->type())
            ->first();

        if (! $automation || ! $automation->isReady()) {
            return null;
        }

        $alreadyRanToday = Campaign::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->where('type', $this->type())
            ->whereDate('created_at', today())
            ->exists();

        if ($alreadyRanToday) {
            return null;
        }

        $template = NotificationTemplate::withoutGlobalScope(TenantScope::class)->find($automation->template_id);
        if (! $template) {
            return null;
        }

        $customers = $this->resolveCustomers($tenant->id, $automation);
        if ($customers->isEmpty()) {
            return null;
        }

        $campaign = new Campaign([
            'name' => $this->label().' — '.today()->toDateString(),
            'type' => $this->type(),
            'channel' => $template->channel,
            'template_id' => $template->id,
        ]);
        $campaign->tenant_id = $tenant->id;
        $campaign->status = 'draft';
        $campaign->save();

        return app(SendCampaign::class)->execute($campaign, $customers);
    }
}
