<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Product;
use App\Domain\Core\Models\StockMovement;

/**
 * Manual stock correction — covers `adjustment` (count correction, either
 * direction), `damage`, and `expiry` (always a decrease). A single-product
 * action, unlike goods receipt/returns/transfers: real-world stock takes
 * and damage/expiry write-offs are naturally one line at a time, not a
 * multi-line document, so no header record was added for this.
 */
class AdjustStock
{
    private const DECREASE_ONLY_TYPES = ['damage', 'expiry'];

    public function execute(
        Branch $branch,
        Product $product,
        string $type,
        float $quantity,
        ?string $notes,
        ?int $performedBy,
    ): StockMovement {
        abort_unless(in_array($type, ['adjustment', 'damage', 'expiry'], true), 422, 'Invalid adjustment type.');

        $signedQuantity = in_array($type, self::DECREASE_ONLY_TYPES, true) ? -abs($quantity) : $quantity;

        return app(RecordStockMovement::class)->execute(
            branch: $branch,
            product: $product,
            type: $type,
            quantity: $signedQuantity,
            notes: $notes,
            performedBy: $performedBy,
        );
    }
}
