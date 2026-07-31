<?php

use App\Domain\Core\Models\Customer;
use App\Domain\Platform\Actions\CreateQuotation;
use App\Domain\Platform\Actions\PayQuotation;
use App\Domain\Platform\Models\SubscriptionPlan;
use App\Domain\Platform\Models\Tenant;

test('a pending (never paid) tenant is redirected to account access from a core route', function () {
    $owner = onboard(['activated' => false]);

    $this->actingAs($owner, 'web')->get('/dashboard')
        ->assertRedirect(route('account.access'));

    $this->actingAs($owner, 'web')->get('/account-access')->assertOk();
});

test('a pending tenant can still reach billing and log out', function () {
    $owner = onboard(['activated' => false]);

    $this->actingAs($owner, 'web')->get('/billing/quotations')->assertOk();

    $response = $this->actingAs($owner, 'web')->post('/logout');
    $response->assertRedirect();
    expect($response->headers->get('Location'))->not->toBe(route('account.access'));
});

test('paying the first quotation activates a pending tenant on the very next request', function () {
    $owner = onboard(['activated' => false]);
    $tenant = Tenant::findOrFail($owner->tenant_id);
    $plan = SubscriptionPlan::where('code', 'growth')->firstOrFail();

    $this->actingAs($owner, 'web')->get('/dashboard')->assertRedirect(route('account.access'));

    $quotation = app(CreateQuotation::class)->execute($tenant, $plan, createdBy: null);
    app(PayQuotation::class)->execute($quotation, 'razorpay', 'pay_test_activation');

    expect($tenant->fresh()->status)->toBe('active');

    // Same test, no re-login — proves the cache was actually busted, not
    // just that a fresh TTL happened to expire.
    $this->actingAs($owner, 'web')->get('/dashboard')->assertOk();
});

test('an active tenant sees the renewal banner inside the reminder window but not outside it', function () {
    $insideWindow = onboard(['subscription_ends_at' => now()->addDays(3)]);
    $outsideWindow = onboard(['subscription_ends_at' => now()->addDays(20)]);

    $this->actingAs($insideWindow, 'web')->get('/dashboard')
        ->assertOk()
        ->assertSee('renews in 3 days');

    $this->actingAs($outsideWindow, 'web')->get('/dashboard')
        ->assertOk()
        ->assertDontSee('renews in');
});

test('a grace-period tenant can view pages but writes are blocked outside billing', function () {
    $owner = onboard(['subscription_ends_at' => now()->subDays(3)]);
    $tenant = Tenant::findOrFail($owner->tenant_id);
    $plan = SubscriptionPlan::where('code', 'growth')->firstOrFail();

    $this->actingAs($owner, 'web')->get('/dashboard')->assertOk();

    $response = $this->actingAs($owner, 'web')->post('/customers', []);
    $response->assertRedirect();
    expect(session('subscription_blocked'))->not->toBeNull();
    expect(Customer::count())->toBe(0);

    // Billing itself must stay writable so the tenant can actually pay
    // their way out of grace.
    $quotation = app(CreateQuotation::class)->execute($tenant, $plan, createdBy: null);
    $checkout = $this->actingAs($owner, 'web')->post("/billing/quotations/{$quotation->id}/checkout");
    expect($checkout->status())->not->toBe(302);
});

test('a blocked (past-grace) tenant is redirected away from GET requests everywhere except exempted routes', function () {
    $owner = onboard(['subscription_ends_at' => now()->subDays(10)]);

    $this->actingAs($owner, 'web')->get('/dashboard')
        ->assertRedirect(route('account.access'));

    $this->actingAs($owner, 'web')->get('/account-access')->assertOk();
    $this->actingAs($owner, 'web')->get('/billing/quotations')->assertOk();
});

test('a suspended tenant still gets hard-logged-out, unaffected by the new access gate', function () {
    $owner = onboard();
    Tenant::findOrFail($owner->tenant_id)->update(['status' => 'suspended']);

    $response = $this->actingAs($owner, 'web')->get('/dashboard');

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});
