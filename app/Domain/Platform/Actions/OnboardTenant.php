<?php

namespace App\Domain\Platform\Actions;

use App\Domain\Platform\Models\Module;
use App\Domain\Platform\Models\SubscriptionPlan;
use App\Domain\Platform\Models\Tenant;
use App\Domain\Platform\Models\TenantModule;
use App\Domain\Platform\Models\TenantSubscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Self-service tenant signup: creates the tenant, starts its trial
 * subscription, enables the modules the signup chose, seeds that tenant's
 * own Owner/Manager/Staff roles (roles are team/tenant-scoped — see
 * docs/04-RBAC.md), and creates the owner user. All-or-nothing (CLAUDE.md
 * §23) — a partial tenant with no owner, or an owner with no roles, must
 * never be left behind.
 *
 * Super Admin retains full authority to change module/plan assignment
 * afterward (CLAUDE.md §7) — this is a starting point, not a permanent
 * self-granted entitlement.
 */
class OnboardTenant
{
    public function execute(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $tenant = Tenant::create([
                'name' => $data['business_name'],
                'slug' => $this->uniqueSlug($data['business_name']),
                'status' => 'trial',
                'timezone' => $data['timezone'],
                'currency' => strtoupper($data['currency']),
                'trial_ends_at' => now()->addDays((int) config('platform.trial_days')),
            ]);

            $this->enableModules($tenant, $data['modules']);
            $this->startTrialSubscription($tenant);

            return $this->createOwner($tenant, $data);
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'tenant';
        $slug = $base;
        $suffix = 1;

        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function enableModules(Tenant $tenant, array $moduleCodes): void
    {
        foreach (Module::whereIn('code', $moduleCodes)->get() as $module) {
            TenantModule::create([
                'tenant_id' => $tenant->id,
                'module_id' => $module->id,
                'enabled' => true,
                'enabled_at' => now(),
            ]);
        }
    }

    private function startTrialSubscription(Tenant $tenant): void
    {
        $plan = SubscriptionPlan::where('code', 'trial')->firstOrFail();

        TenantSubscription::create([
            'tenant_id' => $tenant->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'trialing',
            'starts_at' => now(),
            'trial_ends_at' => $tenant->trial_ends_at,
        ]);
    }

    private function createOwner(Tenant $tenant, array $data): User
    {
        // tenant_id is deliberately NOT in User's fillable list (CLAUDE.md
        // §28 — never mass-assignable), so it's set directly here rather
        // than through create(). This is also the one place it can't come
        // from BelongsToTenant's auto-fill: onboarding runs pre-login, so
        // there is no authenticated tenant session to infer it from.
        $user = new User([
            'name' => $data['owner_name'],
            'email' => $data['owner_email'],
            'password' => $data['owner_password'],
            'is_active' => true,
            'all_branches' => true,
        ]);
        $user->tenant_id = $tenant->id;
        $user->save();

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        $allPermissions = Permission::all();

        $owner = Role::create(['name' => 'Owner', 'guard_name' => 'web']);
        $owner->syncPermissions($allPermissions);

        $manager = Role::create(['name' => 'Manager', 'guard_name' => 'web']);
        $manager->syncPermissions(
            $allPermissions->whereIn('name', [
                'branches.view', 'users.view',
                'employees.view', 'employees.update',
                'resources.view', 'resources.create', 'resources.update',
                'customers.view', 'customers.create', 'customers.update', 'customers.deactivate',
                'services.view', 'services.create', 'services.update',
                'salon-consultations.view', 'salon-consultations.create', 'salon-consultations.update',
                'beauty-consultations.view', 'beauty-consultations.create', 'beauty-consultations.update',
                'spa-consultations.view', 'spa-consultations.create', 'spa-consultations.update',
            ])
        );

        $staff = Role::create(['name' => 'Staff', 'guard_name' => 'web']);
        $staff->syncPermissions(
            $allPermissions->whereIn('name', [
                'customers.view', 'customers.create', 'services.view',
                'salon-consultations.view', 'salon-consultations.create',
                'beauty-consultations.view', 'beauty-consultations.create',
                'spa-consultations.view', 'spa-consultations.create',
            ])
        );

        $user->assignRole($owner);

        return $user;
    }
}
