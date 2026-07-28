<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\AuditLog;
use App\Domain\Core\Models\Branch;
use App\Domain\Platform\Models\Module;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The tenant-vs-branch module boundary (CLAUDE.md §8) is enforced earlier,
 * in UpdateBranchModulesRequest's validation (only tenant-enabled module
 * codes are accepted) — this action just persists an already-validated set.
 * Branch module changes are audited per CLAUDE.md §47.
 *
 * tenant_id is passed explicitly (not via AuditLog::record()'s ambient
 * current_tenant_id() helper) since this action can run without a `web`
 * session — e.g. Super Admin tooling or a script.
 */
class UpdateBranchModules
{
    public function execute(Branch $branch, array $moduleCodes): void
    {
        DB::transaction(function () use ($branch, $moduleCodes) {
            foreach (Module::all() as $module) {
                $branch->modules()->syncWithoutDetaching([
                    $module->id => ['enabled' => in_array($module->code, $moduleCodes, true)],
                ]);
                Branch::forgetModuleCache($branch->id, $module->code);
            }

            AuditLog::create([
                'tenant_id' => $branch->tenant_id,
                'branch_id' => $branch->id,
                'user_id' => Auth::guard('web')->id(),
                'action' => 'branch.modules_changed',
                'entity_type' => 'Branch',
                'entity_id' => $branch->id,
                'meta' => ['modules' => $moduleCodes],
            ]);
        });
    }
}
