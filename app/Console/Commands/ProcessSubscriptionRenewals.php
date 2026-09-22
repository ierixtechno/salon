<?php

namespace App\Console\Commands;

use App\Domain\Core\Models\NotificationLog;
use App\Domain\Core\Scopes\TenantScope;
use App\Domain\Platform\Actions\CreateQuotation;
use App\Domain\Platform\Actions\NotifyTenantBillingContacts;
use App\Domain\Platform\Models\Quotation;
use App\Domain\Platform\Models\TenantSubscription;
use App\Domain\Platform\Support\ResolveSubscriptionAccessState;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Idempotent per tenant per day (exact-day matching, mirroring
 * RunMembershipExpiryCampaign) — a missed or doubled cron firing never
 * sends a duplicate reminder or creates a duplicate renewal Quotation.
 *
 * Access control itself (EnforceSubscriptionAccess) never depends on this
 * command having run — it always recomputes live from ends_at — so this
 * command is purely responsible for the reminder notification, the
 * auto-generated renewal Quotation, and cosmetically flipping
 * TenantSubscription.status to 'expired' for reporting/Tenant-list clarity.
 */
class ProcessSubscriptionRenewals extends Command
{
    protected $signature = 'subscriptions:process-renewals';

    protected $description = 'Send renewal reminders (15 days before a yearly plan ends, 5 days before monthly), auto-create the renewal Quotation, send an expiry notice when the grace period starts, and mark lapsed subscriptions expired.';

    public function __construct(private readonly CreateQuotation $createQuotation)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->markLapsedAsExpired();

        $reminders = 0;
        $expiries = 0;
        $blocks = 0;

        $subscriptions = TenantSubscription::whereIn('status', ['active', 'expired'])
            ->with(['tenant', 'plan'])
            ->get();

        foreach ($subscriptions as $subscription) {
            if (! $subscription->tenant || ! $subscription->plan || ! $subscription->ends_at) {
                continue;
            }

            try {
                if ($this->alreadyNotifiedToday($subscription)) {
                    // A same-day rerun (missed/doubled cron) must not
                    // re-send — CreateQuotation's own "quotation created"
                    // notification means sendReminder() alone isn't the
                    // only side effect that needs guarding.
                    continue;
                }

                $threshold = $subscription->plan->billing_interval === 'yearly' ? 15 : 5;

                if ($subscription->ends_at->isSameDay(today()->addDays($threshold))) {
                    $this->sendReminder($subscription, "renews on {$subscription->ends_at->format('d M Y')}. Renew now to avoid interruption.");
                    $this->ensurePendingQuotation($subscription);
                    $reminders++;
                } elseif ($subscription->ends_at->isSameDay(today()->subDay())) {
                    $this->sendReminder($subscription, 'expired. You have 7 days of read-only access — renew now to restore full access.');
                    $this->ensurePendingQuotation($subscription);
                    $expiries++;
                } elseif ($subscription->ends_at->isSameDay(today()->subDays(ResolveSubscriptionAccessState::GRACE_DAYS + 1))) {
                    // Today is the first fully-blocked day (grace just ran
                    // out). EnforceSubscriptionAccess already recomputes
                    // this live and would redirect them regardless — this
                    // step's job is purely to force an immediate logout on
                    // every device rather than waiting for their existing
                    // session to naturally expire.
                    $this->sendReminder($subscription, 'and its 7-day grace period have ended. You have been logged out everywhere — renew now to restore access.');
                    $this->forceLogout($subscription);
                    $blocks++;
                }
            } catch (Throwable $e) {
                // One tenant's problem (e.g. its plan was deactivated
                // underneath it) must not stop every other tenant's
                // reminder/renewal from processing today.
                Log::error('Subscription renewal processing failed for a tenant.', [
                    'tenant_id' => $subscription->tenant_id,
                    'tenant_subscription_id' => $subscription->id,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Renewal reminders sent: {$reminders}. Expiry notices sent: {$expiries}. Tenants force-logged-out: {$blocks}.");

        return self::SUCCESS;
    }

    private function alreadyNotifiedToday(TenantSubscription $subscription): bool
    {
        return NotificationLog::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $subscription->tenant_id)
            ->where('reference_type', 'TenantSubscription')
            ->where('reference_id', $subscription->id)
            ->whereDate('created_at', today())
            ->exists();
    }

    private function markLapsedAsExpired(): void
    {
        TenantSubscription::where('status', 'active')
            ->whereDate('ends_at', '<', today())
            ->update(['status' => 'expired']);
    }

    private function sendReminder(TenantSubscription $subscription, string $message): void
    {
        // In-app and email both — a renewing tenant may not have opened
        // the app lately, and an in-app notification alone would never
        // reach them (see NotifyTenantBillingContacts).
        app(NotifyTenantBillingContacts::class)->renewalReminder($subscription, $message);
    }

    /**
     * Deletes every database-session row belonging to this tenant's
     * users — the database session driver treats a missing row as "not
     * logged in", so this is a real, immediate logout on every device,
     * not just a redirect on their current request. Safe to call even if
     * SESSION_DRIVER isn't 'database' (the table is still Laravel's
     * default; a no-op query if nothing matches).
     */
    private function forceLogout(TenantSubscription $subscription): void
    {
        $userIds = $subscription->tenant->users()->pluck('id');

        if ($userIds->isEmpty()) {
            return;
        }

        DB::table('sessions')->whereIn('user_id', $userIds)->delete();
    }

    private function ensurePendingQuotation(TenantSubscription $subscription): void
    {
        $hasPending = Quotation::where('tenant_id', $subscription->tenant_id)
            ->where('status', 'pending')
            ->exists();

        if ($hasPending) {
            return;
        }

        $this->createQuotation->execute(
            tenant: $subscription->tenant,
            plan: $subscription->plan,
            createdBy: null,
        );
    }
}
