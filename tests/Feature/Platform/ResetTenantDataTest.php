<?php

use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Customer;
use App\Domain\Core\Models\ExpenseCategory;
use App\Domain\Core\Scopes\TenantScope;
use App\Domain\Platform\Actions\CreateQuotation;
use App\Domain\Platform\Actions\PayQuotation;
use App\Domain\Platform\Models\Module;
use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Platform\Models\PlatformInvoice;
use App\Domain\Platform\Models\Quotation;
use App\Domain\Platform\Models\SubscriptionPlan;
use App\Domain\Platform\Models\Tenant;
use App\Domain\Platform\Models\TenantSubscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

function populatedTenant(): User
{
    $owner = onboard();
    Branch::factory()->forTenant($owner->tenant)->create();
    Customer::factory()->forTenant($owner->tenant)->create();

    $plan = SubscriptionPlan::where('code', 'growth')->firstOrFail();
    $paid = app(CreateQuotation::class)->execute($owner->tenant, $plan, createdBy: null);
    app(PayQuotation::class)->execute($paid, 'upi', 'UTR1');
    app(CreateQuotation::class)->execute($owner->tenant->fresh(), $plan, createdBy: null); // one still pending

    return $owner;
}

test('the reset removes every tenant and everything that belongs to one', function () {
    populatedTenant();
    populatedTenant();

    expect(Tenant::count())->toBeGreaterThanOrEqual(2);

    $this->artisan('platform:reset-tenants', ['--force' => true, '--no-backup' => true])->assertSuccessful();

    expect(Tenant::count())->toBe(0);
    expect(User::withoutGlobalScopes()->count())->toBe(0);
    expect(Branch::withoutGlobalScopes()->count())->toBe(0);
    expect(Customer::withoutGlobalScopes()->count())->toBe(0);
    expect(ExpenseCategory::withoutGlobalScopes()->count())->toBe(0);
    expect(Quotation::count())->toBe(0);
    expect(PlatformInvoice::count())->toBe(0);
    expect(TenantSubscription::count())->toBe(0);
    expect(DB::table('roles')->count())->toBe(0);
    expect(DB::table('model_has_roles')->count())->toBe(0);
    expect(DB::table('platform_sequences')->count())->toBe(0);
    expect(DB::table('payments')->count())->toBe(0);
});

test('the reset keeps Super Admin, plans, modules and permissions', function () {
    populatedTenant();
    $admin = PlatformAdmin::factory()->create();
    $plans = SubscriptionPlan::count();
    $modules = Module::count();
    $permissions = Permission::count();

    $this->artisan('platform:reset-tenants', ['--force' => true, '--no-backup' => true])->assertSuccessful();

    expect(PlatformAdmin::whereKey($admin->id)->exists())->toBeTrue();
    expect(SubscriptionPlan::count())->toBe($plans);
    expect(Module::count())->toBe($modules);
    expect(Permission::count())->toBe($permissions);
});

test('after the reset a brand-new tenant can be created and gets a fresh quotation number', function () {
    populatedTenant();
    $this->artisan('platform:reset-tenants', ['--force' => true, '--no-backup' => true])->assertSuccessful();

    $owner = onboard();
    $plan = SubscriptionPlan::where('code', 'growth')->firstOrFail();
    $quotation = app(CreateQuotation::class)->execute($owner->tenant, $plan, createdBy: null);

    expect(Tenant::count())->toBe(1);
    expect($quotation->quotation_number)->toEndWith('000001');
});

test('the reset does nothing unless RESET is typed', function () {
    populatedTenant();
    $before = Tenant::count();

    $this->artisan('platform:reset-tenants', ['--no-backup' => true])
        ->expectsQuestion('Type RESET to continue', 'yes')
        ->assertFailed();

    expect(Tenant::count())->toBe($before);
});

test('typing RESET runs it', function () {
    populatedTenant();

    $this->artisan('platform:reset-tenants', ['--no-backup' => true])
        ->expectsQuestion('Type RESET to continue', 'RESET')
        ->assertSuccessful();

    expect(Tenant::count())->toBe(0);
});

// ---------------------------------------------------- starter expense categories

test('a new tenant starts with expense categories, so the expense form has options', function () {
    $owner = onboard();

    $names = ExpenseCategory::withoutGlobalScopes()->where('tenant_id', $owner->tenant_id)->pluck('name')->all();
    expect($names)->toContain('Rent')->toContain('Miscellaneous');

    $branch = Branch::factory()->forTenant($owner->tenant)->create();
    $this->actingAs($owner)->get('/expenses/create')->assertOk()->assertSee('Rent')->assertDontSee('No categories yet');
});

test('starter categories are per tenant', function () {
    $a = onboard();
    $b = onboard();

    expect(ExpenseCategory::withoutGlobalScope(TenantScope::class)->where('tenant_id', $a->tenant_id)->count())->toBe(8);
    expect(ExpenseCategory::withoutGlobalScope(TenantScope::class)->where('tenant_id', $b->tenant_id)->count())->toBe(8);
});

test('with no categories the expense form explains what to do', function () {
    $owner = onboard();
    ExpenseCategory::withoutGlobalScopes()->where('tenant_id', $owner->tenant_id)->delete();

    $this->actingAs($owner)->get('/expenses/create')->assertOk()->assertSee('No categories yet')->assertSee(route('expense-categories.create'), false);
});
