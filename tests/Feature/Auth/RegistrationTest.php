<?php

use App\Domain\Core\Scopes\TenantScope;
use App\Domain\Platform\Actions\CreateQuotation;
use App\Domain\Platform\Actions\PayQuotation;
use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Platform\Models\Quotation;
use App\Domain\Platform\Models\SubscriptionPlan;
use App\Domain\Platform\Models\Tenant;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

function salonPlan(): SubscriptionPlan
{
    return SubscriptionPlan::where('code', 'salon')->with('modules')->firstOrFail();
}

function signupPayload(array $overrides = []): array
{
    return array_merge([
        'business_name' => 'Glow Salon',
        'subscription_plan_id' => salonPlan()->id,
        'owner_name' => 'Alice Owner',
        'owner_phone' => '9876543210', 'owner_email' => 'alice@glow.test',
        'owner_password' => 'password123',
        'owner_password_confirmation' => 'password123',
        'billing_state' => 'Haryana',
    ], $overrides);
}

function signedUpOwner(): User
{
    return User::withoutGlobalScope(TenantScope::class)->where('email', 'alice@glow.test')->firstOrFail();
}

// ------------------------------------------------------------------ signup

test('onboarding screen can be rendered, listing the paid packages to choose from', function () {
    $response = $this->get('/register');

    $response->assertOk()
        ->assertSee('Choose your package')
        ->assertSee(salonPlan()->name);

    // Zero-priced plans can't be paid online, so they are never self-selected.
    SubscriptionPlan::where('price', '<=', 0)->where('is_active', true)->get()
        ->each(fn ($free) => $response->assertDontSee('value="'.$free->id.'"', false));
});

test('signing up creates the tenant, its owner, and a quotation for the chosen package', function () {
    $response = $this->post('/register', signupPayload(['gstin' => '06ABCDE1234F1Z5']));

    // Not auto-logged-in — they log in themselves and land on the quotation.
    $this->assertGuest();
    $response->assertRedirect(route('login'));

    $owner = signedUpOwner();
    $tenant = $owner->tenant;

    expect($tenant->status)->toBe('pending_payment');
    expect($tenant->billing_state)->toBe('Haryana');
    expect($tenant->gstin)->toBe('06ABCDE1234F1Z5');

    // The package's modules become the tenant's modules — nothing asked separately.
    foreach (salonPlan()->modules as $module) {
        expect($tenant->hasModuleEnabled($module->code))->toBeTrue();
    }

    $quotation = Quotation::where('tenant_id', $tenant->id)->firstOrFail();
    expect($quotation->status)->toBe('pending');
    expect($quotation->subscription_plan_id)->toBe(salonPlan()->id);
    expect((float) $quotation->amount)->toBe((float) salonPlan()->price);

    $response->assertSessionHas('status', fn ($status) => str_contains($status, $quotation->quotation_number));

    app(PermissionRegistrar::class)->setPermissionsTeamId($owner->tenant_id);
    expect($owner->fresh()->hasRole('Owner'))->toBeTrue();
});

test('signup requires choosing a package', function () {
    $this->post('/register', signupPayload(['subscription_plan_id' => null]))
        ->assertSessionHasErrors('subscription_plan_id');

    $this->assertGuest();
    expect(Tenant::where('name', 'Glow Salon')->exists())->toBeFalse();
});

test('signup refuses a zero-priced or inactive package, even if posted directly', function () {
    $free = SubscriptionPlan::create(['code' => 'free-test', 'name' => 'Free Test', 'price' => 0, 'billing_interval' => 'monthly', 'is_active' => true]);
    $retired = SubscriptionPlan::create(['code' => 'retired-test', 'name' => 'Retired', 'price' => 999, 'billing_interval' => 'monthly', 'is_active' => false]);

    $this->post('/register', signupPayload(['subscription_plan_id' => $free->id]))->assertSessionHasErrors('subscription_plan_id');
    $this->post('/register', signupPayload(['subscription_plan_id' => $retired->id]))->assertSessionHasErrors('subscription_plan_id');

    expect(Tenant::where('name', 'Glow Salon')->exists())->toBeFalse();
});

test('a paid package with no modules is not offered and cannot be chosen — it would leave the tenant with an empty app', function () {
    $empty = SubscriptionPlan::create(['code' => 'empty-test', 'name' => 'Empty Package', 'price' => 1500, 'billing_interval' => 'monthly', 'is_active' => true]);

    $this->get('/register')->assertOk()->assertDontSee('Empty Package');

    $this->post('/register', signupPayload(['subscription_plan_id' => $empty->id]))
        ->assertSessionHasErrors('subscription_plan_id');

    expect(Tenant::where('name', 'Glow Salon')->exists())->toBeFalse();
});

test('if the quotation cannot be created, the whole signup is rolled back — no tenant left without a quotation', function () {
    // A real subclass (so the controller's type-hint accepts it) that fails
    // only once it's called — i.e. AFTER the tenant and owner have already
    // been inserted. Proves those inserts are rolled back, not just skipped.
    $this->app->bind(CreateQuotation::class, fn () => new class extends CreateQuotation
    {
        public function execute(Tenant $tenant, SubscriptionPlan $plan, ?PlatformAdmin $createdBy, ?string $amountOverride = null, ?string $notes = null, bool $isUpgrade = false, ?int $branchCount = null, ?float $discountPercent = null): Quotation
        {
            expect(Tenant::whereKey($tenant->id)->exists())->toBeTrue(); // the tenant really was created first

            throw new RuntimeException('quotation numbering failed');
        }
    });

    $this->withoutExceptionHandling();

    expect(fn () => $this->post('/register', signupPayload()))->toThrow(RuntimeException::class, 'quotation numbering failed');

    expect(Tenant::where('name', 'Glow Salon')->exists())->toBeFalse();
    expect(User::withoutGlobalScope(TenantScope::class)->where('email', 'alice@glow.test')->exists())->toBeFalse();
});

test('onboarding requires a billing state', function () {
    $this->post('/register', signupPayload(['billing_state' => null]))
        ->assertSessionHasErrors('billing_state');
    $this->assertGuest();
});

test('onboarding rejects a malformed GSTIN', function () {
    $this->post('/register', signupPayload(['gstin' => 'not-a-gstin']))
        ->assertSessionHasErrors('gstin');
    $this->assertGuest();
});

test('onboarding does not require a GSTIN', function () {
    $this->post('/register', signupPayload())->assertRedirect(route('login'));

    expect(signedUpOwner()->tenant->gstin)->toBeNull();
});

// ------------------------------------------------- logging in before paying

test('a tenant that has not paid can log in, and is taken straight to their quotation', function () {
    $this->post('/register', signupPayload());
    $quotation = Quotation::where('tenant_id', signedUpOwner()->tenant_id)->firstOrFail();

    $this->post('/login', ['email' => 'alice@glow.test', 'password' => 'password123'])
        ->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticated();

    // Dashboard -> account access -> their quotation.
    $this->get('/dashboard')->assertRedirect(route('account.access'));
    $this->get('/account-access')->assertRedirect(route('billing.quotations.show', $quotation));

    $this->get(route('billing.quotations.show', $quotation))
        ->assertOk()
        ->assertSee($quotation->quotation_number)
        ->assertSee('Pay Now')
        ->assertSee('Pay this quotation to unlock it');
});

test('while unpaid, the tenant sees no side menu and every other page sends them back to the quotation', function () {
    $this->post('/register', signupPayload());
    $owner = signedUpOwner();
    $quotation = Quotation::where('tenant_id', $owner->tenant_id)->firstOrFail();

    $page = $this->actingAs($owner, 'web')->get(route('billing.quotations.show', $quotation));
    $page->assertOk()
        ->assertDontSee('Download Mobile App')
        ->assertDontSee(route('customers.index'));

    foreach (['/dashboard', '/customers', '/appointments', '/services', '/invoices'] as $path) {
        $this->actingAs($owner, 'web')->get($path)->assertRedirect(route('account.access'));
    }

    // Writes are refused just the same.
    $this->actingAs($owner, 'web')->post('/customers', ['first_name' => 'X'])->assertRedirect(route('account.access'));

    // Billing, their profile, and logging out all still work.
    $this->actingAs($owner, 'web')->get(route('billing.quotations.index'))->assertOk();
    $this->actingAs($owner, 'web')->get(route('profile.edit'))->assertOk();
    $this->actingAs($owner, 'web')->post('/logout')->assertRedirect();
});

test('once the quotation is paid online, the tenant gets the whole app and the side menu', function () {
    $this->post('/register', signupPayload());
    $owner = signedUpOwner();
    $quotation = Quotation::where('tenant_id', $owner->tenant_id)->firstOrFail();

    app(PayQuotation::class)->execute($quotation, 'razorpay', 'pay_test_activation');

    expect($owner->tenant->fresh()->status)->toBe('active');

    $this->actingAs($owner, 'web')->get('/dashboard')
        ->assertOk()
        ->assertSee('Download Mobile App');
});

test('when Super Admin records the payment, the tenant is unlocked on their very next page load', function () {
    $this->post('/register', signupPayload());
    $owner = signedUpOwner();
    $quotation = Quotation::where('tenant_id', $owner->tenant_id)->firstOrFail();

    // Logged in and locked...
    $this->actingAs($owner, 'web')->get('/dashboard')->assertRedirect(route('account.access'));

    $admin = PlatformAdmin::factory()->create();
    $this->actingAs($admin, 'platform')
        ->post("/platform/quotations/{$quotation->id}/record-payment", ['payment_method' => 'bank_transfer', 'payment_reference' => 'UTR99'])
        ->assertRedirect();

    // ...unlocked without logging in again.
    $this->actingAs($owner, 'web')->get('/dashboard')->assertOk();
});

test('an existing tenant with a quotation created directly still logs in normally once it is paid', function () {
    $this->post('/register', signupPayload());
    $tenant = signedUpOwner()->tenant;

    // A second quotation (e.g. an upgrade) doesn't change anything once the first is paid.
    $first = Quotation::where('tenant_id', $tenant->id)->firstOrFail();
    app(PayQuotation::class)->execute($first, 'upi', 'UTR1');
    app(CreateQuotation::class)->execute($tenant->fresh(), salonPlan(), createdBy: null);

    $this->post('/login', ['email' => 'alice@glow.test', 'password' => 'password123'])
        ->assertRedirect(route('dashboard', absolute: false));
    $this->get('/dashboard')->assertOk();
});
