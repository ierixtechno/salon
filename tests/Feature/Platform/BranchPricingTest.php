<?php

use App\Domain\Core\Models\Branch;
use App\Domain\Platform\Actions\CreateQuotation;
use App\Domain\Platform\Actions\PayQuotation;
use App\Domain\Platform\Models\Module;
use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Platform\Models\Quotation;
use App\Domain\Platform\Models\SubscriptionPlan;
use App\Domain\Platform\Models\Tenant;
use App\Domain\Platform\Models\TenantSubscription;

/** 1 branch = ₹1999, each additional branch ₹999, at most 4 in total. */
function extraBranchPlan(array $overrides = []): SubscriptionPlan
{
    Module::firstOrCreate(['code' => 'salon'], ['name' => 'Salon']);

    $plan = SubscriptionPlan::create(array_merge([
        'code' => 'salon-flex-'.uniqid(), 'name' => 'Salon Flex', 'price' => 1999, 'billing_interval' => 'monthly',
        'branch_limit' => 1, 'additional_branch_price' => 999, 'max_branches' => 4, 'is_active' => true,
    ], $overrides));
    $plan->modules()->sync(Module::where('code', 'salon')->pluck('id'));

    return $plan;
}

function signupWith(SubscriptionPlan $plan, array $extra = []): array
{
    return array_merge([
        'business_name' => 'Glow Salon', 'subscription_plan_id' => $plan->id,
        'owner_name' => 'Alice', 'owner_phone' => '9876543210', 'owner_email' => 'alice@glow.test',
        'owner_password' => 'password123', 'owner_password_confirmation' => 'password123',
        'billing_state' => 'Haryana',
    ], $extra);
}

// ------------------------------------------------ pricing maths

test('the price for a number of branches is the base price plus each additional branch', function () {
    $plan = extraBranchPlan();

    expect($plan->priceForBranches(1))->toBe(1999.0);
    expect($plan->priceForBranches(2))->toBe(2998.0);
    expect($plan->priceForBranches(4))->toBe(4996.0);
    expect($plan->priceForBranches(null))->toBe(1999.0);
    expect($plan->priceForBranches(0))->toBe(1999.0);   // never below what is included
    expect($plan->priceForBranches(99))->toBe(4996.0);  // never above the maximum
});

test('a plan that sells no additional branches is priced flat and capped at its included branches', function () {
    $plan = extraBranchPlan(['additional_branch_price' => 0, 'max_branches' => null, 'branch_limit' => 2]);

    expect($plan->sellsExtraBranches())->toBeFalse();
    expect($plan->maxBranches())->toBe(2);
    expect($plan->priceForBranches(5))->toBe(1999.0);
});

// ------------------------------------------------ plan management (editable prices)

test('super admin can edit the additional-branch price and maximum on a plan', function () {
    $admin = PlatformAdmin::factory()->create();
    $plan = extraBranchPlan();

    $this->actingAs($admin, 'platform')->get("/platform/subscription-plans/{$plan->id}/edit")
        ->assertOk()->assertSee('Each additional branch')->assertSee('value="999.00"', false);

    $this->actingAs($admin, 'platform')->put("/platform/subscription-plans/{$plan->id}", [
        'name' => $plan->name, 'price' => 2499, 'billing_interval' => 'monthly',
        'branch_limit' => 2, 'additional_branch_price' => 1200, 'max_branches' => 6,
        'is_active' => '1', 'features' => [], 'modules' => ['salon'],
    ])->assertRedirect();

    $plan->refresh();
    expect((float) $plan->price)->toBe(2499.0);
    expect($plan->branch_limit)->toBe(2);
    expect((float) $plan->additional_branch_price)->toBe(1200.0);
    expect($plan->max_branches)->toBe(6);
});

test('a blank additional-branch price means not offered, and the maximum cannot be below the included branches', function () {
    $admin = PlatformAdmin::factory()->create();
    $base = ['code' => 'flex-x', 'name' => 'Flex X', 'price' => 1000, 'billing_interval' => 'monthly', 'features' => [], 'modules' => []];

    $this->actingAs($admin, 'platform')->post('/platform/subscription-plans', $base + ['branch_limit' => 1, 'additional_branch_price' => ''])->assertRedirect();
    expect((float) SubscriptionPlan::where('code', 'flex-x')->firstOrFail()->additional_branch_price)->toBe(0.0);

    $this->actingAs($admin, 'platform')->post('/platform/subscription-plans', ['code' => 'flex-y'] + $base + ['branch_limit' => 3, 'additional_branch_price' => 500, 'max_branches' => 2])
        ->assertSessionHasErrors('max_branches');
});

test('the plan pages show the additional-branch price', function () {
    $admin = PlatformAdmin::factory()->create();
    $owner = onboard();
    $plan = extraBranchPlan();

    $this->actingAs($admin, 'platform')->get('/platform/subscription-plans')->assertOk()->assertSeeText('999 per additional branch');
    $this->actingAs($owner, 'web')->get('/billing/plans')->assertOk()->assertSeeText('999 per additional branch');
});

// ------------------------------------------------ signup

test('signing up for extra branches is quoted at the base price plus the extras, and unlocks that many branches once paid', function () {
    $plan = extraBranchPlan();

    $this->post('/register', signupWith($plan, ['branch_count' => 3]))->assertRedirect(route('login'));

    $tenant = Tenant::where('name', 'Glow Salon')->firstOrFail();
    $quotation = Quotation::where('tenant_id', $tenant->id)->firstOrFail();

    expect($quotation->branch_count)->toBe(3);
    expect((float) $quotation->amount)->toBe(3997.0);                  // 1999 + 2 x 999
    expect((float) $quotation->total_amount)->toBe(round(3997 * 1.18, 2));

    app(PayQuotation::class)->execute($quotation, 'upi', 'UTR1');

    $subscription = TenantSubscription::where('tenant_id', $tenant->id)->firstOrFail();
    expect($subscription->branch_count)->toBe(3);
    expect($tenant->fresh()->branchLimit())->toBe(3);
});

test('signing up without choosing a branch count gets the included branch at the base price', function () {
    $plan = extraBranchPlan();

    $this->post('/register', signupWith($plan))->assertRedirect(route('login'));

    $quotation = Quotation::firstOrFail();
    expect((float) $quotation->amount)->toBe(1999.0);
    expect($quotation->branch_count)->toBe(1);
});

test('asking for more branches than the package allows is refused', function () {
    $plan = extraBranchPlan();
    $flat = extraBranchPlan(['additional_branch_price' => 0, 'max_branches' => null]);

    $this->post('/register', signupWith($plan, ['branch_count' => 9]))->assertSessionHasErrors('branch_count');
    $this->post('/register', signupWith($flat, ['branch_count' => 2]))->assertSessionHasErrors('branch_count');

    expect(Tenant::where('name', 'Glow Salon')->exists())->toBeFalse();
});

test('the branch count is priced on the server — a tampered amount or plan price in the request is ignored', function () {
    $plan = extraBranchPlan();

    $this->post('/register', signupWith($plan, ['branch_count' => 2, 'amount' => 1, 'price' => 1, 'additional_branch_price' => 0]));

    expect((float) Quotation::firstOrFail()->amount)->toBe(2998.0);
});

test('the signup page offers the branch picker and shows the extra-branch price', function () {
    $plan = extraBranchPlan();

    $this->get('/register')->assertOk()->assertSee('branch_count', false)->assertSeeText('999 per additional branch');
});

// ------------------------------------------------ Super Admin

test('super admin can create a tenant with several branches and the quotation reflects them', function () {
    $admin = PlatformAdmin::factory()->create();
    $plan = extraBranchPlan();

    $this->actingAs($admin, 'platform')->post('/platform/tenants', signupWith($plan, ['branch_count' => 4]))->assertRedirect();

    expect((float) Quotation::firstOrFail()->amount)->toBe(4996.0);
});

test('a manual quotation with no amount is priced from the plan and branch count, and an amount still overrides it', function () {
    $admin = PlatformAdmin::factory()->create();
    $plan = extraBranchPlan();
    $owner = onboard();

    $this->actingAs($admin, 'platform')->post('/platform/quotations', ['tenant_id' => $owner->tenant_id, 'subscription_plan_id' => $plan->id, 'branch_count' => 2]);
    $auto = Quotation::where('tenant_id', $owner->tenant_id)->latest('id')->firstOrFail();
    expect((float) $auto->amount)->toBe(2998.0);
    expect($auto->branch_count)->toBe(2);

    $auto->update(['status' => 'cancelled']);
    $this->actingAs($admin, 'platform')->post('/platform/quotations', ['tenant_id' => $owner->tenant_id, 'subscription_plan_id' => $plan->id, 'branch_count' => 2, 'amount' => 2500]);
    expect((float) Quotation::where('tenant_id', $owner->tenant_id)->latest('id')->firstOrFail()->amount)->toBe(2500.0);
});

// ------------------------------------------------ renewals & upgrades

function tenantOnFlexPlan(int $branches, int $daysLeft = 15): array
{
    $plan = extraBranchPlan();
    $owner = onboard(['subscription_plan_code' => $plan->code, 'subscription_ends_at' => now()->addDays($daysLeft)->startOfDay()]);
    $subscription = TenantSubscription::where('tenant_id', $owner->tenant_id)->firstOrFail();
    $subscription->update(['branch_count' => $branches, 'starts_at' => now()->addDays($daysLeft)->subDays(30)->startOfDay()]);
    Tenant::forgetSubscriptionCache($owner->tenant_id);

    return [$owner, $plan, $subscription->fresh()];
}

test('the renewal quotation keeps the branches the tenant already has', function () {
    [$owner, $plan] = tenantOnFlexPlan(3, 5); // renewal reminder threshold for monthly plans

    $this->artisan('subscriptions:process-renewals')->assertSuccessful();

    $renewal = Quotation::where('tenant_id', $owner->tenant_id)->where('status', 'pending')->firstOrFail();
    expect($renewal->branch_count)->toBe(3);
    expect((float) $renewal->amount)->toBe(3997.0);
});

test('a tenant can buy extra branches mid-cycle, pro rata, without moving the renewal date', function () {
    [$owner, $plan, $subscription] = tenantOnFlexPlan(1, 15); // 15 of 30 days left

    $this->actingAs($owner)->post('/billing/branches', ['additional' => 2])->assertRedirect();

    $quotation = Quotation::where('tenant_id', $owner->tenant_id)->where('status', 'pending')->firstOrFail();
    expect($quotation->is_upgrade)->toBeTrue();
    expect($quotation->branch_count)->toBe(3);
    expect((float) $quotation->amount)->toBe(round(2 * 999 / 30 * 15, 2)); // half of 2 extra branches

    $endsAt = $subscription->ends_at;
    $startsAt = $subscription->starts_at;

    app(PayQuotation::class)->execute($quotation, 'upi', 'UTR2');

    $subscription->refresh();
    expect($subscription->branch_count)->toBe(3);
    expect($subscription->ends_at->toDateTimeString())->toBe($endsAt->toDateTimeString());
    expect($subscription->starts_at->toDateTimeString())->toBe($startsAt->toDateTimeString());
    expect(TenantSubscription::where('tenant_id', $owner->tenant_id)->count())->toBe(1);

    // ...and the tenant can now actually open three branches, not four.
    foreach (['A', 'B', 'C'] as $code) {
        $this->actingAs($owner)->post('/branches', ['name' => "Branch {$code}", 'code' => $code])->assertRedirect();
    }
    $this->actingAs($owner)->post('/branches', ['name' => 'Branch D', 'code' => 'D'])->assertSessionHasErrors('branch_limit');
    expect(Branch::count())->toBe(3);
});

test('extra branches cannot exceed the plan maximum, be bought on a plan that does not sell them, or stack on a pending quotation', function () {
    [$owner] = tenantOnFlexPlan(3, 15);

    $this->actingAs($owner)->post('/billing/branches', ['additional' => 5])->assertStatus(422);   // 3 + 5 > 4
    $this->actingAs($owner)->post('/billing/branches', ['additional' => 0])->assertSessionHasErrors('additional');

    $this->actingAs($owner)->post('/billing/branches', ['additional' => 1])->assertRedirect();
    $this->actingAs($owner)->post('/billing/branches', ['additional' => 1])->assertStatus(409);    // one is already pending

    $flat = onboard(['subscription_plan_code' => 'growth', 'subscription_ends_at' => now()->addDays(15)]);
    $this->actingAs($flat)->post('/billing/branches', ['additional' => 1])->assertStatus(422);
});

test('the billing plans page offers extra branches only to a tenant whose plan sells them', function () {
    [$owner] = tenantOnFlexPlan(1, 15);
    $flat = onboard(['subscription_plan_code' => 'growth']);

    $this->actingAs($owner)->get('/billing/plans')->assertOk()->assertSee('Need more branches?')->assertSee(route('billing.branches.add'), false);
    $this->actingAs($flat)->get('/billing/plans')->assertOk()->assertDontSee('Need more branches?');
});

test('a plan upgrade keeps the tenant\'s branches and prices them on the new plan', function () {
    [$owner, $plan] = tenantOnFlexPlan(3, 15);
    $bigger = extraBranchPlan(['price' => 2999, 'additional_branch_price' => 799, 'max_branches' => 10]);

    $this->actingAs($owner)->post(route('billing.plans.upgrade', $bigger))->assertRedirect();

    $quotation = Quotation::where('tenant_id', $owner->tenant_id)->where('status', 'pending')->firstOrFail();
    expect($quotation->branch_count)->toBe(3);

    // new plan at 3 branches: 2999 + 2x799 = 4597; old: 3997; difference 600, half the cycle left
    expect((float) $quotation->amount)->toBe(round((4597 - 3997) / 30 * 15, 2));
});

test('a subscription bought before branch pricing existed still allows the plan\'s included branches', function () {
    $owner = onboard(); // growth plan, no branch_count on its subscription

    expect(Tenant::findOrFail($owner->tenant_id)->branchLimit())->toBe(1);
});

test('a tenant cannot buy branches for another tenant, and a guest cannot buy any', function () {
    [$owner] = tenantOnFlexPlan(1, 15);
    auth()->guard('web')->logout();

    $this->post('/billing/branches', ['additional' => 1])->assertRedirect();
    expect(Quotation::where('tenant_id', $owner->tenant_id)->exists())->toBeFalse();
});
