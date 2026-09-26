<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Service;
use App\Domain\Platform\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Every tenant gets one branch by default; more come from a subscription
 * plan with a higher `branch_limit` (see Tenant::branchLimit). Deactivated
 * branches don't count, so a tenant can retire one and open another.
 *
 * The tenant row is locked for the count-then-insert so two simultaneous
 * requests cannot both take the last slot (CLAUDE.md §24).
 */
class CreateBranch
{
    public function execute(array $data): Branch
    {
        return DB::transaction(function () use ($data) {
            $tenant = Tenant::whereKey(current_tenant_id())->lockForUpdate()->firstOrFail();

            $limit = $tenant->branchLimit();

            if (Branch::where('is_active', true)->count() >= $limit) {
                throw ValidationException::withMessages([
                    'branch_limit' => "Your plan includes {$limit} ".str('branch')->plural($limit).' and all are in use. Upgrade your plan to add more branches.',
                ]);
            }

            $branch = Branch::create($data);

            // Existing services are sellable at a new branch by default (same
            // reasoning as CreateService); restrict from the service's Branches tab.
            $rows = Service::pluck('id')->map(fn ($id) => [
                'service_id' => $id, 'branch_id' => $branch->id, 'is_available' => true,
                'created_at' => now(), 'updated_at' => now(),
            ])->all();
            if ($rows) {
                DB::table('service_branch')->insert($rows);
            }

            return $branch;
        });
    }
}
