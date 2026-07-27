<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\Package;

/**
 * The package's recipe is a true BelongsToMany pivot (package_services),
 * so Eloquent's sync() already gives add/update/remove-not-kept in one
 * call — no need for the updateOrCreate-then-delete pattern used for
 * ServiceConsumable's plain HasMany (Phase 7).
 */
class UpdatePackageServices
{
    public function execute(Package $package, array $items): void
    {
        $syncData = [];
        foreach ($items as $row) {
            $syncData[$row['service_id']] = ['quantity' => $row['quantity']];
        }

        $package->services()->sync($syncData);
    }
}
