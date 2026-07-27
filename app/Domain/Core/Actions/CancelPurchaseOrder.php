<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\PurchaseOrder;

class CancelPurchaseOrder
{
    public function execute(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        abort_unless($purchaseOrder->canTransitionTo('cancelled'), 409, "Cannot cancel a purchase order that is currently {$purchaseOrder->status}.");

        $purchaseOrder->status = 'cancelled';
        $purchaseOrder->cancelled_at = now();
        $purchaseOrder->save();

        return $purchaseOrder;
    }
}
