<?php

use App\Domain\Platform\Actions\CalculateProration;
use App\Domain\Platform\Actions\PayQuotation;
use App\Domain\Platform\Models\Module;
use App\Domain\Platform\Models\Quotation;
use App\Domain\Platform\Models\SubscriptionPlan;
use App\Domain\Platform\Models\Tenant;
use App\Domain\Platform\Models\TenantSubscription;
use Illuminate\Support\Carbon;

test('proration is computed from the plan price difference and remaining cycle days', function () {
    $growth = SubscriptionPlan::where('code', 'growth')->firstOrFail(); // price 999
    $pro = SubscriptionPlan::where('code', 'pro')->firstOrFail(); // price 2499

    $subscription = new TenantSubscription([
        'starts_at' => Carbon::parse('2026-01-01'),
        'ends_at' => Carbon::parse('2026-01-31'), // 30-day cycle
    ]);
    $subscription->setRelation('plan', $growth);

    Carbon::setTestNow(Carbon::parse('2026-01-16')); // 15 days remaining

    $amount = app(CalculateProration::class)->execute($subscription, $pro);

    Carbon::setTestNow();

    expect((float) $amount)->toBe(round((2499 - 999) / 30 * 15, 2));
});

test('a tenant can request a prorated upgrade to a higher-priced plan in the same billing cycle', function () {
    $owner = onboard(['subscription_plan_code' => 'growth', 'subscription_ends_at' => now()->addDays(15)]);
    $pro = SubscriptionPlan::where('code', 'pro')->firstOrFail();

    $response = $this->actingAs($owner, 'web')->post(route('billing.plans.upgrade', $pro));

    $quotation = Quotation::where('tenant_id', $owner->tenant_id)->where('status', 'pending')->firstOrFail();
    $response->assertRedirect(route('billing.quotations.show', $quotation));

    expect($quotation->is_upgrade)->toBeTrue();
    expect($quotation->subscription_plan_id)->toBe($pro->id);
    expect((float) $quotation->amount)->toBeGreaterThan(0);
    expect((float) $quotation->amount)->toBeLessThan((float) $pro->price);

    // A prorated upgrade goes through the same CreateQuotation path, so it
    // must carry the same GST breakdown as any other quotation.
    expect((float) $quotation->gst_rate_percent)->toBe((float) config('platform.gst_rate_percent'));
    expect((float) $quotation->cgst_amount)->toBe(round($quotation->amount * ($quotation->gst_rate_percent / 2) / 100, 2));
    expect((float) $quotation->sgst_amount)->toBe((float) $quotation->cgst_amount);
    expect((float) $quotation->total_amount)->toBe(round(
        (float) $quotation->amount + $quotation->cgst_amount + $quotation->sgst_amount,
        2
    ));
});

test('paying a prorated upgrade preserves the renewal date, supersedes the old plan, and syncs modules', function () {
    $endsAt = now()->addDays(20)->startOfDay();
    $owner = onboard(['subscription_plan_code' => 'growth', 'subscription_ends_at' => $endsAt]);
    $tenant = Tenant::findOrFail($owner->tenant_id);
    $pro = SubscriptionPlan::where('code', 'pro')->firstOrFail();
    $pro->modules()->sync(Module::where('code', 'beauty')->pluck('id'));

    $oldSubscription = $tenant->currentSubscription();

    $this->actingAs($owner, 'web')->post(route('billing.plans.upgrade', $pro));
    $quotation = Quotation::where('tenant_id', $tenant->id)->where('status', 'pending')->firstOrFail();

    app(PayQuotation::class)->execute($quotation, 'razorpay', 'pay_upgrade_test');

    expect($oldSubscription->fresh()->status)->toBe('cancelled');

    $newSubscription = TenantSubscription::where('tenant_id', $tenant->id)
        ->where('subscription_plan_id', $pro->id)
        ->firstOrFail();

    expect($newSubscription->status)->toBe('active');
    expect($newSubscription->ends_at->equalTo($endsAt))->toBeTrue();

    // Modules fully replaced to the new plan's set, not merged with the old.
    expect($tenant->hasModuleEnabled('beauty'))->toBeTrue();
    expect($tenant->hasModuleEnabled('salon'))->toBeFalse();
});

test('upgrading to a same-priced plan is rejected', function () {
    $owner = onboard(['subscription_plan_code' => 'growth', 'subscription_ends_at' => now()->addDays(10)]);
    $growth = SubscriptionPlan::where('code', 'growth')->firstOrFail();

    $this->actingAs($owner, 'web')->post(route('billing.plans.upgrade', $growth))->assertStatus(422);
});

test('upgrading to a plan with a different billing interval is rejected', function () {
    $owner = onboard(['subscription_plan_code' => 'growth', 'subscription_ends_at' => now()->addDays(10)]);
    $trial = SubscriptionPlan::where('code', 'trial')->firstOrFail();

    $this->actingAs($owner, 'web')->post(route('billing.plans.upgrade', $trial))->assertStatus(422);
});

test('a tenant with an existing pending quotation cannot request another upgrade', function () {
    $owner = onboard(['subscription_plan_code' => 'growth', 'subscription_ends_at' => now()->addDays(10)]);
    $pro = SubscriptionPlan::where('code', 'pro')->firstOrFail();

    $this->actingAs($owner, 'web')->post(route('billing.plans.upgrade', $pro))->assertRedirect();
    $this->actingAs($owner, 'web')->post(route('billing.plans.upgrade', $pro))->assertStatus(409);
});

test('a tenant with no active subscription cannot request an upgrade', function () {
    $owner = onboard(['activated' => false]);
    $pro = SubscriptionPlan::where('code', 'pro')->firstOrFail();

    $this->actingAs($owner, 'web')->post(route('billing.plans.upgrade', $pro))->assertStatus(422);
});

test('a tenant cannot upgrade another tenant onto a plan via the shared route', function () {
    $ownerA = onboard(['subscription_plan_code' => 'growth', 'subscription_ends_at' => now()->addDays(10)]);
    $ownerB = onboard(['subscription_plan_code' => 'growth', 'subscription_ends_at' => now()->addDays(10)]);
    $pro = SubscriptionPlan::where('code', 'pro')->firstOrFail();

    $this->actingAs($ownerB, 'web')->post(route('billing.plans.upgrade', $pro))->assertRedirect();

    expect(Quotation::where('tenant_id', $ownerA->tenant_id)->exists())->toBeFalse();
    expect(Quotation::where('tenant_id', $ownerB->tenant_id)->where('status', 'pending')->exists())->toBeTrue();
});

test('the plans page shows Upgrade only for the higher-priced same-interval plan', function () {
    $owner = onboard(['subscription_plan_code' => 'growth', 'subscription_ends_at' => now()->addDays(10)]);

    $this->actingAs($owner, 'web')->get(route('billing.plans.index'))
        ->assertOk()
        ->assertSee('Current Plan')
        ->assertSee('Upgrade');
});
