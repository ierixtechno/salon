<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\PurchaseOrder;
use App\Domain\Core\Models\Supplier;
use App\Domain\Core\Models\SupplierPayment;

/**
 * A simple outgoing-payment ledger entry — no TDS/TCS deduction (see the
 * create_supplier_payments_table migration comment) and no reconciliation
 * against Cash Register (Phase 10), which doesn't exist yet.
 */
class RecordSupplierPayment
{
    public function execute(
        Supplier $supplier,
        ?PurchaseOrder $purchaseOrder,
        float $amount,
        string $method,
        ?string $reference,
        ?int $paidBy,
    ): SupplierPayment {
        return SupplierPayment::create([
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $purchaseOrder?->id,
            'amount' => $amount,
            'method' => $method,
            'reference' => $reference,
            'paid_by' => $paidBy,
            'paid_at' => now(),
        ]);
    }
}
