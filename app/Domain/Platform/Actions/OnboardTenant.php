<?php

namespace App\Domain\Platform\Actions;

use App\Domain\Platform\Models\Module;
use App\Domain\Platform\Models\Tenant;
use App\Domain\Platform\Models\TenantModule;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Self-service tenant signup: creates the tenant and owner login only —
 * there is no free trial. The tenant is created 'pending_payment' and
 * stays fully locked out (EnforceSubscriptionAccess) until Super Admin
 * creates its first Quotation and it's paid (PayQuotation flips it to
 * 'active'). The owner CAN log in immediately, so they can see their
 * pending status and eventually pay — see AccountAccessController. Seeds
 * that tenant's own Owner/Manager/Staff roles (roles are team/tenant-scoped
 * — see docs/04-RBAC.md). All-or-nothing (CLAUDE.md §23) — a partial
 * tenant with no owner, or an owner with no roles, must never be left
 * behind.
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
                'status' => 'pending_payment',
                'timezone' => $data['timezone'] ?? config('platform.default_timezone'),
                'currency' => strtoupper($data['currency'] ?? config('platform.default_currency')),
                'billing_state' => $data['billing_state'] ?? null,
                'gstin' => $data['gstin'] ?? null,
            ]);

            // Modules picked at signup are informational only now — they're
            // not enforced (the tenant can't use anything until paid), but
            // they tell Super Admin what to quote this lead for.
            $this->enableModules($tenant, $data['modules']);

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
                'tattoo-consultations.view', 'tattoo-consultations.create', 'tattoo-consultations.update',
                'appointments.view', 'appointments.create', 'appointments.update', 'appointments.cancel',
                'waitlist.view', 'waitlist.create', 'waitlist.update',
                'invoices.view', 'invoices.create', 'payments.create',
                'products.view', 'products.create', 'products.update',
                'inventory.view', 'inventory.adjust',
                'suppliers.view', 'suppliers.create', 'suppliers.update',
                'purchase-orders.view', 'purchase-orders.create', 'purchase-orders.update',
                'packages.view', 'packages.create', 'packages.update', 'packages.sell', 'packages.redeem',
                'memberships.view', 'memberships.create', 'memberships.update', 'memberships.sell',
                'wallet.view', 'wallet.credit', 'loyalty.view',
                'gift-cards.view', 'gift-cards.create',
                'attendance.view', 'attendance.mark', 'attendance.clock-self',
                'leave.view', 'leave.request', 'leave.approve',
                'commission.view',
                'expenses.view', 'expenses.create', 'expenses.approve', 'expense-categories.manage',
                'cash-register.view', 'cash-register.manage',
                'marketing.templates.manage', 'marketing.segments.manage',
                'marketing.campaigns.view', 'marketing.campaigns.create', 'marketing.campaigns.send',
                'marketing.automations.manage',
                'reports.view',
            ])
        );

        $staff = Role::create(['name' => 'Staff', 'guard_name' => 'web']);
        $staff->syncPermissions(
            $allPermissions->whereIn('name', [
                'customers.view', 'customers.create', 'services.view',
                'salon-consultations.view', 'salon-consultations.create',
                'beauty-consultations.view', 'beauty-consultations.create',
                'spa-consultations.view', 'spa-consultations.create',
                'tattoo-consultations.view', 'tattoo-consultations.create',
                'appointments.view', 'appointments.create', 'appointments.update',
                'waitlist.view', 'waitlist.create',
                'products.view', 'inventory.view',
                'packages.view', 'memberships.view', 'wallet.view', 'loyalty.view', 'gift-cards.view',
                'attendance.clock-self', 'leave.request',
                'expenses.create', 'cash-register.view', 'cash-register.manage',
            ])
        );

        $user->assignRole($owner);

        return $user;
    }
}
