<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\PurchaseOrder;
use App\Domain\Core\Models\Supplier;
use Illuminate\Support\Facades\DB;

/**
 * Creates a draft PO with its lines in one step — unlike Invoice (Phase 6)
 * where lines are added one at a time to an already-created draft, a
 * purchase order's product list is naturally known upfront (what you're
 * ordering from the supplier), so this takes the whole line set at once.
 */
class CreatePurchaseOrder
{
    public function execute(Branch $branch, Supplier $supplier, array $lines, ?string $expectedDate, ?string $notes, ?int $createdBy): PurchaseOrder
    {
        return DB::transaction(function () use ($branch, $supplier, $lines, $expectedDate, $notes, $createdBy) {
            // status is deliberately not in $fillable (CLAUDE.md §28), and
            // Eloquent doesn't reload the DB column default after insert —
            // set it directly or canTransitionTo() sees a null status.
            $order = new PurchaseOrder([
                'branch_id' => $branch->id,
                'supplier_id' => $supplier->id,
                'expected_date' => $expectedDate,
                'notes' => $notes,
                'created_by' => $createdBy,
            ]);
            $order->status = 'draft';
            $order->save();

            foreach ($lines as $line) {
                $order->lines()->create([
                    'product_id' => $line['product_id'],
                    'quantity_ordered' => $line['quantity'],
                    'unit_cost' => $line['unit_cost'],
                ]);
            }

            return $order;
        });
    }
}
