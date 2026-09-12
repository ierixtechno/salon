<?php

use App\Domain\Core\Scopes\TenantScope;
use App\Domain\Platform\Actions\CreateQuotation;
use App\Domain\Platform\Actions\PayQuotation;
use App\Domain\Platform\Models\SubscriptionPlan;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

test('onboarding screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('signing up creates a tenant and an owner, pending payment (no free trial)', function () {
    $response = $this->post('/register', [
        'business_name' => 'Glow Salon',
        'timezone' => 'Asia/Kolkata',
        'currency' => 'INR',
        'modules' => ['salon'],
        'owner_name' => 'Alice Owner',
        'owner_email' => 'alice@glow.test',
        'owner_password' => 'password123',
        'owner_password_confirmation' => 'password123',
        'billing_state' => 'Haryana',
        'gstin' => '06ABCDE1234F1Z5',
    ]);

    // Deliberately NOT auto-logged-in — a tenant that's never paid must
    // not be able to use the app at all (see LoginRequest::authenticate()
    // for the login-time enforcement of the same rule).
    $this->assertGuest();
    $response->assertRedirect(route('login'));
    $response->assertSessionHas('status');

    $owner = User::withoutGlobalScope(TenantScope::class)
        ->where('email', 'alice@glow.test')->firstOrFail();

    expect($owner->tenant)->not->toBeNull();
    expect($owner->tenant->status)->toBe('pending_payment');
    // Modules picked at signup are still recorded (informational for Super
    // Admin — see OnboardTenant), even though the account can't use them
    // until it's paid.
    expect($owner->tenant->hasModuleEnabled('salon'))->toBeTrue();
    // Captured so CreateQuotation can bill this tenant correctly, and so
    // their GSTIN appears as the recipient on their invoices.
    expect($owner->tenant->billing_state)->toBe('Haryana');
    expect($owner->tenant->gstin)->toBe('06ABCDE1234F1Z5');

    app(PermissionRegistrar::class)->setPermissionsTeamId($owner->tenant_id);
    expect($owner->fresh()->hasRole('Owner'))->toBeTrue();
});

test('a pending-payment tenant cannot log in even with correct credentials', function () {
    $this->post('/register', [
        'business_name' => 'Glow Salon',
        'modules' => ['salon'],
        'owner_name' => 'Alice Owner',
        'owner_email' => 'alice@glow.test',
        'owner_password' => 'password123',
        'owner_password_confirmation' => 'password123',
        'billing_state' => 'Haryana',
    ]);

    $response = $this->post('/login', [
        'email' => 'alice@glow.test',
        'password' => 'password123',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('a tenant can log in normally once its first quotation is paid', function () {
    $this->post('/register', [
        'business_name' => 'Glow Salon',
        'modules' => ['salon'],
        'owner_name' => 'Alice Owner',
        'owner_email' => 'alice@glow.test',
        'owner_password' => 'password123',
        'owner_password_confirmation' => 'password123',
        'billing_state' => 'Haryana',
    ]);

    $owner = User::withoutGlobalScope(TenantScope::class)
        ->where('email', 'alice@glow.test')->firstOrFail();
    $tenant = $owner->tenant;
    $plan = SubscriptionPlan::where('code', 'salon')->firstOrFail();

    $quotation = app(CreateQuotation::class)->execute($tenant, $plan, createdBy: null);
    app(PayQuotation::class)->execute($quotation, 'razorpay', 'pay_test_activation');

    $response = $this->post('/login', [
        'email' => 'alice@glow.test',
        'password' => 'password123',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('onboarding requires at least one module', function () {
    $response = $this->post('/register', [
        'business_name' => 'Glow Salon',
        'timezone' => 'Asia/Kolkata',
        'currency' => 'INR',
        'modules' => [],
        'owner_name' => 'Alice Owner',
        'owner_email' => 'alice@glow.test',
        'owner_password' => 'password123',
        'owner_password_confirmation' => 'password123',
        'billing_state' => 'Haryana',
    ]);

    $response->assertSessionHasErrors('modules');
    $this->assertGuest();
});

test('onboarding requires a billing state', function () {
    $response = $this->post('/register', [
        'business_name' => 'Glow Salon',
        'modules' => ['salon'],
        'owner_name' => 'Alice Owner',
        'owner_email' => 'alice@glow.test',
        'owner_password' => 'password123',
        'owner_password_confirmation' => 'password123',
    ]);

    $response->assertSessionHasErrors('billing_state');
    $this->assertGuest();
});

test('onboarding rejects a malformed GSTIN', function () {
    $response = $this->post('/register', [
        'business_name' => 'Glow Salon',
        'modules' => ['salon'],
        'owner_name' => 'Alice Owner',
        'owner_email' => 'alice@glow.test',
        'owner_password' => 'password123',
        'owner_password_confirmation' => 'password123',
        'billing_state' => 'Haryana',
        'gstin' => 'not-a-gstin',
    ]);

    $response->assertSessionHasErrors('gstin');
    $this->assertGuest();
});

test('onboarding does not require a GSTIN', function () {
    $response = $this->post('/register', [
        'business_name' => 'Glow Salon',
        'modules' => ['salon'],
        'owner_name' => 'Alice Owner',
        'owner_email' => 'alice@glow.test',
        'owner_password' => 'password123',
        'owner_password_confirmation' => 'password123',
        'billing_state' => 'Haryana',
    ]);

    $this->assertGuest();
    $response->assertRedirect(route('login'));

    $owner = User::withoutGlobalScope(TenantScope::class)
        ->where('email', 'alice@glow.test')->firstOrFail();
    expect($owner->tenant->gstin)->toBeNull();
});
