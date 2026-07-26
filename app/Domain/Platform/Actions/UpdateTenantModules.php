<?php

namespace App\Domain\Platform\Actions;

use App\Domain\Platform\Models\Module;
use App\Domain\Platform\Models\Tenant;
use App\Domain\Platform\Models\TenantModule;
use Illuminate\Support\Facades\DB;

/**
 * Disabling a module here never deletes historical data (CLAUDE.md §7) — it
 * only flips a boolean. When a module is disabled at the tenant level, any
 * branch that had it enabled is cascaded off too, since a branch's modules
 * must always be a subset of its tenant's (CLAUDE.md §8).
 */
class UpdateTenantModules
{
    public function execute(Tenant $tenant, array $moduleCodes): void
    {
        DB::transaction(function () use ($tenant, $moduleCodes) {
            foreach (Module::all() as $module) {
                $enabled = in_array($module->code, $moduleCodes, true);

                TenantModule::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'module_id' => $module->id],
                    ['enabled' => $enabled, 'enabled_at' => $enabled ? now() : null],
                );

                if (! $enabled) {
                    DB::table('branch_modules')
                        ->join('branches', 'branches.id', '=', 'branch_modules.branch_id')
                        ->where('branches.tenant_id', $tenant->id)
                        ->where('branch_modules.module_id', $module->id)
                        ->update(['branch_modules.enabled' => false]);
                }
            }
        });
    }
}
