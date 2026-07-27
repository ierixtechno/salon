<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\Customer;
use App\Domain\Core\Models\WalletTransaction;

/**
 * Manual top-up (staff-recorded, e.g. cash handed over for wallet credit)
 * or a refund credit (ProcessRefund). Ledger-only — never a mutable
 * balance column (CLAUDE.md §20).
 */
class CreditWallet
{
    public function execute(
        Customer $customer,
        float $amount,
        ?string $reason,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $createdBy = null,
    ): WalletTransaction {
        abort_if($amount <= 0, 422, 'Credit amount must be greater than zero.');

        return WalletTransaction::create([
            'customer_id' => $customer->id,
            'type' => 'credit',
            'amount' => $amount,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'reason' => $reason,
            'created_by' => $createdBy,
        ]);
    }
}
