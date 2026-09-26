<?php

use App\Domain\Platform\Actions\CreateQuotation;
use App\Domain\Platform\Actions\PayQuotation;
use App\Domain\Platform\Models\SubscriptionPlan;
use App\Domain\Platform\Models\Tenant;
use App\Domain\Platform\Models\TenantSubscription;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

function renew(App\Models\User $owner, string $code = 'growth'): TenantSubscription
{
    $tenant = Tenant::findOrFail($owner->tenant_id);
    $quotation = app(CreateQuotation::class)->execute($tenant, SubscriptionPlan::where('code', $code)->firstOrFail(), createdBy: null);
    app(PayQuotation::class)->execute($quotation, 'upi', 'UTR-'.uniqid());

    return TenantSubscription::where('tenant_id', $owner->tenant_id)->firstOrFail();
}

function insertSession(App\Models\User $owner, string $id): void
{
    DB::table('sessions')->insert([
        'id' => $id, 'user_id' => $owner->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'test',
        'payload' => base64_encode('x'), 'last_activity' => now()->timestamp,
    ]);
}

// ------------------------------------------------ early renewal keeps the remaining days

test('renewing before the period ends continues from the old end date, so no days are lost', function () {
    $oldEnd = now()->addDays(3)->startOfDay();
    $owner = onboard(['subscription_plan_code' => 'growth', 'subscription_ends_at' => $oldEnd]);

    $subscription = renew($owner);

    expect($subscription->ends_at->toDateTimeString())->toBe($oldEnd->copy()->addMonth()->toDateTimeString());
    expect($subscription->starts_at->toDateTimeString())->toBe($oldEnd->toDateTimeString());
    expect($subscription->status)->toBe('active');
    expect(TenantSubscription::where('tenant_id', $owner->tenant_id)->count())->toBe(1);
});

test('renewing after the period has lapsed starts a fresh period from today', function () {
    $owner = onboard(['subscription_plan_code' => 'growth', 'subscription_ends_at' => now()->subDays(2)->startOfDay()]);

    $subscription = renew($owner);

    expect($subscription->starts_at->isToday())->toBeTrue();
    expect($subscription->ends_at->toDateString())->toBe(now()->addMonth()->toDateString());
});

test('a yearly plan renewed early gets a full extra year on top of what was left', function () {
    $yearly = SubscriptionPlan::create(['code' => 'yr-'.uniqid(), 'name' => 'Yearly', 'price' => 9999, 'billing_interval' => 'yearly', 'branch_limit' => 1, 'is_active' => true]);
    $oldEnd = now()->addDays(10)->startOfDay();
    $owner = onboard(['subscription_plan_code' => $yearly->code, 'subscription_ends_at' => $oldEnd]);

    $subscription = renew($owner, $yearly->code);

    expect($subscription->ends_at->toDateTimeString())->toBe($oldEnd->copy()->addYear()->toDateTimeString());
});

test('the tenant stays fully active through an early renewal and its access state reflects the new end date', function () {
    $owner = onboard(['subscription_plan_code' => 'growth', 'subscription_ends_at' => now()->addDays(2)->startOfDay()]);

    renew($owner);

    $this->actingAs($owner, 'web')->get('/dashboard')->assertOk();
    $state = app(App\Domain\Platform\Support\ResolveSubscriptionAccessState::class)->execute(Tenant::findOrFail($owner->tenant_id));
    expect($state->level)->toBe('active');
    expect($state->reminderDaysLeft)->toBeNull(); // the renewal reminder window has moved a month on
});

test('an upgrade after an early renewal is never charged for more than one cycle', function () {
    $owner = onboard(['subscription_plan_code' => 'growth', 'subscription_ends_at' => now()->addDays(3)->startOfDay()]);
    $subscription = renew($owner); // cycle now starts in the future

    $pro = SubscriptionPlan::where('code', 'pro')->firstOrFail();
    $amount = (float) app(App\Domain\Platform\Actions\CalculateProration::class)->execute($subscription->fresh(), $pro);

    expect($amount)->toBeLessThanOrEqual((float) $pro->price - (float) SubscriptionPlan::where('code', 'growth')->firstOrFail()->price);
    expect($amount)->toBeGreaterThan(0);
});

test('a plan upgrade still keeps the renewal date (unchanged behaviour)', function () {
    $endsAt = now()->addDays(20)->startOfDay();
    $owner = onboard(['subscription_plan_code' => 'growth', 'subscription_ends_at' => $endsAt]);
    $pro = SubscriptionPlan::where('code', 'pro')->firstOrFail();

    $this->actingAs($owner, 'web')->post(route('billing.plans.upgrade', $pro));
    $quotation = App\Domain\Platform\Models\Quotation::where('tenant_id', $owner->tenant_id)->where('status', 'pending')->firstOrFail();
    app(PayQuotation::class)->execute($quotation, 'upi', 'UTR-UP');

    expect(TenantSubscription::where('tenant_id', $owner->tenant_id)->where('status', 'active')->firstOrFail()->ends_at->toDateTimeString())->toBe($endsAt->toDateTimeString());
});

// ------------------------------------------------ forced logout catches up

test('a missed cron day does not skip the forced logout — it happens on the next run', function () {
    Cache::flush();
    foreach ([9, 12, 40] as $daysAgo) {
        $owner = onboard(['subscription_ends_at' => now()->subDays($daysAgo)]);
        insertSession($owner, "sess-late-{$daysAgo}");

        $this->artisan('subscriptions:process-renewals')->assertSuccessful();

        expect(DB::table('sessions')->where('user_id', $owner->id)->exists())->toBeFalse("{$daysAgo} days past expiry");
    }
});

test('the forced logout happens once per expiry — a tenant who logs back in to pay is not kicked out again', function () {
    Cache::flush();
    $owner = onboard(['subscription_ends_at' => now()->subDays(10)]);
    insertSession($owner, 'sess-first');

    $this->artisan('subscriptions:process-renewals')->assertSuccessful();
    expect(DB::table('sessions')->where('user_id', $owner->id)->exists())->toBeFalse();

    // They log in again to reach the payment page...
    insertSession($owner, 'sess-second');
    $this->artisan('subscriptions:process-renewals')->assertSuccessful();

    expect(DB::table('sessions')->where('user_id', $owner->id)->exists())->toBeTrue();
});

test('the logout notice is sent once, not on every later cron run', function () {
    Cache::flush();
    $owner = onboard(['subscription_ends_at' => now()->subDays(11)]);

    $this->artisan('subscriptions:process-renewals')->assertSuccessful();
    $this->artisan('subscriptions:process-renewals')->assertSuccessful();

    $count = App\Domain\Core\Models\NotificationLog::withoutGlobalScope(App\Domain\Core\Scopes\TenantScope::class)
        ->where('tenant_id', $owner->tenant_id)->where('channel', 'in_app')->where('subject', 'Subscription renewal')->count();
    expect($count)->toBe(1);
});

test('tenants still inside their grace period are not logged out', function () {
    Cache::flush();
    $owner = onboard(['subscription_ends_at' => now()->subDays(5)]);
    insertSession($owner, 'sess-grace');

    $this->artisan('subscriptions:process-renewals')->assertSuccessful();

    expect(DB::table('sessions')->where('user_id', $owner->id)->exists())->toBeTrue();
});
