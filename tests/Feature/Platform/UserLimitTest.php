<?php

use App\Domain\Core\Actions\CreateEmployee;
use App\Domain\Core\Models\EmployeeProfile;
use App\Domain\Core\Scopes\TenantScope;
use App\Domain\Platform\Models\Module;
use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Platform\Models\SubscriptionPlan;
use App\Domain\Platform\Models\Tenant;
use App\Domain\Platform\Models\TenantSubscription;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/** 1 branch = 5 users; each extra branch adds 3 users. Extra branches cost 999, max 4 branches. */
function userLimitPlan(array $overrides = []): SubscriptionPlan
{
    Module::firstOrCreate(['code' => 'salon'], ['name' => 'Salon']);

    $plan = SubscriptionPlan::create(array_merge([
        'code' => 'users-'.uniqid(), 'name' => 'Team Plan', 'price' => 1999, 'billing_interval' => 'monthly',
        'branch_limit' => 1, 'additional_branch_price' => 999, 'max_branches' => 4,
        'users_included' => 5, 'users_per_additional_branch' => 3, 'is_active' => true,
    ], $overrides));
    $plan->modules()->sync(Module::where('code', 'salon')->pluck('id'));

    return $plan;
}

function tenantWithUserLimit(?int $usersIncluded, int $perBranch = 3, int $branches = 1): User
{
    $plan = userLimitPlan(['users_included' => $usersIncluded, 'users_per_additional_branch' => $perBranch]);
    $owner = onboard(['subscription_plan_code' => $plan->code]);
    TenantSubscription::where('tenant_id', $owner->tenant_id)->update(['branch_count' => $branches]);
    Tenant::forgetSubscriptionCache($owner->tenant_id);

    return $owner;
}

function addStaff(User $owner, string $email): User
{
    return app(CreateEmployee::class)->execute($owner->tenant, [
        'name' => 'Staff '.$email, 'email' => $email, 'password' => 'password123',
        'employment_type' => 'full_time', 'role' => 'Staff', 'all_branches' => true, 'branches' => [],
    ]);
}

function staffProfile(User $user): EmployeeProfile
{
    return EmployeeProfile::withoutGlobalScope(TenantScope::class)->where('user_id', $user->id)->firstOrFail();
}

// ------------------------------------------------ the maths

test('the user limit grows with the number of branches', function () {
    $plan = userLimitPlan();

    expect($plan->userLimitFor(1))->toBe(5);
    expect($plan->userLimitFor(2))->toBe(8);
    expect($plan->userLimitFor(4))->toBe(14);
    expect($plan->userLimitFor(null))->toBe(5);
    expect($plan->userLimitFor(99))->toBe(14); // never beyond the plan's branch maximum
});

test('a plan with no user limit is unlimited, and one that sells no extra branches never grows', function () {
    expect(userLimitPlan(['users_included' => null])->userLimitFor(3))->toBeNull();
    expect(userLimitPlan(['users_included' => null])->usersLabel())->toBe('Unlimited users');

    $flat = userLimitPlan(['additional_branch_price' => 0, 'max_branches' => null]);
    expect($flat->userLimitFor(4))->toBe(5);
    expect($flat->usersLabel())->toBe('5 users');
    expect(userLimitPlan()->usersLabel())->toBe('5 users (+3 per additional branch)');
});

// ------------------------------------------------ enforcement

test('a tenant cannot add employees beyond the plan limit, and the owner counts as a user', function () {
    $owner = tenantWithUserLimit(3); // owner + 2 more

    addStaff($owner, 'one@glow.test');
    addStaff($owner, 'two@glow.test');

    expect(fn () => addStaff($owner, 'three@glow.test'))->toThrow(ValidationException::class);

    expect($owner->tenant->activeUserCount())->toBe(3);
    expect(User::withoutGlobalScopes()->where('email', 'three@glow.test')->exists())->toBeFalse();
});

test('through the employee form the refusal is a clear message, not an error page', function () {
    $owner = tenantWithUserLimit(1); // just the owner

    $this->actingAs($owner)->post('/employees', [
        'name' => 'Cara', 'email' => 'cara@glow.test', 'password' => 'password123',
        'employment_type' => 'full_time', 'role' => 'Staff', 'all_branches' => '1',
    ])->assertSessionHasErrors('user_limit');

    expect(User::withoutGlobalScopes()->where('email', 'cara@glow.test')->exists())->toBeFalse();
});

test('buying more branches raises the user limit', function () {
    $owner = tenantWithUserLimit(2, 3, 1);
    addStaff($owner, 'one@glow.test');
    expect(fn () => addStaff($owner, 'two@glow.test'))->toThrow(ValidationException::class);

    TenantSubscription::where('tenant_id', $owner->tenant_id)->update(['branch_count' => 2]); // now 2 + 3 = 5
    Tenant::forgetSubscriptionCache($owner->tenant_id);

    addStaff($owner, 'two@glow.test');
    addStaff($owner, 'three@glow.test');
    addStaff($owner, 'four@glow.test');
    expect(fn () => addStaff($owner, 'five@glow.test'))->toThrow(ValidationException::class);
    expect($owner->tenant->fresh()->userLimit())->toBe(5);
});

test('deactivating an employee frees a slot, and switching them back on needs a free one', function () {
    $owner = tenantWithUserLimit(2);
    $staff = addStaff($owner, 'one@glow.test');
    $profile = staffProfile($staff);

    $this->actingAs($owner)->delete("/employees/{$profile->id}")->assertRedirect();
    expect($staff->fresh()->is_active)->toBeFalse();

    $replacement = addStaff($owner, 'two@glow.test'); // takes the freed slot

    $this->actingAs($owner)->put("/employees/{$profile->id}", [
        'name' => $staff->name, 'email' => $staff->email, 'employment_type' => 'full_time', 'role' => 'Staff', 'is_active' => '1', 'all_branches' => '1',
    ])->assertSessionHasErrors('user_limit');

    expect($staff->fresh()->is_active)->toBeFalse();
    expect($replacement->fresh()->is_active)->toBeTrue();
});

test('editing an active employee is never blocked by the limit', function () {
    $owner = tenantWithUserLimit(2);
    $staff = addStaff($owner, 'one@glow.test');

    $this->actingAs($owner)->put('/employees/'.staffProfile($staff)->id, [
        'name' => 'Renamed', 'email' => $staff->email, 'employment_type' => 'full_time', 'role' => 'Staff', 'all_branches' => '1',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($staff->fresh()->name)->toBe('Renamed');
});

test('users a tenant already has above a lowered limit are kept', function () {
    $owner = tenantWithUserLimit(null);
    foreach (['a', 'b', 'c'] as $n) {
        addStaff($owner, "{$n}@glow.test");
    }

    $plan = SubscriptionPlan::whereKey(TenantSubscription::where('tenant_id', $owner->tenant_id)->value('subscription_plan_id'))->firstOrFail();
    $plan->update(['users_included' => 2]);
    Tenant::forgetSubscriptionCache($owner->tenant_id);

    expect($owner->tenant->fresh()->activeUserCount())->toBe(4);
    expect(fn () => addStaff($owner, 'd@glow.test'))->toThrow(ValidationException::class);
    expect($owner->tenant->fresh()->activeUserCount())->toBe(4);
});

test('a plan without a user limit lets a tenant add as many employees as it likes', function () {
    $owner = tenantWithUserLimit(null);

    foreach (range(1, 8) as $n) {
        addStaff($owner, "u{$n}@glow.test");
    }

    expect($owner->tenant->activeUserCount())->toBe(9);
    expect($owner->tenant->userLimit())->toBeNull();
});

test('a tenant on a legacy plan is unaffected', function () {
    $owner = onboard(); // growth plan, no user limit configured

    addStaff($owner, 'one@glow.test');
    addStaff($owner, 'two@glow.test');

    expect($owner->tenant->userLimit())->toBeNull();
});

test('the limit is per tenant', function () {
    $small = tenantWithUserLimit(1);
    $big = tenantWithUserLimit(10);

    expect(fn () => addStaff($small, 'x@glow.test'))->toThrow(ValidationException::class);
    addStaff($big, 'y@glow.test');
    expect($big->tenant->activeUserCount())->toBe(2);
});

test('the employee pages show how many users the plan allows', function () {
    $owner = tenantWithUserLimit(3);
    addStaff($owner, 'one@glow.test');

    $this->actingAs($owner)->get('/employees')->assertOk()->assertSee('Your plan allows 3 users')->assertSee('2 in use');
    $this->actingAs($owner)->get('/employees/create')->assertOk()->assertSee('Your plan allows 3 users');

    addStaff($owner, 'two@glow.test');
    $this->actingAs($owner)->get('/employees')->assertOk()->assertSee('To add more, add branches or upgrade your plan');
});

// ------------------------------------------------ plan management

test('super admin can set the user limits on a plan, and blank means unlimited', function () {
    $admin = PlatformAdmin::factory()->create();
    $plan = userLimitPlan();
    $base = ['name' => $plan->name, 'price' => 1999, 'billing_interval' => 'monthly', 'branch_limit' => 1, 'additional_branch_price' => 999, 'is_active' => '1', 'features' => [], 'modules' => ['salon']];

    $this->actingAs($admin, 'platform')->get("/platform/subscription-plans/{$plan->id}/edit")->assertOk()->assertSee('Users (employees)');

    $this->actingAs($admin, 'platform')->put("/platform/subscription-plans/{$plan->id}", $base + ['users_included' => 8, 'users_per_additional_branch' => 4])->assertRedirect();
    $plan->refresh();
    expect($plan->users_included)->toBe(8);
    expect($plan->users_per_additional_branch)->toBe(4);

    $this->actingAs($admin, 'platform')->put("/platform/subscription-plans/{$plan->id}", $base + ['users_included' => '', 'users_per_additional_branch' => ''])->assertRedirect();
    $plan->refresh();
    expect($plan->users_included)->toBeNull();
    expect($plan->users_per_additional_branch)->toBe(0);

    $this->actingAs($admin, 'platform')->put("/platform/subscription-plans/{$plan->id}", $base + ['users_included' => 0])->assertSessionHasErrors('users_included');
});

test('the plan cards show the user limit', function () {
    $admin = PlatformAdmin::factory()->create();
    $owner = onboard();
    userLimitPlan(['name' => 'Team Plan']);

    $this->get('/register')->assertOk()->assertSeeText('5 users');
    $this->actingAs($owner, 'web')->get('/billing/plans')->assertOk()->assertSeeText('5 users (+3 per additional branch)');
    $this->actingAs($admin, 'platform')->get('/platform/subscription-plans')->assertOk()->assertSeeText('5 users (+3 per additional branch)');
});
