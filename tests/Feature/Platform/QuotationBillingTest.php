<?php

use App\Domain\Platform\Contracts\PaymentGatewayProvider;
use App\Domain\Platform\Models\Module;
use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Platform\Models\PlatformInvoice;
use App\Domain\Platform\Models\Quotation;
use App\Domain\Platform\Models\SubscriptionPlan;
use App\Domain\Platform\Models\Tenant;
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

test('a created quotation has a correct GST breakdown (CGST+SGST, snapshotted rate)', function () {
    config(['platform.gst_rate_percent' => 18]);

    $admin = PlatformAdmin::factory()->create();
    $owner = onboard(['modules' => ['salon']]);
    $plan = growthPlanWithModules(); // price 999

    $this->actingAs($admin, 'platform')->post('/platform/quotations', [
        'tenant_id' => $owner->tenant_id,
        'subscription_plan_id' => $plan->id,
    ]);

    $quotation = Quotation::where('tenant_id', $owner->tenant_id)->firstOrFail();

    expect((float) $quotation->gst_rate_percent)->toBe(18.0);
    // Hand-computed: 999 base at 18% -> 89.91 CGST + 89.91 SGST -> 1178.82 total.
    expect((float) $quotation->cgst_amount)->toBe(89.91);
    expect((float) $quotation->sgst_amount)->toBe(89.91);
    expect((float) $quotation->total_amount)->toBe(1178.82);
    expect((float) $quotation->total_amount)->toBe(round(
        (float) $quotation->amount + $quotation->cgst_amount + $quotation->sgst_amount,
        2
    ));
});

test('a quotation for a tenant in a different state than the platform is charged IGST instead of CGST+SGST', function () {
    config(['platform.gst_rate_percent' => 18, 'platform.state' => 'Haryana']);

    $admin = PlatformAdmin::factory()->create();
    $owner = onboard(['modules' => ['salon'], 'billing_state' => 'Maharashtra']);
    $plan = growthPlanWithModules(); // price 999

    $this->actingAs($admin, 'platform')->post('/platform/quotations', [
        'tenant_id' => $owner->tenant_id,
        'subscription_plan_id' => $plan->id,
    ])->assertRedirect();

    $quotation = Quotation::where('tenant_id', $owner->tenant_id)->firstOrFail();

    expect((float) $quotation->cgst_amount)->toBe(0.0);
    expect((float) $quotation->sgst_amount)->toBe(0.0);
    // Hand-computed: 999 base at the full 18% as a single IGST line.
    expect((float) $quotation->igst_amount)->toBe(179.82);
    expect((float) $quotation->total_amount)->toBe(1178.82);
});

test('creating a quotation for a tenant with no billing state set is rejected', function () {
    $admin = PlatformAdmin::factory()->create();
    $owner = onboard(['billing_state' => null]);
    $plan = growthPlanWithModules();

    $this->actingAs($admin, 'platform')->post('/platform/quotations', [
        'tenant_id' => $owner->tenant_id,
        'subscription_plan_id' => $plan->id,
    ])->assertStatus(422);

    expect(Quotation::where('tenant_id', $owner->tenant_id)->exists())->toBeFalse();
});

test('a super admin can set a tenant\'s billing state and GSTIN from the tenant detail page', function () {
    $admin = PlatformAdmin::factory()->create();
    $owner = onboard(['billing_state' => null]);

    $this->actingAs($admin, 'platform')
        ->patch("/platform/tenants/{$owner->tenant_id}/billing-state", [
            'billing_state' => 'Punjab',
            'gstin' => '03ABCDE1234F1Z5',
        ])
        ->assertRedirect();

    $tenant = Tenant::findOrFail($owner->tenant_id);
    expect($tenant->billing_state)->toBe('Punjab');
    expect($tenant->gstin)->toBe('03ABCDE1234F1Z5');
});

test('a super admin cannot save a malformed GSTIN for a tenant', function () {
    $admin = PlatformAdmin::factory()->create();
    $owner = onboard();

    $this->actingAs($admin, 'platform')
        ->patch("/platform/tenants/{$owner->tenant_id}/billing-state", ['gstin' => 'not-a-gstin'])
        ->assertSessionHasErrors('gstin');
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

    $checkoutResponse = $this->actingAs($owner, 'web')->post("/billing/quotations/{$quotation->id}/checkout")
        ->assertOk()
        ->assertJsonStructure(['order_id', 'key', 'amount']);

    $quotation->refresh();
    expect($quotation->razorpay_order_id)->not->toBeNull();
    // Razorpay must be charged the tax-inclusive total, not the pre-GST amount.
    expect((float) $checkoutResponse->json('amount'))->toBe((float) $quotation->total_amount);

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
    // Invoice.amount records what was actually collected — the
    // tax-inclusive total, not the pre-GST base amount.
    expect((float) $invoice->amount)->toBe((float) $quotation->total_amount);
    expect((float) $invoice->subtotal)->toBe((float) $quotation->amount);

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

test('a platform admin can record a manual (offline) payment, which activates a pending tenant just like an online payment', function () {
    $owner = onboard(['activated' => false, 'modules' => ['salon']]);
    $admin = PlatformAdmin::factory()->create();
    $plan = growthPlanWithModules(); // bundles salon + beauty

    // Blocked pre-payment — this is the whole reason the manual-payment
    // path is needed (the tenant can't reach the online checkout itself).
    $this->post('/login', ['email' => $owner->email, 'password' => 'password123'])
        ->assertSessionHasErrors('email');
    $this->assertGuest('web');

    $this->actingAs($admin, 'platform')->post('/platform/quotations', [
        'tenant_id' => $owner->tenant_id,
        'subscription_plan_id' => $plan->id,
    ]);
    $quotation = Quotation::where('tenant_id', $owner->tenant_id)->firstOrFail();

    $this->actingAs($admin, 'platform')->post("/platform/quotations/{$quotation->id}/record-payment", [
        'payment_method' => 'bank_transfer',
        'payment_reference' => 'UTR123456789',
    ])->assertRedirect();

    $quotation->refresh();
    expect($quotation->status)->toBe('paid');
    expect($quotation->paid_at)->not->toBeNull();

    $invoice = PlatformInvoice::where('quotation_id', $quotation->id)->first();
    expect($invoice)->not->toBeNull();
    expect($invoice->payment_method)->toBe('bank_transfer');
    expect($invoice->payment_reference)->toBe('UTR123456789');

    $enabledCodes = TenantModule::where('tenant_id', $owner->tenant_id)
        ->where('enabled', true)
        ->with('module')
        ->get()
        ->pluck('module.code')
        ->all();
    expect($enabledCodes)->toEqualCanonicalizing(['salon', 'beauty']);

    expect(Tenant::findOrFail($owner->tenant_id)->status)->toBe('active');

    // The tenant, previously blocked from logging in pre-payment, can now
    // log in — this is the whole point of the manual-payment path.
    $this->post('/login', ['email' => $owner->email, 'password' => 'password123'])
        ->assertRedirect(route('dashboard'));
});

test('a manual payment cannot be recorded twice on the same quotation', function () {
    $admin = PlatformAdmin::factory()->create();
    $owner = onboard();
    $plan = growthPlanWithModules();

    $this->actingAs($admin, 'platform')->post('/platform/quotations', [
        'tenant_id' => $owner->tenant_id,
        'subscription_plan_id' => $plan->id,
    ]);
    $quotation = Quotation::where('tenant_id', $owner->tenant_id)->firstOrFail();

    $this->actingAs($admin, 'platform')->post("/platform/quotations/{$quotation->id}/record-payment", [
        'payment_method' => 'cash',
    ])->assertRedirect();

    $this->actingAs($admin, 'platform')->post("/platform/quotations/{$quotation->id}/record-payment", [
        'payment_method' => 'cash',
    ])->assertStatus(409);

    expect(PlatformInvoice::where('quotation_id', $quotation->id)->count())->toBe(1);
});

test('recording a manual payment requires a valid payment method', function () {
    $admin = PlatformAdmin::factory()->create();
    $owner = onboard();
    $plan = growthPlanWithModules();

    $this->actingAs($admin, 'platform')->post('/platform/quotations', [
        'tenant_id' => $owner->tenant_id,
        'subscription_plan_id' => $plan->id,
    ]);
    $quotation = Quotation::where('tenant_id', $owner->tenant_id)->firstOrFail();

    $this->actingAs($admin, 'platform')->post("/platform/quotations/{$quotation->id}/record-payment", [
        'payment_method' => 'bitcoin',
    ])->assertSessionHasErrors('payment_method');

    expect($quotation->fresh()->status)->toBe('pending');
});

test('a tenant cannot record their own payment manually', function () {
    $admin = PlatformAdmin::factory()->create();
    $owner = onboard();
    $plan = growthPlanWithModules();

    $this->actingAs($admin, 'platform')->post('/platform/quotations', [
        'tenant_id' => $owner->tenant_id,
        'subscription_plan_id' => $plan->id,
    ]);
    $quotation = Quotation::where('tenant_id', $owner->tenant_id)->firstOrFail();

    // Logging into the 'web' guard does not clear a separately-authenticated
    // 'platform' guard session (both are real, independent Laravel guards) —
    // log the admin out explicitly so this exercises a plain tenant user
    // who was never platform-authenticated, not "an admin also logged in
    // as this tenant".
    $this->app['auth']->guard('platform')->logout();

    $this->actingAs($owner, 'web')->post("/platform/quotations/{$quotation->id}/record-payment", [
        'payment_method' => 'cash',
    ])->assertRedirect(route('platform.login'));

    expect($quotation->fresh()->status)->toBe('pending');
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

test('creating a tenant without a plan behaves exactly as before (manual modules, no quotation)', function () {
    $admin = PlatformAdmin::factory()->create();

    $response = $this->actingAs($admin, 'platform')->post('/platform/tenants', [
        'business_name' => 'No Plan Yet Salon',
        'modules' => ['salon'],
        'owner_name' => 'Owner',
        'owner_email' => 'noplan@example.com',
        'owner_password' => 'password123',
        'owner_password_confirmation' => 'password123',
    ]);

    $tenant = Tenant::where('name', 'No Plan Yet Salon')->firstOrFail();
    $response->assertRedirect(route('platform.tenants.show', $tenant->id));
    expect($tenant->status)->toBe('pending_payment');
    expect(Quotation::where('tenant_id', $tenant->id)->exists())->toBeFalse();
});

test('creating a tenant with a plan selected skips the separate quotation step', function () {
    $admin = PlatformAdmin::factory()->create();
    $plan = growthPlanWithModules(); // bundles salon + beauty

    $response = $this->actingAs($admin, 'platform')->post('/platform/tenants', [
        'business_name' => 'One Step Salon',
        'subscription_plan_id' => $plan->id,
        'billing_state' => config('platform.state'),
        'owner_name' => 'Owner',
        'owner_email' => 'onestep@example.com',
        'owner_password' => 'password123',
        'owner_password_confirmation' => 'password123',
    ]);

    $tenant = Tenant::where('name', 'One Step Salon')->firstOrFail();
    $quotation = Quotation::where('tenant_id', $tenant->id)->firstOrFail();

    $response->assertRedirect(route('platform.quotations.show', $quotation));
    expect($quotation->subscription_plan_id)->toBe($plan->id);
    expect((float) $quotation->amount)->toBe((float) $plan->price);
    expect($quotation->status)->toBe('pending');

    // Modules weren't asked for — they're derived from the plan so the
    // tenant record isn't left with an empty module set pre-payment.
    $initialCodes = TenantModule::where('tenant_id', $tenant->id)
        ->with('module')->get()->pluck('module.code')->all();
    expect($initialCodes)->toEqualCanonicalizing(['salon', 'beauty']);

    // The whole point: it's immediately payable from here, no detour
    // through "Quotations > Create" needed.
    $this->actingAs($admin, 'platform')->post("/platform/quotations/{$quotation->id}/record-payment", [
        'payment_method' => 'upi',
    ])->assertRedirect();

    expect(Tenant::findOrFail($tenant->id)->status)->toBe('active');
});

test('creating a tenant with a plan but no billing state is rejected before anything is created', function () {
    $admin = PlatformAdmin::factory()->create();
    $plan = growthPlanWithModules();

    $this->actingAs($admin, 'platform')->post('/platform/tenants', [
        'business_name' => 'Missing State Salon',
        'subscription_plan_id' => $plan->id,
        'owner_name' => 'Owner',
        'owner_email' => 'missingstate@example.com',
        'owner_password' => 'password123',
        'owner_password_confirmation' => 'password123',
    ])->assertSessionHasErrors('billing_state');

    expect(Tenant::where('name', 'Missing State Salon')->exists())->toBeFalse();
});

test('creating a tenant with a plan honours an amount override on the auto-generated quotation', function () {
    $admin = PlatformAdmin::factory()->create();
    $plan = growthPlanWithModules();

    $this->actingAs($admin, 'platform')->post('/platform/tenants', [
        'business_name' => 'Discounted Salon',
        'subscription_plan_id' => $plan->id,
        'billing_state' => config('platform.state'),
        'quotation_amount' => 499,
        'quotation_notes' => 'First-month discount',
        'owner_name' => 'Owner',
        'owner_email' => 'discounted@example.com',
        'owner_password' => 'password123',
        'owner_password_confirmation' => 'password123',
    ])->assertRedirect();

    $tenant = Tenant::where('name', 'Discounted Salon')->firstOrFail();
    $quotation = Quotation::where('tenant_id', $tenant->id)->firstOrFail();

    expect((float) $quotation->amount)->toBe(499.0);
    expect($quotation->notes)->toBe('First-month discount');
});
