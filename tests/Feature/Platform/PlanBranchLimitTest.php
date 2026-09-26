<?php

use App\Domain\Core\Actions\CreateEmployee;
use App\Domain\Core\Models\Branch;
use App\Domain\Platform\Models\Module;
use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Platform\Models\SubscriptionPlan;
use App\Domain\Platform\Models\Tenant;
use App\Domain\Platform\Models\TenantSubscription;

function setBranchLimit(Tenant $tenant, int $limit): void
{
    $plan = SubscriptionPlan::create([
        'code' => 'branches-'.$limit.'-'.uniqid(), 'name' => "{$limit} branches", 'price' => 999,
        'billing_interval' => 'monthly', 'branch_limit' => $limit, 'is_active' => true,
    ]);

    TenantSubscription::where('tenant_id', $tenant->id)->update(['status' => 'cancelled']);
    TenantSubscription::create([
        'tenant_id' => $tenant->id, 'subscription_plan_id' => $plan->id,
        'status' => 'active', 'starts_at' => now()->addSecond(), 'ends_at' => now()->addMonth(),
    ]);
    Tenant::forgetSubscriptionCache($tenant->id);
}

function branchPayload(string $code): array
{
    return ['name' => "Branch {$code}", 'code' => $code];
}

test('a tenant gets one branch by default, and a second is refused with a clear message', function () {
    $owner = onboard();

    $this->actingAs($owner)->post('/branches', branchPayload('ONE'))->assertRedirect();

    $this->actingAs($owner)->post('/branches', branchPayload('TWO'))
        ->assertSessionHasErrors('branch_limit');

    expect(Branch::count())->toBe(1);
});

test('a plan with a higher branch limit lets the tenant add exactly that many branches', function () {
    $owner = onboard();
    setBranchLimit($owner->tenant, 3);

    foreach (['A', 'B', 'C'] as $code) {
        $this->actingAs($owner)->post('/branches', branchPayload($code))->assertRedirect();
    }
    $this->actingAs($owner)->post('/branches', branchPayload('D'))->assertSessionHasErrors('branch_limit');

    expect(Branch::count())->toBe(3);
});

test('a deactivated branch frees its slot', function () {
    $owner = onboard();
    $this->actingAs($owner)->post('/branches', branchPayload('ONE'));
    $branch = Branch::where('code', 'ONE')->firstOrFail();

    $this->actingAs($owner)->delete("/branches/{$branch->id}")->assertRedirect();
    $this->actingAs($owner)->post('/branches', branchPayload('TWO'))->assertRedirect();

    expect(Branch::where('is_active', true)->count())->toBe(1);
});

test('branches a tenant already has above its limit are kept, they just cannot add more', function () {
    $owner = onboard();
    Branch::factory()->forTenant($owner->tenant)->count(2)->create();

    $this->actingAs($owner)->post('/branches', branchPayload('NEW'))->assertSessionHasErrors('branch_limit');

    expect(Branch::count())->toBe(2);
});

test('the branch limit is per tenant — another tenant\'s plan does not raise it', function () {
    $ownerA = onboard();
    $ownerB = onboard();
    setBranchLimit($ownerA->tenant, 5);

    $this->actingAs($ownerB)->post('/branches', branchPayload('B1'))->assertRedirect();
    $this->actingAs($ownerB)->post('/branches', branchPayload('B2'))->assertSessionHasErrors('branch_limit');
});

test('a user without the branches.create permission still cannot create a branch', function () {
    $owner = onboard();
    setBranchLimit($owner->tenant, 5);
    $staff = app(CreateEmployee::class)->execute($owner->tenant, [
        'name' => 'Sam', 'email' => 'sam@glow.test', 'password' => 'password123',
        'employment_type' => 'full_time', 'role' => 'Staff', 'all_branches' => false, 'branches' => [],
    ]);

    $this->actingAs($staff)->post('/branches', branchPayload('X'))->assertForbidden();
});

test('the new-branch page tells the tenant how many branches their plan includes', function () {
    $owner = onboard();

    $this->actingAs($owner)->get('/branches/create')
        ->assertOk()
        ->assertSee('Your plan includes 1 branch');
});

test('super admin can set the number of branches on a plan', function () {
    $admin = PlatformAdmin::factory()->create();
    $module = Module::firstOrCreate(['code' => 'salon'], ['name' => 'Salon']);

    $this->actingAs($admin, 'platform')->post('/platform/subscription-plans', [
        'code' => 'salon-3-branches', 'name' => 'Salon 3 Branches', 'price' => 2999,
        'billing_interval' => 'monthly', 'branch_limit' => 3, 'is_active' => '1',
        'features' => [], 'modules' => [$module->code],
    ])->assertRedirect('/platform/subscription-plans');

    expect(SubscriptionPlan::where('code', 'salon-3-branches')->firstOrFail()->branch_limit)->toBe(3);

    $plan = SubscriptionPlan::where('code', 'salon-3-branches')->firstOrFail();
    $this->actingAs($admin, 'platform')->put("/platform/subscription-plans/{$plan->id}", [
        'name' => $plan->name, 'price' => 2999, 'billing_interval' => 'monthly',
        'branch_limit' => 5, 'is_active' => '1', 'features' => [], 'modules' => [$module->code],
    ])->assertRedirect();

    expect($plan->fresh()->branch_limit)->toBe(5);
});

test('a plan must include at least one branch', function () {
    $admin = PlatformAdmin::factory()->create();

    $this->actingAs($admin, 'platform')->post('/platform/subscription-plans', [
        'code' => 'zero-branches', 'name' => 'Zero', 'price' => 100,
        'billing_interval' => 'monthly', 'branch_limit' => 0,
        'features' => [], 'modules' => [],
    ])->assertSessionHasErrors('branch_limit');
});

test('the tenant\'s active plan is listed first on the billing plans page', function () {
    $owner = onboard(['subscription_plan_code' => 'pro']); // the dearest plan would normally be listed last
    $pro = SubscriptionPlan::where('code', 'pro')->firstOrFail();
    $others = SubscriptionPlan::where('is_active', true)->where('id', '!=', $pro->id)->orderBy('price')->get();

    expect($others)->not->toBeEmpty();

    $this->actingAs($owner)->get('/billing/plans')
        ->assertOk()
        ->assertSeeInOrder([$pro->name, 'Current Plan', $others->first()->name]);
});

test('the billing plans page shows how many branches each plan includes', function () {
    $owner = onboard();
    setBranchLimit($owner->tenant, 4);

    $this->actingAs($owner)->get('/billing/plans')->assertOk()->assertSeeText('4 branches included');
});
