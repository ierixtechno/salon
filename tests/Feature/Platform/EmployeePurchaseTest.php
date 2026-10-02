<?php

use App\Domain\Platform\Actions\PayQuotation;
use App\Domain\Platform\Models\EmployeeReductionRequest;
use App\Domain\Platform\Models\Module;
use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Platform\Models\PlatformAuditLog;
use App\Domain\Platform\Models\Quotation;
use App\Domain\Platform\Models\SubscriptionPlan;
use App\Domain\Platform\Models\Tenant;
use App\Domain\Platform\Models\TenantSubscription;

/** 1 branch, 5 users included, ₹100 per extra employee, capped at 20 overall. */
function staffPlan(array $overrides = []): SubscriptionPlan
{
    Module::firstOrCreate(['code' => 'salon'], ['name' => 'Salon']);
    $plan = SubscriptionPlan::create(array_merge([
        'code' => 'staff-'.uniqid(), 'name' => 'Staff Plan', 'price' => 1000, 'billing_interval' => 'monthly',
        'branch_limit' => 1, 'additional_branch_price' => 500, 'max_branches' => 3,
        'users_included' => 5, 'users_per_additional_branch' => 2,
        'additional_employee_price' => 100, 'max_users' => 20, 'is_active' => true,
    ], $overrides));
    $plan->modules()->sync(Module::where('code', 'salon')->pluck('id'));

    return $plan;
}

function tenantOnStaffPlan(SubscriptionPlan $plan, int $extra = 0, int $daysLeft = 15)
{
    $owner = onboard(['subscription_plan_code' => $plan->code, 'subscription_ends_at' => now()->addDays($daysLeft)->startOfDay()]);
    TenantSubscription::where('tenant_id', $owner->tenant_id)->update(['extra_user_count' => $extra]);
    Tenant::forgetSubscriptionCache($owner->tenant_id);

    return $owner;
}

// ------------------------------------------------ plan maths

test('total user limit combines branch-derived users and purchased slots, capped by max_users', function () {
    $plan = staffPlan();

    expect($plan->totalUserLimit(1, 0))->toBe(5);
    expect($plan->totalUserLimit(1, 4))->toBe(9);
    expect($plan->totalUserLimit(2, 4))->toBe(11); // 5 + 2 per extra branch + 4
    expect($plan->totalUserLimit(1, 100))->toBe(20); // capped
    expect(staffPlan(['users_included' => null])->totalUserLimit(1, 5))->toBeNull(); // unlimited
    expect($plan->sellsExtraEmployees())->toBeTrue();
    expect(staffPlan(['additional_employee_price' => 0])->sellsExtraEmployees())->toBeFalse();
});

// ------------------------------------------------ plan form (Super Admin)

test('super admin can save the employee price and cap on a plan', function () {
    $admin = PlatformAdmin::factory()->create();
    $plan = staffPlan(['additional_employee_price' => 0, 'max_users' => null]);

    $this->actingAs($admin, 'platform')->put("/platform/subscription-plans/{$plan->id}", [
        'name' => $plan->name, 'price' => 1000, 'billing_interval' => 'monthly',
        'branch_limit' => 1, 'additional_branch_price' => 500, 'max_branches' => 3,
        'users_included' => 5, 'users_per_additional_branch' => 2,
        'additional_employee_price' => 150, 'max_users' => 12,
        'features' => [], 'modules' => ['salon'],
    ])->assertRedirect();

    expect((float) $plan->fresh()->additional_employee_price)->toBe(150.0);
    expect($plan->fresh()->max_users)->toBe(12);
});

test('a blank employee price saves as 0 and max_users below users_included is rejected', function () {
    $admin = PlatformAdmin::factory()->create();
    $plan = staffPlan();
    $base = [
        'name' => $plan->name, 'price' => 1000, 'billing_interval' => 'monthly',
        'branch_limit' => 1, 'additional_branch_price' => 500, 'max_branches' => 3,
        'users_included' => 5, 'users_per_additional_branch' => 2,
        'features' => [], 'modules' => ['salon'],
    ];

    $this->actingAs($admin, 'platform')->put("/platform/subscription-plans/{$plan->id}", $base + ['additional_employee_price' => '', 'max_users' => ''])->assertRedirect();
    expect((float) $plan->fresh()->additional_employee_price)->toBe(0.0);
    expect($plan->fresh()->max_users)->toBeNull();

    $this->actingAs($admin, 'platform')->put("/platform/subscription-plans/{$plan->id}", $base + ['additional_employee_price' => 100, 'max_users' => 3])
        ->assertSessionHasErrors('max_users');
});

// ------------------------------------------------ self-service purchase

test('a tenant can buy extra employees, pays pro rata, and the limit rises once paid', function () {
    $plan = staffPlan();
    $owner = tenantOnStaffPlan($plan);

    $this->actingAs($owner, 'web')->post('/billing/employees', ['additional' => 3])->assertRedirect();

    $quotation = Quotation::where('tenant_id', $owner->tenant_id)->firstOrFail();
    expect($quotation->extra_user_count)->toBe(3);
    expect((float) $quotation->amount)->toBeGreaterThan(0)->toBeLessThanOrEqual(300.0);
    expect(Tenant::findOrFail($owner->tenant_id)->userLimit())->toBe(5); // not until paid

    app(PayQuotation::class)->execute($quotation, 'upi', 'UTR-EMP-1');

    $tenant = Tenant::findOrFail($owner->tenant_id);
    expect($tenant->currentSubscription()->currentExtraUserCount())->toBe(3);
    expect($tenant->currentSubscription()->currentBranchCount())->toBe(1); // branches untouched
    expect($tenant->userLimit())->toBe(8);
});

test('buying again adds to the slots already purchased', function () {
    $owner = tenantOnStaffPlan(staffPlan(), extra: 2);

    $this->actingAs($owner, 'web')->post('/billing/employees', ['additional' => 3])->assertRedirect();

    expect(Quotation::where('tenant_id', $owner->tenant_id)->firstOrFail()->extra_user_count)->toBe(5);
});

test('employee purchase is refused when the plan does not sell it, is unlimited, or would pass the cap', function () {
    $owner = tenantOnStaffPlan(staffPlan(['additional_employee_price' => 0]));
    $this->actingAs($owner, 'web')->post('/billing/employees', ['additional' => 1])->assertStatus(422);

    $owner = tenantOnStaffPlan(staffPlan(['users_included' => null]));
    $this->actingAs($owner, 'web')->post('/billing/employees', ['additional' => 1])->assertStatus(422);

    $owner = tenantOnStaffPlan(staffPlan()); // 5 + 16 = 21 > 20
    $this->actingAs($owner, 'web')->post('/billing/employees', ['additional' => 16])->assertStatus(422);

    expect(Quotation::count())->toBe(0);
});

test('employee purchase is refused with a pending quotation or when renewal is imminent', function () {
    $owner = tenantOnStaffPlan(staffPlan());
    $this->actingAs($owner, 'web')->post('/billing/employees', ['additional' => 1])->assertRedirect();
    $this->actingAs($owner, 'web')->post('/billing/employees', ['additional' => 1])->assertStatus(409);

    $soon = tenantOnStaffPlan(staffPlan(), daysLeft: 0);
    $this->actingAs($soon, 'web')->post('/billing/employees', ['additional' => 1])->assertStatus(422);
});

test('purchased slots survive a branch purchase', function () {
    $plan = staffPlan();
    $owner = tenantOnStaffPlan($plan, extra: 4);

    $this->actingAs($owner, 'web')->post('/billing/branches', ['additional' => 1])->assertRedirect();
    $quotation = Quotation::where('tenant_id', $owner->tenant_id)->firstOrFail();
    app(PayQuotation::class)->execute($quotation, 'upi', 'UTR-BR-1');

    $tenant = Tenant::findOrFail($owner->tenant_id);
    expect($tenant->currentSubscription()->currentExtraUserCount())->toBe(4);
    expect($tenant->userLimit())->toBe(5 + 2 + 4);
});

test('the billing page shows the staff card only for plans that sell employees', function () {
    $owner = tenantOnStaffPlan(staffPlan());
    $this->actingAs($owner, 'web')->get('/billing/plans')->assertOk()->assertSee('Need more staff?');

    $other = tenantOnStaffPlan(staffPlan(['additional_employee_price' => 0]));
    $this->actingAs($other, 'web')->get('/billing/plans')->assertOk()->assertDontSee('Need more staff?');
});

// ------------------------------------------------ reduction flow

test('a tenant can request an employee reduction and sees it pending', function () {
    $owner = tenantOnStaffPlan(staffPlan(), extra: 4);

    $this->actingAs($owner, 'web')->post('/employee-reduction-requests', ['requested_extra_user_count' => 1, 'reason' => 'Downsizing'])->assertRedirect();

    $request = EmployeeReductionRequest::firstOrFail();
    expect($request->current_extra_user_count)->toBe(4);
    expect($request->requested_extra_user_count)->toBe(1);
    expect($request->status)->toBe('pending');

    $this->actingAs($owner, 'web')->get('/employees')->assertOk()->assertSee('Request pending');
    $this->actingAs($owner, 'web')->post('/employee-reduction-requests', ['requested_extra_user_count' => 0])->assertStatus(409);
});

test('employee reduction validations: lower than current, not negative, active staff must fit', function () {
    $owner = tenantOnStaffPlan(staffPlan(), extra: 2);

    $this->actingAs($owner, 'web')->post('/employee-reduction-requests', ['requested_extra_user_count' => 2])->assertStatus(422);
    $this->actingAs($owner, 'web')->post('/employee-reduction-requests', ['requested_extra_user_count' => -1])->assertSessionHasErrors('requested_extra_user_count');

    // 7 allowed now; add 6 more active staff (owner counts too) so 0 extra (limit 5) no longer fits.
    for ($i = 0; $i < 6; $i++) {
        app(App\Domain\Core\Actions\CreateEmployee::class)->execute($owner->tenant, [
            'name' => "Staff {$i}", 'email' => "s{$i}@glow.test", 'password' => 'password123',
            'employment_type' => 'full_time', 'role' => 'Staff', 'all_branches' => true, 'branches' => [],
        ]);
    }
    $this->actingAs($owner, 'web')->post('/employee-reduction-requests', ['requested_extra_user_count' => 0])->assertStatus(422);

    expect(EmployeeReductionRequest::count())->toBe(0);
});

test('a user without billing permission cannot request an employee reduction', function () {
    $owner = tenantOnStaffPlan(staffPlan(), extra: 2);
    $staff = app(App\Domain\Core\Actions\CreateEmployee::class)->execute($owner->tenant, [
        'name' => 'Sam', 'email' => 'sam@glow.test', 'password' => 'password123',
        'employment_type' => 'full_time', 'role' => 'Staff', 'all_branches' => true, 'branches' => [],
    ]);

    $this->actingAs($staff, 'web')->post('/employee-reduction-requests', ['requested_extra_user_count' => 0])->assertForbidden();
});

test('super admin approves an employee reduction: slots drop, limit falls, audit written', function () {
    $owner = tenantOnStaffPlan(staffPlan(), extra: 4);
    $admin = PlatformAdmin::factory()->create();
    $this->actingAs($owner, 'web')->post('/employee-reduction-requests', ['requested_extra_user_count' => 1]);
    $request = EmployeeReductionRequest::firstOrFail();

    $this->actingAs($admin, 'platform')->post("/platform/employee-reduction-requests/{$request->id}/approve")->assertRedirect();

    expect($request->fresh()->status)->toBe('approved');
    expect($request->fresh()->decided_by)->toBe($admin->id);
    $tenant = Tenant::findOrFail($owner->tenant_id);
    expect($tenant->currentSubscription()->currentExtraUserCount())->toBe(1);
    expect($tenant->userLimit())->toBe(6);
    expect(PlatformAuditLog::where('action', 'tenant.employee_reduction_approved')->where('tenant_id', $tenant->id)->exists())->toBeTrue();
});

test('approval is re-checked: refused if the tenant hired more staff after requesting', function () {
    $owner = tenantOnStaffPlan(staffPlan(), extra: 4);
    $admin = PlatformAdmin::factory()->create();
    $this->actingAs($owner, 'web')->post('/employee-reduction-requests', ['requested_extra_user_count' => 0]);
    $request = EmployeeReductionRequest::firstOrFail();

    for ($i = 0; $i < 6; $i++) {
        app(App\Domain\Core\Actions\CreateEmployee::class)->execute($owner->tenant, [
            'name' => "Late {$i}", 'email' => "l{$i}@glow.test", 'password' => 'password123',
            'employment_type' => 'full_time', 'role' => 'Staff', 'all_branches' => true, 'branches' => [],
        ]);
    }

    $this->actingAs($admin, 'platform')->post("/platform/employee-reduction-requests/{$request->id}/approve")->assertStatus(409);

    expect($request->fresh()->status)->toBe('pending');
    expect(Tenant::findOrFail($owner->tenant_id)->currentSubscription()->currentExtraUserCount())->toBe(4);
});

test('super admin can reject with a reason, and a decided request cannot be decided again', function () {
    $owner = tenantOnStaffPlan(staffPlan(), extra: 2);
    $admin = PlatformAdmin::factory()->create();
    $this->actingAs($owner, 'web')->post('/employee-reduction-requests', ['requested_extra_user_count' => 1]);
    $request = EmployeeReductionRequest::firstOrFail();

    $this->actingAs($admin, 'platform')->post("/platform/employee-reduction-requests/{$request->id}/reject", ['reason' => 'Not now'])->assertRedirect();
    expect($request->fresh()->status)->toBe('rejected');
    expect($request->fresh()->decision_reason)->toBe('Not now');
    expect(Tenant::findOrFail($owner->tenant_id)->currentSubscription()->currentExtraUserCount())->toBe(2);

    $this->actingAs($admin, 'platform')->post("/platform/employee-reduction-requests/{$request->id}/approve")->assertStatus(409);
});

test('the platform list and nav badge show pending employee requests', function () {
    $owner = tenantOnStaffPlan(staffPlan(), extra: 2);
    $admin = PlatformAdmin::factory()->create();
    $this->actingAs($owner, 'web')->post('/employee-reduction-requests', ['requested_extra_user_count' => 1]);

    $this->actingAs($admin, 'platform')->get('/platform/employee-reduction-requests')->assertOk()->assertSee($owner->tenant->name);
    $this->actingAs($admin, 'platform')->get('/platform/dashboard')->assertOk()->assertSee('Employee Requests');
});

test('a tenant cannot see or act on another tenant\'s employee reduction request', function () {
    $a = tenantOnStaffPlan(staffPlan(), extra: 2);
    $this->actingAs($a, 'web')->post('/employee-reduction-requests', ['requested_extra_user_count' => 1]);
    $b = tenantOnStaffPlan(staffPlan(), extra: 2);

    $this->actingAs($b, 'web')->get('/employees')->assertOk()->assertDontSee('Request pending');
    $this->actingAs($b, 'web')->post("/platform/employee-reduction-requests/1/approve")->assertRedirect(); // no platform session: bounced to login
    expect(EmployeeReductionRequest::firstOrFail()->status)->toBe('pending');
});

test('purchased employee slots carry into the renewal quotation price', function () {
    $plan = staffPlan();
    $owner = tenantOnStaffPlan($plan, extra: 3);

    $quotation = app(App\Domain\Platform\Actions\CreateQuotation::class)->execute(tenant: Tenant::findOrFail($owner->tenant_id), plan: $plan, createdBy: null);

    expect($quotation->extra_user_count)->toBe(3);
    expect((float) $quotation->amount)->toBe(1000.0 + 300.0);
});
