<?php

use App\Domain\Core\Models\NotificationLog;
use App\Domain\Core\Scopes\TenantScope;
use App\Domain\Platform\Models\Quotation;
use App\Domain\Platform\Models\SubscriptionPlan;
use App\Domain\Platform\Models\TenantSubscription;

function renewalNotifications(int $tenantId)
{
    return NotificationLog::withoutGlobalScope(TenantScope::class)
        ->where('tenant_id', $tenantId)
        ->where('subject', 'Subscription renewal')
        ->get();
}

test('a reminder notification and renewal quotation are created exactly on the threshold day for a monthly plan', function () {
    $owner = onboard(['subscription_plan_code' => 'growth', 'subscription_ends_at' => now()->addDays(5)]);

    $this->artisan('subscriptions:process-renewals')->assertSuccessful();

    expect(renewalNotifications($owner->tenant_id))->toHaveCount(1);
    expect(Quotation::where('tenant_id', $owner->tenant_id)->where('status', 'pending')->count())->toBe(1);
});

test('a reminder is not sent before or after the monthly threshold day', function () {
    $tooEarly = onboard(['subscription_ends_at' => now()->addDays(6)]);
    $tooLate = onboard(['subscription_ends_at' => now()->addDays(4)]);

    $this->artisan('subscriptions:process-renewals')->assertSuccessful();

    expect(renewalNotifications($tooEarly->tenant_id))->toHaveCount(0);
    expect(renewalNotifications($tooLate->tenant_id))->toHaveCount(0);
});

test('a yearly plan reminds at 15 days, not at the monthly 5-day threshold', function () {
    $plan = SubscriptionPlan::where('code', 'pro')->firstOrFail();
    $plan->update(['billing_interval' => 'yearly']);

    $owner = onboard(['subscription_plan_code' => 'pro', 'subscription_ends_at' => now()->addDays(15)]);

    $this->artisan('subscriptions:process-renewals')->assertSuccessful();

    expect(renewalNotifications($owner->tenant_id))->toHaveCount(1);
});

test('rerunning the command the same day does not duplicate the reminder or the quotation', function () {
    $owner = onboard(['subscription_ends_at' => now()->addDays(5)]);

    $this->artisan('subscriptions:process-renewals')->assertSuccessful();
    $this->artisan('subscriptions:process-renewals')->assertSuccessful();

    expect(renewalNotifications($owner->tenant_id))->toHaveCount(1);
    expect(Quotation::where('tenant_id', $owner->tenant_id)->where('status', 'pending')->count())->toBe(1);
});

test('a distinct expiry notice is sent the day a subscription lapses, and its status is flipped for reporting', function () {
    $owner = onboard(['subscription_ends_at' => now()->subDay()]);

    $this->artisan('subscriptions:process-renewals')->assertSuccessful();

    $notifications = renewalNotifications($owner->tenant_id);
    expect($notifications)->toHaveCount(1);
    expect($notifications->first()->body)->toContain('expired');

    $subscription = TenantSubscription::where('tenant_id', $owner->tenant_id)->firstOrFail();
    expect($subscription->status)->toBe('expired');
});

test('an already-pending quotation is not duplicated by the expiry safety net', function () {
    $owner = onboard(['subscription_ends_at' => now()->subDay()]);

    Quotation::create([
        'tenant_id' => $owner->tenant_id,
        'subscription_plan_id' => SubscriptionPlan::where('code', 'growth')->firstOrFail()->id,
        'quotation_number' => 'QUO/TEST/EXISTING',
        'amount' => 999,
        'status' => 'pending',
    ]);

    $this->artisan('subscriptions:process-renewals')->assertSuccessful();

    expect(Quotation::where('tenant_id', $owner->tenant_id)->where('status', 'pending')->count())->toBe(1);
});
