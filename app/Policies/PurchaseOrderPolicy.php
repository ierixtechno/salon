<?php

namespace App\Policies;

use App\Domain\Core\Models\PurchaseOrder;
use App\Models\User;

/**
 * `update` gates the whole "still managing this PO" surface — place order,
 * cancel, receive goods — the same way InvoicePolicy's `update` covers
 * draft-management (Phase 6). Branch access is checked too, same as
 * Appointment/Invoice policies.
 */
class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('purchase-orders.view');
    }

    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('purchase-orders.view')
            && $user->tenant_id === $purchaseOrder->tenant_id
            && $user->canAccessBranch($purchaseOrder->branch);
    }

    public function create(User $user): bool
    {
        return $user->can('purchase-orders.create');
    }

    public function update(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('purchase-orders.update')
            && $user->tenant_id === $purchaseOrder->tenant_id
            && $user->canAccessBranch($purchaseOrder->branch);
    }
}
