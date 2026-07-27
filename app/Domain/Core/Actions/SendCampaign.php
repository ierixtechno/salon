<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\BusinessProfile;
use App\Domain\Core\Models\Campaign;
use App\Domain\Core\Models\CampaignRecipient;
use App\Domain\Core\Models\Customer;
use App\Domain\Core\Models\CustomerSegment;
use App\Domain\Core\Models\NotificationTemplate;
use App\Domain\Core\Scopes\TenantScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Resolves recipients (from the campaign's segment, or an explicit list
 * for system automations that don't use segments), skips anyone without
 * marketing consent (CLAUDE.md §36 — `customers.marketing_consent`, kept
 * in sync by RecordCustomerConsent) or a usable address for the channel,
 * and hands each one to SendNotification.
 *
 * Idempotent: campaign_recipients has a unique (campaign_id, customer_id)
 * constraint, and only ever-`pending` rows are (re)processed — re-running
 * SendCampaign against an already-`sending`/`sent` campaign never
 * double-sends to a recipient already handled (CLAUDE.md §24/§44).
 *
 * `campaign_recipients.status = 'sent'` means "handed off for delivery",
 * not "confirmed delivered" — the definitive per-channel outcome lives on
 * the linked NotificationLog, which DeliverNotification updates
 * asynchronously for email/sms/whatsapp.
 *
 * Every lookup here explicitly bypasses TenantScope in favor of
 * `$campaign->tenant_id` (already a trusted value — the Campaign was
 * loaded/authorized before reaching this action) rather than relying on
 * the ambient session scope: this runs both from authenticated web
 * requests *and* from RunMarketingAutomations/scheduled-campaign dispatch,
 * a console context with no authenticated user (CLAUDE.md §11). Eloquent
 * relations (`$campaign->segment`/`->template`) would otherwise silently
 * fail closed to null/empty in that console context.
 */
class SendCampaign
{
    public function execute(Campaign $campaign, ?Collection $customers = null): Campaign
    {
        abort_unless(
            in_array($campaign->status, ['draft', 'scheduled'], true) && $campaign->canTransitionTo('sending'),
            409,
            "Cannot send a campaign that is currently {$campaign->status}.",
        );

        $tenantId = $campaign->tenant_id;

        $campaign = DB::transaction(function () use ($campaign) {
            $campaign = Campaign::withoutGlobalScope(TenantScope::class)->whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            abort_unless($campaign->canTransitionTo('sending'), 409, "Cannot send a campaign that is currently {$campaign->status}.");
            $campaign->status = 'sending';
            $campaign->save();

            return $campaign;
        });

        if ($customers === null) {
            $segment = $campaign->segment_id
                ? CustomerSegment::withoutGlobalScope(TenantScope::class)->find($campaign->segment_id)
                : null;
            $customers = $segment ? app(ResolveSegmentCustomers::class)->execute($tenantId, $segment) : collect();
        }

        $businessName = BusinessProfile::withoutGlobalScope(TenantScope::class)->where('tenant_id', $tenantId)->value('display_name') ?? '';
        $template = NotificationTemplate::withoutGlobalScope(TenantScope::class)->find($campaign->template_id);

        foreach ($customers as $customer) {
            $this->processRecipient($campaign, $customer, $template, $businessName);
        }

        $campaign->status = 'sent';
        $campaign->sent_at = now();
        $campaign->save();

        return $campaign;
    }

    private function processRecipient(Campaign $campaign, Customer $customer, ?NotificationTemplate $template, string $businessName): void
    {
        $recipient = CampaignRecipient::withoutGlobalScope(TenantScope::class)
            ->firstOrNew(['campaign_id' => $campaign->id, 'customer_id' => $customer->id]);

        if ($recipient->exists && $recipient->status !== 'pending') {
            return;
        }
        if (! $recipient->exists) {
            $recipient->tenant_id = $campaign->tenant_id;
            $recipient->save();
        }

        if (! $template || ! $customer->marketing_consent) {
            $recipient->status = 'skipped_no_consent';
            $recipient->save();

            return;
        }

        $toAddress = match ($campaign->channel) {
            'email' => $customer->email,
            'sms', 'whatsapp' => $customer->phone,
            default => null,
        };

        if (! $toAddress) {
            $recipient->status = 'failed';
            $recipient->save();

            return;
        }

        $rendered = app(RenderNotificationTemplate::class)->execute($template, [
            'customer_name' => $customer->name,
            'business_name' => $businessName,
        ]);

        $log = app(SendNotification::class)->execute(
            tenantId: $campaign->tenant_id,
            channel: $campaign->channel,
            recipientType: 'customer',
            recipientId: $customer->id,
            toAddress: $toAddress,
            subject: $rendered['subject'],
            body: $rendered['body'],
            referenceType: Campaign::class,
            referenceId: $campaign->id,
        );

        $recipient->notification_log_id = $log->id;
        $recipient->status = $log->status === 'failed' ? 'failed' : 'sent';
        $recipient->save();
    }
}
