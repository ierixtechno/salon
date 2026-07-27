<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\PurchaseOrder;

class OrderPurchaseOrder
{
    public function execute(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        abort_unless($purchaseOrder->canTransitionTo('ordered'), 409, "Cannot place an order that is currently {$purchaseOrder->status}.");
        abort_if($purchaseOrder->lines()->count() === 0, 422, 'Cannot place an order with no line items.');

        $purchaseOrder->status = 'ordered';
        $purchaseOrder->ordered_at = now();
        $purchaseOrder->save();

        return $purchaseOrder;
    }
}
