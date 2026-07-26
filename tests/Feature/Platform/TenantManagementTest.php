<?php

use App\Domain\Core\Scopes\TenantScope;
use App\Domain\Platform\Actions\UpdateTenantModules;
use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Platform\Models\Tenant;
use App\Models\User;

test('a tenant user cannot access any platform route', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();

    $this->actingAs($user)->get('/platform/dashboard')
        ->assertRedirect('/platform/login');
});

test('an unauthenticated guest cannot access platform routes', function () {
    $this->get('/platform/dashboard')->assertRedirect('/platform/login');
});

test('a platform admin can log in and reach the dashboard', function () {
    $admin = PlatformAdmin::factory()->create();

    $this->actingAs($admin, 'platform')
        ->get('/platform/dashboard')
        ->assertOk();
});

/**
 * Regression test: Laravel's default RedirectIfAuthenticated isn't guard-
 * aware, so an already-authenticated platform admin revisiting the guest-
 * only /platform/login route was being redirected to the tenant
 * `dashboard` route — which then bounced to the *tenant* /login since
 * there was no `web` session, a confusing dead end. See bootstrap/app.php
 * redirectUsersTo().
 */
test('a logged-in platform admin revisiting the platform login page lands on the platform dashboard, not the tenant login', function () {
    $admin = PlatformAdmin::factory()->create();

    $this->actingAs($admin, 'platform')
        ->get('/platform/login')
        ->assertRedirect('/platform/dashboard');
});

test('a logged-in tenant user revisiting the tenant login page lands on the tenant dashboard, not the platform login', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();

    $this->actingAs($user)
        ->get('/login')
        ->assertRedirect('/dashboard');
});

test('a platform admin can suspend and reactivate a tenant', function () {
    $admin = PlatformAdmin::factory()->create();
    $tenant = Tenant::factory()->active()->create();

    $this->actingAs($admin, 'platform')
        ->patch("/platform/tenants/{$tenant->id}/status", ['status' => 'suspended'])
        ->assertRedirect();

    expect($tenant->fresh()->status)->toBe('suspended');

    $this->actingAs($admin, 'platform')
        ->patch("/platform/tenants/{$tenant->id}/status", ['status' => 'active'])
        ->assertRedirect();

    expect($tenant->fresh()->status)->toBe('active');
});

test('disabling a module for a tenant does not delete its historical data', function () {
    $admin = PlatformAdmin::factory()->create();
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();

    app(UpdateTenantModules::class)->execute($tenant, ['salon', 'beauty']);

    $this->actingAs($admin, 'platform')
        ->patch("/platform/tenants/{$tenant->id}/modules", ['modules' => ['salon']])
        ->assertRedirect();

    expect($tenant->fresh()->hasModuleEnabled('salon'))->toBeTrue();
    expect($tenant->fresh()->hasModuleEnabled('beauty'))->toBeFalse();

    // The user record — historical data — is untouched by the module change.
    expect(User::withoutGlobalScope(TenantScope::class)->find($user->id))->not->toBeNull();
});
