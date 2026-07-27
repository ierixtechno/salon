<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\Expense;

class RejectExpense
{
    public function execute(Expense $expense, ?string $reason, ?int $decidedBy): Expense
    {
        abort_unless($expense->canTransitionTo('rejected'), 409, "Cannot reject an expense that is currently {$expense->status}.");

        $expense->status = 'rejected';
        $expense->approved_by = $decidedBy;
        $expense->approved_at = now();
        $expense->decision_reason = $reason;
        $expense->save();

        return $expense;
    }
}
