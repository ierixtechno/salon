<?php

use App\Domain\Platform\Contracts\PaymentGatewayProvider;
use App\Domain\Platform\Models\Module;
use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Platform\Models\PlatformInvoice;
use App\Domain\Platform\Models\Quotation;
use App\Domain\Platform\Models\SubscriptionPlan;
use App\Domain\Platform\Models\TenantModule;
use App\Domain\Platform\Models\TenantSubscription;

/**
 * A fake gateway that always reports a verified payment, so these tests
 * exercise the full checkout->confirm->PayQuotation flow without hitting
 * the real Razorpay API.
 */
class FakeVerifiedPaymentGatewayProvider implements PaymentGatewayProvider
{
    public function createOrder(string $receiptId, string $amount, string $currency = 'INR'): array
    {
        return ['order_id' => 'order_fake_'.$receiptId, 'key' => 'rzp_test_fake'];
    }

    public function verifyPayment(string $orderId, string $paymentId, string $signature): bool
    {
        return true;
    }
}

class FakeFailingPaymentGatewayProvider implements PaymentGatewayProvider
{
    public function createOrder(string $receiptId, string $amount, string $currency = 'INR'): array
    {
        return ['order_id' => 'order_fake_'.$receiptId, 'key' => 'rzp_test_fake'];
    }

    public function verifyPayment(string $orderId, string $paymentId, string $signature): bool
    {
        return false;
    }
}

function growthPlanWithModules(): SubscriptionPlan
{
    $plan = SubscriptionPlan::where('code', 'growth')->firstOrFail();
    $plan->modules()->sync(Module::whereIn('code', ['salon', 'beauty'])->pluck('id'));

    return $plan;
}

test('a platform admin can create a quotation for a tenant', function () {
    $admin = PlatformAdmin::factory()->create();
    $owner = onboard(['modules' => ['salon']]);
    $plan = growthPlanWithModules();

    $this->actingAs($admin, 'platform')->post('/platform/quotations', [
        'tenant_id' => $owner->tenant_id,
        'subscription_plan_id' => $plan->id,
        'notes' => 'Upgrade to Growth',
    ])->assertRedirect();

    $quotation = Quotation::where('tenant_id', $owner->tenant_id)->firstOrFail();
    expect($quotation->status)->toBe('pending');
    expect((float) $quotation->amount)->toBe((float) $plan->price);
    expect($quotation->quotation_number)->toStartWith('QUO/');
});

test('a platform admin can override the quotation amount', function () {
    $admin = PlatformAdmin::factory()->create();
    $owner = onboard();
    $plan = growthPlanWithModules();

    $this->actingAs($admin, 'platform')->post('/platform/quotations', [
        'tenant_id' => $owner->tenant_id,
        'subscription_plan_id' => $plan->id,
        'amount' => 499,
    ])->assertRedirect();

    $quotation = Quotation::where('tenant_id', $owner->tenant_id)->firstOrFail();
    expect((float) $quotation->amount)->toBe(499.0);
});

test('a tenant admin sees a quotation created for them', function () {
    $admin = PlatformAdmin::factory()->create();
    $owner = onboard();
    $plan = growthPlanWithModules();

    $this->actingAs($admin, 'platform')->post('/platform/quotations', [
        'tenant_id' => $owner->tenant_id,
        'subscription_plan_id' => $plan->id,
    ]);
    $quotation = Quotation::where('tenant_id', $owner->tenant_id)->firstOrFail();

    $this->actingAs($owner, 'web')->get('/billing/quotations')
        ->assertOk()
        ->assertSee($quotation->quotation_number);

    $this->actingAs($owner, 'web')->get("/billing/quotations/{$quotation->id}")
        ->assertOk()
        ->assertSee('Pay Now');
});

test('paying a quotation creates an invoice, syncs tenant modules, and activates the subscription', function () {
    $this->app->bind(PaymentGatewayProvider::class, FakeVerifiedPaymentGatewayProvider::class);

    $admin = PlatformAdmin::factory()->create();
    $owner = onboard(['modules' => ['salon']]);
    $plan = growthPlanWithModules(); // bundles salon + beauty

    $this->actingAs($admin, 'platform')->post('/platform/quotations', [
        'tenant_id' => $owner->tenant_id,
        'subscription_plan_id' => $plan->id,
    ]);
    $quotation = Quotation::where('tenant_id', $owner->tenant_id)->firstOrFail();

    $this->actingAs($owner, 'web')->post("/billing/quotations/{$quotation->id}/checkout")
        ->assertOk()
        ->assertJsonStructure(['order_id', 'key', 'amount']);

    $quotation->refresh();
    expect($quotation->razorpay_order_id)->not->toBeNull();

    $this->actingAs($owner, 'web')->post("/billing/quotations/{$quotation->id}/confirm", [
        'razorpay_order_id' => $quotation->razorpay_order_id,
        'razorpay_payment_id' => 'pay_fake_123',
        'razorpay_signature' => 'sig_fake',
    ])->assertRedirect();

    $quotation->refresh();
    expect($quotation->status)->toBe('paid');
    expect($quotation->paid_at)->not->toBeNull();

    $invoice = PlatformInvoice::where('quotation_id', $quotation->id)->first();
    expect($invoice)->not->toBeNull();
    expect($invoice->invoice_number)->toStartWith('PINV/');
    expect((float) $invoice->amount)->toBe((float) $quotation->amount);

    $enabledCodes = TenantModule::where('tenant_id', $owner->tenant_id)
        ->where('enabled', true)
        ->with('module')
        ->get()
        ->pluck('module.code')
        ->all();
    expect($enabledCodes)->toEqualCanonicalizing(['salon', 'beauty']);

    $subscription = TenantSubscription::where('tenant_id', $owner->tenant_id)
        ->where('subscription_plan_id', $plan->id)
        ->first();
    expect($subscription)->not->toBeNull();
    expect($subscription->status)->toBe('active');

    $this->actingAs($owner, 'web')->get('/billing/invoices')
        ->assertOk()
        ->assertSee($invoice->invoice_number);
});

test('a second payment attempt on an already-paid quotation is rejected', function () {
    $this->app->bind(PaymentGatewayProvider::class, FakeVerifiedPaymentGatewayProvider::class);

    $admin = PlatformAdmin::factory()->create();
    $owner = onboard();
    $plan = growthPlanWithModules();

    $this->actingAs($admin, 'platform')->post('/platform/quotations', [
        'tenant_id' => $owner->tenant_id,
        'subscription_plan_id' => $plan->id,
    ]);
    $quotation = Quotation::where('tenant_id', $owner->tenant_id)->firstOrFail();

    $this->actingAs($owner, 'web')->post("/billing/quotations/{$quotation->id}/checkout");
    $quotation->refresh();

    $this->actingAs($owner, 'web')->post("/billing/quotations/{$quotation->id}/confirm", [
        'razorpay_order_id' => $quotation->razorpay_order_id,
        'razorpay_payment_id' => 'pay_fake_123',
        'razorpay_signature' => 'sig_fake',
    ])->assertRedirect();

    expect(PlatformInvoice::where('quotation_id', $quotation->id)->count())->toBe(1);

    // Retry (double-click / webhook replay) must not double-invoice.
    $this->actingAs($owner, 'web')->post("/billing/quotations/{$quotation->id}/checkout")
        ->assertStatus(409);

    expect(PlatformInvoice::where('quotation_id', $quotation->id)->count())->toBe(1);
});

test('an unverified payment signature is rejected and does not create an invoice', function () {
    $this->app->bind(PaymentGatewayProvider::class, FakeFailingPaymentGatewayProvider::class);

    $admin = PlatformAdmin::factory()->create();
    $owner = onboard();
    $plan = growthPlanWithModules();

    $this->actingAs($admin, 'platform')->post('/platform/quotations', [
        'tenant_id' => $owner->tenant_id,
        'subscription_plan_id' => $plan->id,
    ]);
    $quotation = Quotation::where('tenant_id', $owner->tenant_id)->firstOrFail();

    $this->actingAs($owner, 'web')->post("/billing/quotations/{$quotation->id}/checkout");
    $quotation->refresh();

    $this->actingAs($owner, 'web')->post("/billing/quotations/{$quotation->id}/confirm", [
        'razorpay_order_id' => $quotation->razorpay_order_id,
        'razorpay_payment_id' => 'pay_fake_123',
        'razorpay_signature' => 'bad_sig',
    ])->assertStatus(422);

    expect($quotation->fresh()->status)->toBe('pending');
    expect(PlatformInvoice::where('quotation_id', $quotation->id)->exists())->toBeFalse();
});

test('with no payment gateway configured, checkout fails with a business error instead of crashing', function () {
    $admin = PlatformAdmin::factory()->create();
    $owner = onboard();
    $plan = growthPlanWithModules();

    $this->actingAs($admin, 'platform')->post('/platform/quotations', [
        'tenant_id' => $owner->tenant_id,
        'subscription_plan_id' => $plan->id,
    ]);
    $quotation = Quotation::where('tenant_id', $owner->tenant_id)->firstOrFail();

    // Default binding is NullPaymentGatewayProvider (PAYMENTS_DRIVER=null in tests).
    $this->actingAs($owner, 'web')->post("/billing/quotations/{$quotation->id}/checkout")
        ->assertStatus(422)
        ->assertJson(['message' => 'Online payment is not configured yet. Please contact support.']);

    expect($quotation->fresh()->status)->toBe('pending');
});

test('a platform admin can cancel a pending quotation but not a paid one', function () {
    $this->app->bind(PaymentGatewayProvider::class, FakeVerifiedPaymentGatewayProvider::class);

    $admin = PlatformAdmin::factory()->create();
    $owner = onboard();
    $plan = growthPlanWithModules();

    $this->actingAs($admin, 'platform')->post('/platform/quotations', [
        'tenant_id' => $owner->tenant_id,
        'subscription_plan_id' => $plan->id,
    ]);
    $quotation = Quotation::where('tenant_id', $owner->tenant_id)->firstOrFail();

    $this->actingAs($admin, 'platform')->patch("/platform/quotations/{$quotation->id}/cancel")
        ->assertRedirect();
    expect($quotation->fresh()->status)->toBe('cancelled');

    // A second, freshly paid quotation must not be cancellable.
    $this->actingAs($admin, 'platform')->post('/platform/quotations', [
        'tenant_id' => $owner->tenant_id,
        'subscription_plan_id' => $plan->id,
    ]);
    $paid = Quotation::where('tenant_id', $owner->tenant_id)->where('status', 'pending')->firstOrFail();

    $this->actingAs($owner, 'web')->post("/billing/quotations/{$paid->id}/checkout");
    $paid->refresh();
    $this->actingAs($owner, 'web')->post("/billing/quotations/{$paid->id}/confirm", [
        'razorpay_order_id' => $paid->razorpay_order_id,
        'razorpay_payment_id' => 'pay_fake_456',
        'razorpay_signature' => 'sig_fake',
    ]);

    $this->actingAs($admin, 'platform')->patch("/platform/quotations/{$paid->id}/cancel")
        ->assertStatus(409);
    expect($paid->fresh()->status)->toBe('paid');
});

test('a tenant admin cannot view or pay another tenant quotation or invoice', function () {
    $this->app->bind(PaymentGatewayProvider::class, FakeVerifiedPaymentGatewayProvider::class);

    $admin = PlatformAdmin::factory()->create();
    $ownerA = onboard();
    $ownerB = onboard();
    $plan = growthPlanWithModules();

    $this->actingAs($admin, 'platform')->post('/platform/quotations', [
        'tenant_id' => $ownerA->tenant_id,
        'subscription_plan_id' => $plan->id,
    ]);
    $quotation = Quotation::where('tenant_id', $ownerA->tenant_id)->firstOrFail();

    $this->actingAs($ownerB, 'web')->get("/billing/quotations/{$quotation->id}")->assertNotFound();
    $this->actingAs($ownerB, 'web')->post("/billing/quotations/{$quotation->id}/checkout")->assertNotFound();
    $this->actingAs($ownerB, 'web')->post("/billing/quotations/{$quotation->id}/confirm", [
        'razorpay_order_id' => 'x',
        'razorpay_payment_id' => 'y',
        'razorpay_signature' => 'z',
    ])->assertNotFound();

    // Pay quotation A as its rightful owner, then confirm tenant B still can't see the invoice.
    $this->actingAs($ownerA, 'web')->post("/billing/quotations/{$quotation->id}/checkout");
    $quotation->refresh();
    $this->actingAs($ownerA, 'web')->post("/billing/quotations/{$quotation->id}/confirm", [
        'razorpay_order_id' => $quotation->razorpay_order_id,
        'razorpay_payment_id' => 'pay_fake_789',
        'razorpay_signature' => 'sig_fake',
    ]);
    $invoice = PlatformInvoice::where('quotation_id', $quotation->id)->firstOrFail();

    $this->actingAs($ownerB, 'web')->get("/billing/invoices/{$invoice->id}")->assertNotFound();
});

test('an unauthenticated guest cannot access tenant billing routes', function () {
    $owner = onboard();
    $plan = growthPlanWithModules();
    $admin = PlatformAdmin::factory()->create();

    $this->actingAs($admin, 'platform')->post('/platform/quotations', [
        'tenant_id' => $owner->tenant_id,
        'subscription_plan_id' => $plan->id,
    ]);
    $quotation = Quotation::where('tenant_id', $owner->tenant_id)->firstOrFail();

    $this->get('/billing/quotations')->assertRedirect('/login');
    $this->get("/billing/quotations/{$quotation->id}")->assertRedirect('/login');
});
