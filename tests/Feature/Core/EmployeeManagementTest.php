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
    $branch = Branch::factory()->forTenant($owner->tenant)->create();

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


test('an owner can change an employee\'s email, and the change is audited', function () {
    $owner = onboard();
    $employee = app(CreateEmployee::class)->execute($owner->tenant, [
        'name' => 'Cara', 'email' => 'cara@glow.test', 'password' => 'password123',
        'employment_type' => 'full_time', 'role' => 'Staff', 'all_branches' => false, 'branches' => [],
    ]);
    $profile = EmployeeProfile::withoutGlobalScope(TenantScope::class)->where('user_id', $employee->id)->firstOrFail();

    $this->actingAs($owner)->get("/employees/{$profile->id}/edit")->assertOk()->assertSee('cara@glow.test');

    $this->actingAs($owner)->put("/employees/{$profile->id}", [
        'name' => 'Cara', 'email' => 'Cara.New@Glow.test', 'employment_type' => 'full_time', 'role' => 'Staff', 'branches' => [],
    ])->assertRedirect();

    expect($employee->fresh()->email)->toBe('cara.new@glow.test');
    expect(AuditLog::where('action', 'employee.email_changed')->where('entity_id', $employee->id)->exists())->toBeTrue();

    // ...and they can log in with it.
    auth()->guard('web')->logout();
    $this->post('/login', ['email' => 'cara.new@glow.test', 'password' => 'password123'])->assertRedirect();
});

test('saving an employee without touching the email is fine, but another employee\'s email is refused', function () {
    $owner = onboard();
    $make = fn (string $email) => app(CreateEmployee::class)->execute($owner->tenant, [
        'name' => 'Emp', 'email' => $email, 'password' => 'password123',
        'employment_type' => 'full_time', 'role' => 'Staff', 'all_branches' => false, 'branches' => [],
    ]);
    $cara = $make('cara@glow.test');
    $make('dan@glow.test');
    $profile = EmployeeProfile::withoutGlobalScope(TenantScope::class)->where('user_id', $cara->id)->firstOrFail();
    $base = ['name' => 'Cara', 'employment_type' => 'full_time', 'role' => 'Staff', 'branches' => []];

    $this->actingAs($owner)->put("/employees/{$profile->id}", $base + ['email' => 'cara@glow.test'])->assertRedirect()->assertSessionHasNoErrors();
    $this->actingAs($owner)->put("/employees/{$profile->id}", $base + ['email' => 'dan@glow.test'])->assertSessionHasErrors('email');
    $this->actingAs($owner)->put("/employees/{$profile->id}", $base + ['email' => 'not-an-email'])->assertSessionHasErrors('email');
    $this->actingAs($owner)->put("/employees/{$profile->id}", $base)->assertSessionHasErrors('email');

    expect($cara->fresh()->email)->toBe('cara@glow.test');
});

test('the same email may exist in another tenant', function () {
    $ownerA = onboard();
    $ownerB = onboard();
    app(CreateEmployee::class)->execute($ownerB->tenant, [
        'name' => 'Other', 'email' => 'shared@glow.test', 'password' => 'password123',
        'employment_type' => 'full_time', 'role' => 'Staff', 'all_branches' => false, 'branches' => [],
    ]);
    $cara = app(CreateEmployee::class)->execute($ownerA->tenant, [
        'name' => 'Cara', 'email' => 'cara@glow.test', 'password' => 'password123',
        'employment_type' => 'full_time', 'role' => 'Staff', 'all_branches' => false, 'branches' => [],
    ]);
    $profile = EmployeeProfile::withoutGlobalScope(TenantScope::class)->where('user_id', $cara->id)->firstOrFail();

    $this->actingAs($ownerA)->put("/employees/{$profile->id}", [
        'name' => 'Cara', 'email' => 'shared@glow.test', 'employment_type' => 'full_time', 'role' => 'Staff', 'branches' => [],
    ])->assertRedirect()->assertSessionHasNoErrors();
});


test('saving a weekly schedule with a working day but no times is a validation error, not a database crash', function () {
    $owner = onboard();
    $branch = Branch::factory()->forTenant($owner->tenant)->create();
    $employee = app(CreateEmployee::class)->execute($owner->tenant, [
        'name' => 'Cara', 'email' => 'cara@glow.test', 'password' => 'password123',
        'employment_type' => 'full_time', 'role' => 'Staff', 'all_branches' => true, 'branches' => [],
    ]);
    $profile = EmployeeProfile::withoutGlobalScope(TenantScope::class)->where('user_id', $employee->id)->firstOrFail();

    $shifts = fn (array $monday) => collect(range(0, 6))->mapWithKeys(fn ($d) => [$d => $d === 1
        ? ['day_of_week' => $d] + $monday          // "Off" unchecked => the browser sends no is_off at all
        : ['day_of_week' => $d, 'is_off' => '1']])->all();

    // Working day, times left blank (what crashed in production).
    $this->actingAs($owner)->put("/employees/{$profile->id}/schedule", ['branch_id' => $branch->id, 'shifts' => $shifts(['starts_at' => '', 'ends_at' => ''])])
        ->assertSessionHasErrors(['shifts.1.starts_at', 'shifts.1.ends_at']);

    // Filled in properly it saves.
    $this->actingAs($owner)->put("/employees/{$profile->id}/schedule", ['branch_id' => $branch->id, 'shifts' => $shifts(['starts_at' => '09:00', 'ends_at' => '18:00'])])
        ->assertRedirect()->assertSessionHasNoErrors();

    expect(App\Domain\Core\Models\EmployeeSchedule::where('user_id', $employee->id)->where('day_of_week', 1)->exists())->toBeTrue();
});
