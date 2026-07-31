<?php

use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Platform\Models\PlatformInvoice;
use App\Domain\Platform\Models\Quotation;
use App\Domain\Platform\Models\SubscriptionPlan;
use App\Domain\Platform\Models\TenantSubscription;

function paidInvoiceFor(int $tenantId, SubscriptionPlan $plan, ?DateTimeInterface $paidAt = null): PlatformInvoice
{
    $quotation = Quotation::create([
        'tenant_id' => $tenantId,
        'subscription_plan_id' => $plan->id,
        'quotation_number' => 'QUO/TEST/'.uniqid(),
        'amount' => $plan->price,
        'status' => 'paid',
        'paid_at' => $paidAt ?? now(),
    ]);

    return PlatformInvoice::create([
        'tenant_id' => $tenantId,
        'quotation_id' => $quotation->id,
        'subscription_plan_id' => $plan->id,
        'invoice_number' => 'PINV/TEST/'.uniqid(),
        'amount' => $plan->price,
        'payment_method' => 'razorpay',
        'payment_reference' => 'pay_test_'.uniqid(),
        'paid_at' => $paidAt ?? now(),
    ]);
}

test('an unauthenticated guest cannot view platform accounting', function () {
    $this->get('/platform/accounting')->assertRedirect('/platform/login');
});

test('a tenant user cannot view platform accounting', function () {
    $owner = onboard();

    $this->actingAs($owner, 'web')->get('/platform/accounting')->assertRedirect('/platform/login');
});

test('platform accounting shows current-month invoicing, total generated, and previous months', function () {
    $admin = PlatformAdmin::factory()->create();
    $owner = onboard();
    $plan = SubscriptionPlan::where('code', 'growth')->firstOrFail();

    $thisMonthInvoice = paidInvoiceFor($owner->tenant_id, $plan);
    paidInvoiceFor($owner->tenant_id, $plan, now()->subMonthNoOverflow());

    $response = $this->actingAs($admin, 'platform')->get('/platform/accounting');

    $response->assertOk()
        ->assertSee($thisMonthInvoice->invoice_number)
        ->assertSee('₹'.number_format((float) $plan->price * 2, 2), false);
});

test('the next-month projection counts a tenant only once even with multiple active subscription rows', function () {
    $admin = PlatformAdmin::factory()->create();
    $owner = onboard();
    $pro = SubscriptionPlan::where('code', 'pro')->firstOrFail();
    $growth = SubscriptionPlan::where('code', 'growth')->firstOrFail();

    // Stale row: an earlier active subscription for a different plan that
    // was never explicitly superseded (PayQuotation only inserts new rows).
    TenantSubscription::create([
        'tenant_id' => $owner->tenant_id,
        'subscription_plan_id' => $pro->id,
        'status' => 'active',
        'starts_at' => now()->subMonths(2),
        'ends_at' => now()->addMonthNoOverflow(),
    ]);

    // The real, most-recently-activated subscription.
    TenantSubscription::create([
        'tenant_id' => $owner->tenant_id,
        'subscription_plan_id' => $growth->id,
        'status' => 'active',
        'starts_at' => now(),
        'ends_at' => now()->addMonthNoOverflow(),
    ]);

    $response = $this->actingAs($admin, 'platform')->get('/platform/accounting');

    // The next-month projection is tax-inclusive (see PlatformAccountingController).
    $gstMultiplier = 1 + config('platform.gst_rate_percent') / 100;

    $response->assertOk()
        ->assertSee('₹'.number_format((float) $growth->price * $gstMultiplier, 2), false)
        ->assertDontSee('₹'.number_format((float) ($growth->price + $pro->price) * $gstMultiplier, 2), false);
});
