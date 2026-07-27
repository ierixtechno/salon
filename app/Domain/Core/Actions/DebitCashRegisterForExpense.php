<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\CashRegisterSession;
use App\Domain\Core\Models\Expense;

/**
 * Silent no-op when the expense isn't cash or there's no open register
 * session for its branch — same safe-default reasoning as loyalty
 * auto-earn (Phase 8) and commission accrual (Phase 9): harmless if
 * skipped, never blocks the approval it's attached to.
 */
class DebitCashRegisterForExpense
{
    public function execute(Expense $expense, ?int $actorId): void
    {
        if ($expense->payment_method !== 'cash') {
            return;
        }

        $session = CashRegisterSession::where('branch_id', $expense->branch_id)->where('status', 'open')->first();
        if (! $session) {
            return;
        }

        app(RecordCashMovement::class)->execute(
            session: $session,
            type: 'expense',
            amount: -(float) $expense->amount,
            referenceType: Expense::class,
            referenceId: $expense->id,
            reason: "Expense: {$expense->expenseCategory->name}",
            createdBy: $actorId,
        );
    }
}
