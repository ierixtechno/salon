<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

/**
 * Permission *definitions* are global platform vocabulary, shared across
 * every tenant (unlike roles, which are team/tenant-scoped — see
 * docs/04-RBAC.md). This seeder only ever adds to the catalog as new
 * modules ship; it never assigns permissions to any tenant's roles — that
 * happens per-tenant in OnboardTenant / role management.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Phase 1: platform/tenant administration
            'tenant.settings.manage',
            'branches.view',
            'branches.create',
            'branches.update',
            'branches.delete',
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'roles.manage',
            'subscription.view',

            // Phase 2: employees and resources
            'employees.view',
            'employees.create',
            'employees.update',
            'employees.delete',
            'resources.view',
            'resources.create',
            'resources.update',
            'resources.delete',

            // Phase 3: customers
            'customers.view',
            'customers.create',
            'customers.update',
            'customers.deactivate',
            'customers.erase',

            // Phase 4: service catalogue
            'services.view',
            'services.create',
            'services.update',
            'services.delete',

            // Phase 4: vertical consultation profiles (hair/skin/spa/tattoo)
            'salon-consultations.view',
            'salon-consultations.create',
            'salon-consultations.update',
            'beauty-consultations.view',
            'beauty-consultations.create',
            'beauty-consultations.update',
            'spa-consultations.view',
            'spa-consultations.create',
            'spa-consultations.update',
            'tattoo-consultations.view',
            'tattoo-consultations.create',
            'tattoo-consultations.update',

            // Phase 5: appointment engine
            'appointments.view',
            'appointments.create',
            'appointments.update',
            'appointments.cancel',
            'waitlist.view',
            'waitlist.create',
            'waitlist.update',

            // Phase 6: POS / invoicing / payments / refunds
            'invoices.view',
            'invoices.create',
            'invoices.void',
            'payments.create',
            'refunds.create',

            // Phase 7: products, inventory, suppliers, purchasing
            'products.view',
            'products.create',
            'products.update',
            'products.delete',
            'inventory.view',
            'inventory.adjust',
            'suppliers.view',
            'suppliers.create',
            'suppliers.update',
            'purchase-orders.view',
            'purchase-orders.create',
            'purchase-orders.update',
            'supplier-payments.create',

            // Phase 8: packages, memberships, loyalty, wallet, gift cards
            'packages.view',
            'packages.create',
            'packages.update',
            'packages.delete',
            'packages.sell',
            'packages.redeem',
            'memberships.view',
            'memberships.create',
            'memberships.update',
            'memberships.delete',
            'memberships.sell',
            'wallet.view',
            'wallet.credit',
            'loyalty.view',
            'gift-cards.view',
            'gift-cards.create',
            'gift-cards.cancel',

            // Phase 9: attendance, leave, commission
            'attendance.view',
            'attendance.mark',
            'attendance.clock-self',
            'leave.view',
            'leave.request',
            'leave.approve',
            'leave-types.manage',
            'commission.view',
            'commission.manage',

            // Phase 10: expenses, cash register
            'expenses.view',
            'expenses.create',
            'expenses.approve',
            'expense-categories.manage',
            'cash-register.view',
            'cash-register.manage',

            // Phase 11: notifications, marketing
            'marketing.templates.manage',
            'marketing.segments.manage',
            'marketing.campaigns.view',
            'marketing.campaigns.create',
            'marketing.campaigns.send',
            'marketing.automations.manage',

            // Phase 12: reports
            'reports.view',

            // Phase 14: tenant data export
            'data-export.request',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }
}
