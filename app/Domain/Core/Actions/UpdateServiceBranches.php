<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\Service;

/**
 * The tenant/branch module boundary (CLAUDE.md §8) is enforced earlier, in
 * UpdateServiceBranchesRequest's validation — this action just persists an
 * already-validated set.
 */
class UpdateServiceBranches
{
    public function execute(Service $service, array $branches): void
    {
        $sync = [];

        foreach ($branches as $row) {
            $sync[$row['branch_id']] = [
                'is_available' => (bool) ($row['is_available'] ?? false),
                'price_override' => $row['price_override'] ?? null,
            ];
        }

        $service->branches()->sync($sync);
    }
}
