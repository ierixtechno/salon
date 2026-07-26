<?php

use App\Domain\Core\Actions\CreateEmployee;
use App\Domain\Core\Models\AuditLog;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\EmployeeProfile;
use App\Domain\Core\Scopes\TenantScope;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

test('an owner can create an employee with a role and branch access', function () {
    $owner = onboard();
    $branch = Branch::factory()->create(['tenant_id' => $owner->tenant_id]);

    $this->actingAs($owner)->post('/employees', [
        'name' => 'Cara Stylist',
        'email' => 'cara@glow.test',
        'password' => 'password123',
        'employment_type' => 'full_time',
        'role' => 'Manager',
        'branches' => [$branch->id],
    ])->assertRedirect();

    $employee = User::withoutGlobalScope(TenantScope::class)->where('email', 'cara@glow.test')->firstOrFail();
    expect($employee->tenant_id)->toBe($owner->tenant_id);
    expect(EmployeeProfile::where('user_id', $employee->id)->exists())->toBeTrue();
    expect($employee->canAccessBranch($branch))->toBeTrue();

    app(PermissionRegistrar::class)->setPermissionsTeamId($owner->tenant_id);
    expect($employee->fresh()->hasRole('Manager'))->toBeTrue();

    // Role assignment is audited (CLAUDE.md §47).
    expect(AuditLog::where('action', 'employee.role_assigned')
        ->where('entity_id', $employee->id)->exists())->toBeTrue();
});

test('creating an employee requires a valid role for that tenant', function () {
    $owner = onboard();

    $this->actingAs($owner)->post('/employees', [
        'name' => 'Cara Stylist',
        'email' => 'cara@glow.test',
        'password' => 'password123',
        'employment_type' => 'full_time',
        'role' => 'NotARealRole',
        'branches' => [],
    ])->assertSessionHasErrors('role');
});

test('employee email must be unique within the tenant but may repeat across tenants', function () {
    $ownerA = onboard();
    $ownerB = onboard();

    $this->actingAs($ownerA)->post('/employees', [
        'name' => 'Cara', 'email' => 'shared@example.test', 'password' => 'password123',
        'employment_type' => 'full_time', 'role' => 'Staff', 'branches' => [],
    ])->assertRedirect();

    // Same email, different tenant — must succeed.
    $this->actingAs($ownerB)->post('/employees', [
        'name' => 'Dana', 'email' => 'shared@example.test', 'password' => 'password123',
        'employment_type' => 'full_time', 'role' => 'Staff', 'branches' => [],
    ])->assertRedirect();

    // Same email, same tenant — must fail.
    $this->actingAs($ownerA)->post('/employees', [
        'name' => 'Eve', 'email' => 'shared@example.test', 'password' => 'password123',
        'employment_type' => 'full_time', 'role' => 'Staff', 'branches' => [],
    ])->assertSessionHasErrors('email');
});

/**
 * Mandatory pattern per CLAUDE.md §62.
 */
test('a user from tenant B cannot view or edit an employee belonging to tenant A', function () {
    $ownerA = onboard();
    $ownerB = onboard();

    $employeeA = app(CreateEmployee::class)->execute($ownerA->tenant, [
        'name' => 'Cara', 'email' => 'cara@glow.test', 'password' => 'password123',
        'employment_type' => 'full_time', 'role' => 'Staff', 'all_branches' => false, 'branches' => [],
    ]);
    // No `web`-guard session exists yet at this point in the test, so
    // TenantScope's fail-closed behavior would otherwise hide this row too
    // — same reason Tenant::users() needs an explicit bypass in production.
    $profile = EmployeeProfile::withoutGlobalScope(TenantScope::class)->where('user_id', $employeeA->id)->firstOrFail();

    $this->actingAs($ownerB)->get("/employees/{$profile->id}/edit")->assertNotFound();
    $this->actingAs($ownerB)
        ->put("/employees/{$profile->id}", [
            'name' => 'Hijacked', 'employment_type' => 'full_time', 'role' => 'Staff', 'branches' => [],
        ])
        ->assertNotFound();
    $this->actingAs($ownerB)->delete("/employees/{$profile->id}")->assertNotFound();

    expect($employeeA->fresh()->name)->not->toBe('Hijacked');
});

test('deactivating an employee revokes access but keeps the record', function () {
    $owner = onboard();

    $employee = app(CreateEmployee::class)->execute($owner->tenant, [
        'name' => 'Cara', 'email' => 'cara@glow.test', 'password' => 'password123',
        'employment_type' => 'full_time', 'role' => 'Staff', 'all_branches' => false, 'branches' => [],
    ]);
    $profile = EmployeeProfile::withoutGlobalScope(TenantScope::class)->where('user_id', $employee->id)->firstOrFail();

    $this->actingAs($owner)->delete("/employees/{$profile->id}")->assertRedirect();

    expect($employee->fresh())->not->toBeNull();
    expect($employee->fresh()->is_active)->toBeFalse();
    expect(EmployeeProfile::find($profile->id))->not->toBeNull();
});
