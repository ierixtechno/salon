<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\Service;

/**
 * Sync-style update, same "updateOrCreate + delete-not-kept" pattern as
 * UpdateServiceVariants (Phase 4) — keyed on product_id since a service
 * has at most one consumable row per product (unique constraint).
 */
class UpdateServiceConsumables
{
    public function execute(Service $service, array $consumables): void
    {
        $keptProductIds = [];

        foreach ($consumables as $row) {
            $consumable = $service->consumables()->updateOrCreate(
                ['product_id' => $row['product_id']],
                ['quantity_per_use' => $row['quantity_per_use']],
            );
            $keptProductIds[] = $consumable->product_id;
        }

        $service->consumables()->whereNotIn('product_id', $keptProductIds)->delete();
    }
}
