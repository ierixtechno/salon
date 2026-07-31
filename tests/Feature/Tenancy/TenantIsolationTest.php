<?php

use App\Domain\Platform\Models\SubscriptionPlan;
use App\Domain\Platform\Models\Tenant;
use App\Domain\Platform\Models\TenantSubscription;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Mandatory pattern per CLAUDE.md §62: create Tenant A, create Tenant B,
 * create a resource under Tenant A, authenticate as Tenant B, attempt
 * view/edit/delete/export/API fetch — all must fail safely.
 */
test('a user from tenant B can never fetch a user belonging to tenant A', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = User::factory()->forTenant($tenantA)->create();
    $userB = User::factory()->forTenant($tenantB)->create();

    $this->actingAs($userB);

    expect(User::find($userA->id))->toBeNull();
    expect(User::where('email', $userA->email)->first())->toBeNull();
});

test('an unscoped query with no tenant context fails closed, not open', function () {
    Tenant::factory()->create();
    $tenantA = Tenant::factory()->create();
    User::factory()->forTenant($tenantA)->create();

    // No authenticated `web` user at all — current_tenant_id() is null.
    expect(User::count())->toBe(0);
});

test('roles are isolated per tenant even when both tenants use the same role name', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $registrar = app(PermissionRegistrar::class);

    $registrar->setPermissionsTeamId($tenantA->id);
    $ownerRoleA = Role::create(['name' => 'Owner', 'guard_name' => 'web']);
    $userA = User::factory()->forTenant($tenantA)->create();
    $userA->assignRole($ownerRoleA);

    $registrar->setPermissionsTeamId($tenantB->id);
    $userB = User::factory()->forTenant($tenantB)->create();

    // Under tenant B's team context, tenant A's Owner role must not apply to B's user.
    expect($userB->fresh()->hasRole('Owner'))->toBeFalse();

    $registrar->setPermissionsTeamId($tenantA->id);
    expect($userA->fresh()->hasRole('Owner'))->toBeTrue();
});

test('a suspended tenant cannot access authenticated tenant routes', function () {
    $tenant = Tenant::factory()->suspended()->create();
    $user = User::factory()->forTenant($tenant)->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});

test('an active tenant with a current subscription can access authenticated tenant routes', function () {
    $tenant = Tenant::factory()->active()->create();
    TenantSubscription::create([
        'tenant_id' => $tenant->id,
        'subscription_plan_id' => SubscriptionPlan::where('code', 'growth')->firstOrFail()->id,
        'status' => 'active',
        'starts_at' => now()->subMonth(),
        'ends_at' => now()->addMonth(),
    ]);
    $user = User::factory()->forTenant($tenant)->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
});

test('a tenant with no subscription yet is redirected to the account access page', function () {
    $tenant = Tenant::factory()->create(['status' => 'pending_payment']);
    $user = User::factory()->forTenant($tenant)->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertRedirect(route('account.access'));
});

test('super admin cross-tenant relations are explicit, not a blanket scope bypass', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    User::factory()->forTenant($tenantA)->count(2)->create();
    User::factory()->forTenant($tenantB)->count(1)->create();

    // No web-guard session at all (this simulates the platform-guard context).
    $counts = Tenant::withCount('users')->get()->pluck('users_count', 'id');

    expect($counts[$tenantA->id])->toBe(2);
    expect($counts[$tenantB->id])->toBe(1);
});
