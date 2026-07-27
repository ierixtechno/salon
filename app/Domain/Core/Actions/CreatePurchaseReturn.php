<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Product;
use App\Domain\Core\Models\PurchaseOrder;
use App\Domain\Core\Models\PurchaseReturn;
use App\Domain\Core\Models\Supplier;
use Illuminate\Support\Facades\DB;

/**
 * Decreases stock — one `return` StockMovement per line, referencing the
 * resulting PurchaseReturn. `allowNegative` is NOT set, since returning
 * more of a product than the branch actually has in stock is a genuine
 * data-entry error worth catching, not a legitimate business scenario the
 * way negative service-consumption stock can be (RecordServiceConsumption).
 */
class CreatePurchaseReturn
{
    public function execute(
        Branch $branch,
        Supplier $supplier,
        ?PurchaseOrder $purchaseOrder,
        array $lines,
        ?string $reason,
        ?int $createdBy,
    ): PurchaseReturn {
        return DB::transaction(function () use ($branch, $supplier, $purchaseOrder, $lines, $reason, $createdBy) {
            $return = PurchaseReturn::create([
                'branch_id' => $branch->id,
                'supplier_id' => $supplier->id,
                'purchase_order_id' => $purchaseOrder?->id,
                'reason' => $reason,
                'created_by' => $createdBy,
            ]);

            foreach ($lines as $lineInput) {
                $quantity = round((float) ($lineInput['quantity'] ?? 0), 3);

                if ($quantity <= 0) {
                    continue;
                }

                $product = Product::findOrFail($lineInput['product_id']);

                app(RecordStockMovement::class)->execute(
                    branch: $branch,
                    product: $product,
                    type: 'return',
                    quantity: -$quantity,
                    unitCost: isset($lineInput['unit_cost']) ? (float) $lineInput['unit_cost'] : null,
                    referenceType: 'purchase_return',
                    referenceId: $return->id,
                    performedBy: $createdBy,
                );
            }

            return $return;
        });
    }
}
