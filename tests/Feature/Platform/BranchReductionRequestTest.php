<?php

use App\Domain\Core\Models\Branch;
use App\Domain\Platform\Models\BranchReductionRequest;
use App\Domain\Platform\Models\Module;
use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Platform\Models\PlatformAuditLog;
use App\Domain\Platform\Models\SubscriptionPlan;
use App\Domain\Platform\Models\Tenant;
use App\Domain\Platform\Models\TenantSubscription;
use App\Models\User;

/** 1 branch included, up to 4 total, 5 users included + 3 per extra branch. */
function reductionFlexPlan(array $overrides = []): SubscriptionPlan
{
    Module::firstOrCreate(['code' => 'salon'], ['name' => 'Salon']);
    $plan = SubscriptionPlan::create(array_merge([
        'code' => 'reduce-flex-'.uniqid(), 'name' => 'Flex Plan', 'price' => 1999, 'billing_interval' => 'monthly',
        'branch_limit' => 1, 'additional_branch_price' => 999, 'max_branches' => 4,
        'users_included' => 5, 'users_per_additional_branch' => 3, 'is_active' => true,
    ], $overrides));
    $plan->modules()->sync(Module::where('code', 'salon')->pluck('id'));

    return $plan;
}

function tenantOnReductionPlan(int $branches, SubscriptionPlan $plan): User
{
    $owner = onboard(['subscription_plan_code' => $plan->code, 'subscription_ends_at' => now()->addDays(15)->startOfDay()]);
    TenantSubscription::where('tenant_id', $owner->tenant_id)->update(['branch_count' => $branches]);
    Tenant::forgetSubscriptionCache($owner->tenant_id);

    return $owner;
}

// ------------------------------------------------ tenant submits a request

test('a tenant can request a branch reduction once it has deactivated enough branches', function () {
    $plan = reductionFlexPlan();
    $owner = tenantOnReductionPlan(3, $plan);
    Branch::factory()->forTenant($owner->tenant)->count(3)->create(['is_active' => true]);
    // Tenant::branches() bypasses TenantScope (see its docblock) — needed here since
    // there is no acting-as tenant session at this point in the test.
    $owner->tenant->branches()->limit(2)->get()->each->update(['is_active' => false]); // down to 1 active

    $response = $this->actingAs($owner, 'web')->post('/branch-reduction-requests', [
        'requested_branch_count' => 1, 'reason' => 'Closed two locations',
    ]);

    $response->assertRedirect();
    $request = BranchReductionRequest::firstOrFail();
    expect($request->tenant_id)->toBe($owner->tenant_id);
    expect($request->current_branch_count)->toBe(3);
    expect($request->requested_branch_count)->toBe(1);
    expect($request->status)->toBe('pending');
    expect($request->requested_by)->toBe($owner->id);
});

test('a tenant cannot request a reduction while active branches still exceed the target', function () {
    $plan = reductionFlexPlan();
    $owner = tenantOnReductionPlan(3, $plan);
    Branch::factory()->forTenant($owner->tenant)->count(3)->create(['is_active' => true]);

    $this->actingAs($owner, 'web')->post('/branch-reduction-requests', ['requested_branch_count' => 1])
        ->assertStatus(422); // 3 active > 1 requested

    expect(BranchReductionRequest::count())->toBe(0);
});

test('the requested count must be lower than current', function () {
    $plan = reductionFlexPlan();
    $owner = tenantOnReductionPlan(2, $plan);

    $this->actingAs($owner, 'web')->post('/branch-reduction-requests', ['requested_branch_count' => 2])->assertStatus(422); // not lower than current (2)
    $this->actingAs($owner, 'web')->post('/branch-reduction-requests', ['requested_branch_count' => 3])->assertStatus(422); // higher than current
    $this->actingAs($owner, 'web')->post('/branch-reduction-requests', ['requested_branch_count' => 0])->assertSessionHasErrors('requested_branch_count'); // form-level min:1

    expect(BranchReductionRequest::count())->toBe(0);
});

test('the requested count cannot go below the plan\'s included branches — that needs a different plan, not a reduction', function () {
    $plan = reductionFlexPlan(['branch_limit' => 2, 'max_branches' => 5]);
    $owner = tenantOnReductionPlan(3, $plan);

    $this->actingAs($owner, 'web')->post('/branch-reduction-requests', ['requested_branch_count' => 1])->assertStatus(422);

    expect(BranchReductionRequest::count())->toBe(0);
});

test('only one pending request at a time', function () {
    $plan = reductionFlexPlan();
    $owner = tenantOnReductionPlan(3, $plan);

    $this->actingAs($owner, 'web')->post('/branch-reduction-requests', ['requested_branch_count' => 1]);
    $this->actingAs($owner, 'web')->post('/branch-reduction-requests', ['requested_branch_count' => 1])->assertStatus(409);

    expect(BranchReductionRequest::count())->toBe(1);
});

test('a user without tenant.billing.manage cannot submit a reduction request', function () {
    $plan = reductionFlexPlan();
    $owner = tenantOnReductionPlan(2, $plan);
    $staff = app(App\Domain\Core\Actions\CreateEmployee::class)->execute($owner->tenant, [
        'name' => 'Sam', 'email' => 'sam@glow.test', 'password' => 'password123',
        'employment_type' => 'full_time', 'role' => 'Staff', 'all_branches' => true, 'branches' => [],
    ]);

    $this->actingAs($staff, 'web')->post('/branch-reduction-requests', ['requested_branch_count' => 1])->assertForbidden();
});

test('the branches page shows the current allowance, and the request form once deactivated down', function () {
    $plan = reductionFlexPlan();
    $owner = tenantOnReductionPlan(2, $plan);

    $this->actingAs($owner, 'web')->get('/branches')->assertOk()->assertSee('bills for')->assertSee('2')->assertSee('Reduce your branch count');

    $this->actingAs($owner, 'web')->post('/branch-reduction-requests', ['requested_branch_count' => 1]);
    $this->actingAs($owner, 'web')->get('/branches')->assertOk()->assertSee('Waiting on Super Admin');
});

// ------------------------------------------------ Super Admin approves/rejects

test('super admin can approve a reduction request, which lowers the subscription and the user limit', function () {
    $plan = reductionFlexPlan();
    $owner = tenantOnReductionPlan(3, $plan);
    Branch::factory()->forTenant($owner->tenant)->count(1)->create(['is_active' => true]); // fits under 1
    $admin = PlatformAdmin::factory()->create();
    $this->actingAs($owner, 'web')->post('/branch-reduction-requests', ['requested_branch_count' => 1]);
    $request = BranchReductionRequest::firstOrFail();

    $response = $this->actingAs($admin, 'platform')->post("/platform/branch-reduction-requests/{$request->id}/approve");
    $response->assertRedirect();

    expect($request->fresh()->status)->toBe('approved');
    expect($request->fresh()->decided_by)->toBe($admin->id);
    $tenant = Tenant::findOrFail($owner->tenant_id);
    expect($tenant->branchLimit())->toBe(1);
    expect($tenant->userLimit())->toBe(5); // back to the base tier
    expect(PlatformAuditLog::where('action', 'tenant.branch_reduction_approved')->where('tenant_id', $tenant->id)->exists())->toBeTrue();
});

test('approval is refused if the tenant now has more active branches than requested', function () {
    $plan = reductionFlexPlan();
    $owner = tenantOnReductionPlan(3, $plan);
    Branch::factory()->forTenant($owner->tenant)->count(1)->create(['is_active' => true]);
    $admin = PlatformAdmin::factory()->create();
    $this->actingAs($owner, 'web')->post('/branch-reduction-requests', ['requested_branch_count' => 1]);
    $request = BranchReductionRequest::firstOrFail();

    // The tenant opens more branches after requesting.
    Branch::factory()->forTenant($owner->tenant)->count(2)->create(['is_active' => true]);

    $this->actingAs($admin, 'platform')->post("/platform/branch-reduction-requests/{$request->id}/approve")->assertStatus(409);

    expect($request->fresh()->status)->toBe('pending');
    expect(Tenant::findOrFail($owner->tenant_id)->branchLimit())->toBe(3);
});

test('super admin can reject a request with a reason, which changes nothing', function () {
    $plan = reductionFlexPlan();
    $owner = tenantOnReductionPlan(2, $plan);
    Branch::factory()->forTenant($owner->tenant)->count(1)->create(['is_active' => true]);
    $admin = PlatformAdmin::factory()->create();
    $this->actingAs($owner, 'web')->post('/branch-reduction-requests', ['requested_branch_count' => 1]);
    $request = BranchReductionRequest::firstOrFail();

    $this->actingAs($admin, 'platform')->post("/platform/branch-reduction-requests/{$request->id}/reject", ['reason' => 'Not eligible'])->assertRedirect();

    expect($request->fresh()->status)->toBe('rejected');
    expect($request->fresh()->decision_reason)->toBe('Not eligible');
    expect(Tenant::findOrFail($owner->tenant_id)->branchLimit())->toBe(2);

    // ...and the tenant can submit a new one now that the old one is decided.
    $this->actingAs($owner, 'web')->post('/branch-reduction-requests', ['requested_branch_count' => 1])->assertRedirect();
});

test('an already-decided request cannot be decided again', function () {
    $plan = reductionFlexPlan();
    $owner = tenantOnReductionPlan(2, $plan);
    Branch::factory()->forTenant($owner->tenant)->count(1)->create(['is_active' => true]);
    $admin = PlatformAdmin::factory()->create();
    $this->actingAs($owner, 'web')->post('/branch-reduction-requests', ['requested_branch_count' => 1]);
    $request = BranchReductionRequest::firstOrFail();

    $this->actingAs($admin, 'platform')->post("/platform/branch-reduction-requests/{$request->id}/approve")->assertRedirect();
    $this->actingAs($admin, 'platform')->post("/platform/branch-reduction-requests/{$request->id}/approve")->assertStatus(409);
    $this->actingAs($admin, 'platform')->post("/platform/branch-reduction-requests/{$request->id}/reject")->assertStatus(409);
});

test('the reduced billing shows up only on the next renewal, not the current period', function () {
    $plan = reductionFlexPlan();
    $owner = tenantOnReductionPlan(3, $plan);
    Branch::factory()->forTenant($owner->tenant)->count(1)->create(['is_active' => true]);
    $admin = PlatformAdmin::factory()->create();
    $this->actingAs($owner, 'web')->post('/branch-reduction-requests', ['requested_branch_count' => 1]);
    $request = BranchReductionRequest::firstOrFail();
    $subscriptionBefore = TenantSubscription::where('tenant_id', $owner->tenant_id)->firstOrFail();
    $endsAtBefore = $subscriptionBefore->ends_at;

    $this->actingAs($admin, 'platform')->post("/platform/branch-reduction-requests/{$request->id}/approve");

    $subscriptionAfter = TenantSubscription::where('tenant_id', $owner->tenant_id)->firstOrFail();
    expect($subscriptionAfter->ends_at->toDateTimeString())->toBe($endsAtBefore->toDateTimeString()); // no refund/change to current period
    expect($subscriptionAfter->branch_count)->toBe(1); // but future renewal quotations price from this now
});

test('only Super Admin can approve or reject, and a guest cannot reach either route', function () {
    $plan = reductionFlexPlan();
    $owner = tenantOnReductionPlan(2, $plan);
    Branch::factory()->forTenant($owner->tenant)->count(1)->create(['is_active' => true]);
    $this->actingAs($owner, 'web')->post('/branch-reduction-requests', ['requested_branch_count' => 1]);
    $request = BranchReductionRequest::firstOrFail();

    $this->actingAs($owner, 'web')->post("/platform/branch-reduction-requests/{$request->id}/approve")->assertRedirect();
    expect($request->fresh()->status)->toBe('pending');

    auth('platform')->logout();
    $this->post("/platform/branch-reduction-requests/{$request->id}/approve")->assertRedirect();
});

test('the platform list shows pending requests and a badge in the sidebar', function () {
    $plan = reductionFlexPlan();
    $owner = tenantOnReductionPlan(2, $plan);
    $owner->tenant->update(['name' => 'Glow Salon']);
    Branch::factory()->forTenant($owner->tenant)->count(1)->create(['is_active' => true]);
    $admin = PlatformAdmin::factory()->create();
    $this->actingAs($owner, 'web')->post('/branch-reduction-requests', ['requested_branch_count' => 1, 'reason' => 'Cutting costs']);

    $this->actingAs($admin, 'platform')->get('/platform/branch-reduction-requests')
        ->assertOk()->assertSee('Glow Salon')->assertSee('Cutting costs')->assertSee('2 &rarr; 1', false);

    $this->actingAs($admin, 'platform')->get('/platform/tenants')->assertOk()->assertSee('Branch Requests');
});
