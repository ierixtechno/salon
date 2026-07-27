<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\BranchStock;
use App\Domain\Core\Models\Product;
use App\Domain\Core\Models\StockMovement;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The single choke point every stock-changing operation in this codebase
 * goes through (goods receipt, purchase return, transfer, adjustment,
 * service consumption) — CLAUDE.md §46: every stock change produces a
 * ledger row, and the branch's cached balance is reconstructable from it.
 *
 * Concurrency: the BranchStock row for this (branch, product) pair is
 * locked with `lockForUpdate()` before the balance is read+adjusted — the
 * same "lock a proxy row" pattern used throughout this codebase
 * (BookAppointment, CheckoutSale), since two simultaneous stock changes for
 * the same product at the same branch must be serialized, not lost to a
 * race. "Insert, ignore if it already exists, then lock+read" handles the
 * first-ever movement for a (branch, product) pair race-safely too.
 */
class RecordStockMovement
{
    public function execute(
        Branch $branch,
        Product $product,
        string $type,
        float $quantity,
        ?float $unitCost = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null,
        ?int $performedBy = null,
        bool $allowNegative = false,
        ?\DateTimeInterface $occurredAt = null,
    ): StockMovement {
        return DB::transaction(function () use (
            $branch, $product, $type, $quantity, $unitCost,
            $referenceType, $referenceId, $notes, $performedBy, $allowNegative, $occurredAt,
        ) {
            try {
                $newStock = new BranchStock(['branch_id' => $branch->id, 'product_id' => $product->id]);
                $newStock->quantity = 0;
                $newStock->save();
            } catch (UniqueConstraintViolationException) {
                // Already exists — fine, the lock+read below picks it up.
            }

            $stock = BranchStock::where('branch_id', $branch->id)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->firstOrFail();

            $newQuantity = round((float) $stock->quantity + $quantity, 3);

            abort_if(! $allowNegative && $newQuantity < 0, 409, 'This would result in negative stock.');

            $stock->quantity = $newQuantity;
            $stock->save();

            return StockMovement::create([
                'branch_id' => $branch->id,
                'product_id' => $product->id,
                'type' => $type,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'notes' => $notes,
                'performed_by' => $performedBy,
                'occurred_at' => $occurredAt ?? now(),
            ]);
        });
    }
}
