<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\GoodsReceipt;
use App\Domain\Core\Models\PurchaseOrder;
use App\Domain\Core\Models\PurchaseOrderLine;
use Illuminate\Support\Facades\DB;

/**
 * Increases stock (CLAUDE.md §46) — one `purchase` StockMovement per line
 * received, referencing the resulting GoodsReceipt. A PO may be received
 * across several GoodsReceipts (partial deliveries); `quantity_received`
 * on each line accumulates, and the PO only reaches `received` once every
 * line is fully received.
 */
class ReceiveGoods
{
    public function execute(
        PurchaseOrder $purchaseOrder,
        array $lines,
        ?string $supplierInvoiceNumber,
        ?float $supplierInvoiceAmount,
        ?int $receivedBy,
    ): GoodsReceipt {
        abort_unless(
            in_array($purchaseOrder->status, ['ordered', 'partially_received'], true),
            409,
            "Cannot receive goods against a purchase order that is currently {$purchaseOrder->status}.",
        );

        return DB::transaction(function () use ($purchaseOrder, $lines, $supplierInvoiceNumber, $supplierInvoiceAmount, $receivedBy) {
            $receipt = GoodsReceipt::create([
                'purchase_order_id' => $purchaseOrder->id,
                'supplier_invoice_number' => $supplierInvoiceNumber,
                'supplier_invoice_amount' => $supplierInvoiceAmount,
                'received_by' => $receivedBy,
                'received_at' => now(),
            ]);

            foreach ($lines as $lineInput) {
                $quantity = round((float) ($lineInput['quantity'] ?? 0), 3);

                if ($quantity <= 0) {
                    continue;
                }

                $line = PurchaseOrderLine::where('purchase_order_id', $purchaseOrder->id)
                    ->findOrFail($lineInput['purchase_order_line_id']);

                $outstanding = round((float) $line->quantity_ordered - (float) $line->quantity_received, 3);
                abort_if($quantity > $outstanding, 409, "Cannot receive more than the outstanding quantity for {$line->product->name}.");

                app(RecordStockMovement::class)->execute(
                    branch: $purchaseOrder->branch,
                    product: $line->product,
                    type: 'purchase',
                    quantity: $quantity,
                    unitCost: (float) $line->unit_cost,
                    referenceType: 'goods_receipt',
                    referenceId: $receipt->id,
                    performedBy: $receivedBy,
                );

                $line->quantity_received = round((float) $line->quantity_received + $quantity, 3);
                $line->save();
            }

            $allReceived = $purchaseOrder->lines()->get()->every(
                fn (PurchaseOrderLine $l) => round((float) $l->quantity_received, 3) >= round((float) $l->quantity_ordered, 3)
            );

            $purchaseOrder->status = $allReceived ? 'received' : 'partially_received';
            $purchaseOrder->save();

            return $receipt;
        });
    }
}
