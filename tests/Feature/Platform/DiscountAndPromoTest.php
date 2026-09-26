<?php

use App\Domain\Platform\Actions\PayQuotation;
use App\Domain\Platform\Models\Module;
use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Platform\Models\PlatformAuditLog;
use App\Domain\Platform\Models\PlatformInvoice;
use App\Domain\Platform\Models\Quotation;
use App\Domain\Platform\Models\SubscriptionPlan;

function promoPlan(array $overrides = []): SubscriptionPlan
{
    Module::firstOrCreate(['code' => 'salon'], ['name' => 'Salon']);

    $plan = SubscriptionPlan::create(array_merge([
        'code' => 'promo-'.uniqid(), 'name' => 'Promo Plan', 'price' => 1000, 'billing_interval' => 'monthly',
        'branch_limit' => 1, 'is_active' => true,
    ], $overrides));
    $plan->modules()->sync(Module::where('code', 'salon')->pluck('id'));

    return $plan;
}

function newTenantPayload(SubscriptionPlan $plan, array $extra = []): array
{
    return array_merge([
        'business_name' => 'Glow Salon', 'subscription_plan_id' => $plan->id,
        'owner_name' => 'Alice', 'owner_email' => 'alice@glow.test',
        'owner_password' => 'password123', 'owner_password_confirmation' => 'password123',
        'billing_state' => 'Haryana',
    ], $extra);
}

// ------------------------------------------------ promo price on the plan

test('a plan with a regular price shows it struck through with the discount percentage', function () {
    $plan = promoPlan(['name' => 'Salon Offer', 'price' => 999, 'compare_at_price' => 1999]);

    expect($plan->hasPromo())->toBeTrue();
    expect($plan->promoPercent())->toBe(50);

    $signup = $this->get('/register')->assertOk();
    $signup->assertSee('line-through', false)->assertSee('1,999')->assertSee('50% OFF');

    $owner = onboard();
    $this->actingAs($owner, 'web')->get('/billing/plans')->assertOk()->assertSee('line-through', false)->assertSee('1,999')->assertSee('50% OFF');

    $admin = PlatformAdmin::factory()->create();
    $this->actingAs($admin, 'platform')->get('/platform/subscription-plans')->assertOk()->assertSee('line-through', false)->assertSee('50% OFF');
});

test('the offer price is what is actually charged', function () {
    $plan = promoPlan(['price' => 999, 'compare_at_price' => 1999]);

    $this->post('/register', newTenantPayload($plan))->assertRedirect(route('login'));

    expect((float) Quotation::firstOrFail()->amount)->toBe(999.0);
});

test('a plan with no regular price, or one that is not higher, shows no promo', function () {
    $plain = promoPlan(['name' => 'Plain Plan', 'price' => 1500]);
    $same = promoPlan(['name' => 'Same Plan', 'price' => 1500, 'compare_at_price' => 1500]);

    expect($plain->hasPromo())->toBeFalse();
    expect($same->hasPromo())->toBeFalse();
    expect($plain->promoPercent())->toBe(0);

    $this->get('/register')->assertOk()->assertDontSee('OFF');
});

test('super admin sets the regular price on a plan, and it must be higher than the price', function () {
    $admin = PlatformAdmin::factory()->create();
    $plan = promoPlan(['price' => 999]);
    $base = ['name' => $plan->name, 'price' => 999, 'billing_interval' => 'monthly', 'branch_limit' => 1, 'additional_branch_price' => 0, 'is_active' => '1', 'features' => [], 'modules' => ['salon']];

    $this->actingAs($admin, 'platform')->get("/platform/subscription-plans/{$plan->id}/edit")->assertOk()->assertSee('Regular price');

    $this->actingAs($admin, 'platform')->put("/platform/subscription-plans/{$plan->id}", $base + ['compare_at_price' => 1999])->assertRedirect();
    expect((float) $plan->fresh()->compare_at_price)->toBe(1999.0);

    $this->actingAs($admin, 'platform')->put("/platform/subscription-plans/{$plan->id}", $base + ['compare_at_price' => 500])->assertSessionHasErrors('compare_at_price');

    $this->actingAs($admin, 'platform')->put("/platform/subscription-plans/{$plan->id}", $base + ['compare_at_price' => ''])->assertRedirect();
    expect($plan->fresh()->compare_at_price)->toBeNull();
});

// ------------------------------------------------ Super Admin discount

test('super admin can give a percentage discount when creating a tenant, and it flows through quotation, invoice and PDF', function () {
    $admin = PlatformAdmin::factory()->create();
    $plan = promoPlan(['price' => 1000]);

    $this->actingAs($admin, 'platform')->post('/platform/tenants', newTenantPayload($plan, ['discount_percent' => 25]))->assertRedirect();

    $quotation = Quotation::firstOrFail();
    expect((float) $quotation->discount_percent)->toBe(25.0);
    expect((float) $quotation->discount_amount)->toBe(250.0);
    expect((float) $quotation->amount)->toBe(750.0);                       // discounted subtotal
    expect((float) $quotation->total_amount)->toBe(round(750 * 1.18, 2)); // GST on the discounted amount

    $audit = PlatformAuditLog::where('action', 'quotation.created')->firstOrFail();
    expect((float) $audit->meta['discount_percent'])->toBe(25.0);

    $this->actingAs($admin, 'platform')->get(route('platform.quotations.show', $quotation))->assertOk()->assertSee('Discount (25%)')->assertSee('1,000.00');

    $invoice = app(PayQuotation::class)->execute($quotation, 'upi', 'UTR1');
    expect((float) $invoice->fresh()->discount_amount)->toBe(250.0);
    expect((float) $invoice->fresh()->subtotal)->toBe(750.0);

    $this->actingAs($admin, 'platform')->get(route('platform.invoices.show', $invoice))->assertOk()->assertSee('Discount (25%)');
    $pdf = $this->actingAs($admin, 'platform')->get(route('platform.invoices.pdf', $invoice));
    $pdf->assertOk();
    expect(substr($pdf->getContent(), 0, 5))->toBe('%PDF-');
});

test('the tenant sees the discount on their quotation and invoice', function () {
    $admin = PlatformAdmin::factory()->create();
    $plan = promoPlan(['price' => 1000]);
    $this->actingAs($admin, 'platform')->post('/platform/tenants', newTenantPayload($plan, ['discount_percent' => 10]));

    $quotation = Quotation::firstOrFail();
    $owner = App\Models\User::withoutGlobalScopes()->where('email', 'alice@glow.test')->firstOrFail();

    $this->actingAs($owner, 'web')->get(route('billing.quotations.show', $quotation))->assertOk()->assertSee('Discount (10%)');

    $invoice = app(PayQuotation::class)->execute($quotation, 'upi', 'UTR1');
    $this->actingAs($owner, 'web')->get(route('billing.invoices.show', $invoice))->assertOk()->assertSee('Discount (10%)');
});

test('super admin can discount a manual quotation, and the discount applies to the branch-inclusive price', function () {
    $admin = PlatformAdmin::factory()->create();
    $plan = promoPlan(['price' => 1000, 'additional_branch_price' => 500, 'max_branches' => 5]);
    $owner = onboard();

    $this->actingAs($admin, 'platform')->post('/platform/quotations', [
        'tenant_id' => $owner->tenant_id, 'subscription_plan_id' => $plan->id, 'branch_count' => 3, 'discount_percent' => 20,
    ])->assertRedirect();

    $quotation = Quotation::where('tenant_id', $owner->tenant_id)->firstOrFail();
    expect((float) $quotation->discount_amount)->toBe(400.0);   // 20% of (1000 + 2 x 500)
    expect((float) $quotation->amount)->toBe(1600.0);
    expect($quotation->branch_count)->toBe(3);
});

test('a discount and a custom amount cannot be combined, and the percentage must be 0 to 100', function () {
    $admin = PlatformAdmin::factory()->create();
    $plan = promoPlan();
    $owner = onboard();

    $this->actingAs($admin, 'platform')->post('/platform/quotations', ['tenant_id' => $owner->tenant_id, 'subscription_plan_id' => $plan->id, 'amount' => 500, 'discount_percent' => 10])
        ->assertSessionHasErrors('discount_percent');
    $this->actingAs($admin, 'platform')->post('/platform/quotations', ['tenant_id' => $owner->tenant_id, 'subscription_plan_id' => $plan->id, 'discount_percent' => 101])
        ->assertSessionHasErrors('discount_percent');
    $this->actingAs($admin, 'platform')->post('/platform/quotations', ['tenant_id' => $owner->tenant_id, 'subscription_plan_id' => $plan->id, 'discount_percent' => -5])
        ->assertSessionHasErrors('discount_percent');

    expect(Quotation::where('tenant_id', $owner->tenant_id)->count())->toBe(0);
});

test('a tenant who registers through the public link can never get a discount, whatever they send', function () {
    $plan = promoPlan(['price' => 1000]);

    $this->post('/register', newTenantPayload($plan, ['discount_percent' => 50, 'discount_amount' => 500, 'amount' => 1]))->assertRedirect(route('login'));

    $quotation = Quotation::firstOrFail();
    expect((float) $quotation->amount)->toBe(1000.0);
    expect((float) $quotation->discount_percent)->toBe(0.0);
    expect((float) $quotation->discount_amount)->toBe(0.0);
});

test('a tenant cannot give themselves a discount on upgrades or extra branches, and only Super Admin can reach the discount forms', function () {
    $owner = onboard();
    $plan = promoPlan();

    // Tenant-facing routes have no discount input; a tenant cannot reach the Platform ones.
    $this->actingAs($owner, 'web')->post('/platform/quotations', ['tenant_id' => $owner->tenant_id, 'subscription_plan_id' => $plan->id, 'discount_percent' => 100])
        ->assertRedirect(); // bounced to the platform login
    expect(Quotation::where('tenant_id', $owner->tenant_id)->count())->toBe(0);

    $pro = SubscriptionPlan::where('code', 'pro')->firstOrFail();
    $this->actingAs($owner, 'web')->post(route('billing.plans.upgrade', $pro), ['discount_percent' => 100]);
    $upgrade = Quotation::where('tenant_id', $owner->tenant_id)->first();
    expect((float) ($upgrade?->discount_percent ?? 0))->toBe(0.0);
});

test('a quotation with no discount shows no discount lines', function () {
    $admin = PlatformAdmin::factory()->create();
    $plan = promoPlan();
    $owner = onboard();

    $this->actingAs($admin, 'platform')->post('/platform/quotations', ['tenant_id' => $owner->tenant_id, 'subscription_plan_id' => $plan->id]);
    $quotation = Quotation::where('tenant_id', $owner->tenant_id)->firstOrFail();

    $this->actingAs($admin, 'platform')->get(route('platform.quotations.show', $quotation))->assertOk()->assertDontSee('Discount (');
});
