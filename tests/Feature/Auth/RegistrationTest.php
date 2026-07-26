<?php

use App\Domain\Core\Scopes\TenantScope;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

test('onboarding screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('signing up creates a tenant, an owner, and starts a trial', function () {
    $response = $this->post('/register', [
        'business_name' => 'Glow Salon',
        'timezone' => 'Asia/Kolkata',
        'currency' => 'INR',
        'modules' => ['salon'],
        'owner_name' => 'Alice Owner',
        'owner_email' => 'alice@glow.test',
        'owner_password' => 'password123',
        'owner_password_confirmation' => 'password123',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $owner = User::withoutGlobalScope(TenantScope::class)
        ->where('email', 'alice@glow.test')->firstOrFail();

    expect($owner->tenant)->not->toBeNull();
    expect($owner->tenant->status)->toBe('trial');
    expect($owner->tenant->hasModuleEnabled('salon'))->toBeTrue();

    app(PermissionRegistrar::class)->setPermissionsTeamId($owner->tenant_id);
    expect($owner->fresh()->hasRole('Owner'))->toBeTrue();
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
    ]);

    $response->assertSessionHasErrors('modules');
    $this->assertGuest();
});
