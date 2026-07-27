<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Product;
use App\Domain\Core\Models\StockTransfer;
use Illuminate\Support\Facades\DB;

/**
 * CLAUDE.md §51 names this action explicitly ("TransferStock"). Moves
 * stock between two branches atomically: a `transfer_out` movement at the
 * source (blocked if it would go negative — you can't ship what you don't
 * have) and a `transfer_in` movement at the destination, both referencing
 * the same StockTransfer header.
 */
class TransferStock
{
    public function execute(Branch $fromBranch, Branch $toBranch, array $lines, ?string $notes, ?int $createdBy): StockTransfer
    {
        abort_if($fromBranch->id === $toBranch->id, 422, 'Source and destination branch must be different.');

        return DB::transaction(function () use ($fromBranch, $toBranch, $lines, $notes, $createdBy) {
            $transfer = StockTransfer::create([
                'from_branch_id' => $fromBranch->id,
                'to_branch_id' => $toBranch->id,
                'notes' => $notes,
                'created_by' => $createdBy,
                'transferred_at' => now(),
            ]);

            foreach ($lines as $lineInput) {
                $quantity = round((float) ($lineInput['quantity'] ?? 0), 3);

                if ($quantity <= 0) {
                    continue;
                }

                $product = Product::findOrFail($lineInput['product_id']);

                app(RecordStockMovement::class)->execute(
                    branch: $fromBranch,
                    product: $product,
                    type: 'transfer_out',
                    quantity: -$quantity,
                    referenceType: 'stock_transfer',
                    referenceId: $transfer->id,
                    performedBy: $createdBy,
                );

                app(RecordStockMovement::class)->execute(
                    branch: $toBranch,
                    product: $product,
                    type: 'transfer_in',
                    quantity: $quantity,
                    referenceType: 'stock_transfer',
                    referenceId: $transfer->id,
                    performedBy: $createdBy,
                );
            }

            return $transfer;
        });
    }
}
