<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\CashMovement;
use App\Domain\Core\Models\CashRegisterSession;

/**
 * Ledger-only (CLAUDE.md §20/§45) — a session's running total is always
 * reconstructed as opening_cash + sum(amount), never a mutable balance.
 * `$amount` is signed by the caller: positive for cash in, negative for
 * cash out. Used both for manual cash-in/cash-out and automatically by
 * RecordPayment/ProcessRefund/ApproveExpense for cash-method transactions.
 */
class RecordCashMovement
{
    public function execute(
        CashRegisterSession $session,
        string $type,
        float $amount,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $reason = null,
        ?int $createdBy = null,
    ): CashMovement {
        abort_unless(in_array($type, CashMovement::TYPES, true), 422, 'Invalid cash movement type.');
        abort_unless($session->status === 'open', 409, 'Cannot record a cash movement against a closed register session.');
        abort_if($amount == 0, 422, 'Cash movement amount cannot be zero.');

        return CashMovement::create([
            'branch_id' => $session->branch_id,
            'cash_register_session_id' => $session->id,
            'type' => $type,
            'amount' => $amount,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'reason' => $reason,
            'created_by' => $createdBy,
        ]);
    }
}
