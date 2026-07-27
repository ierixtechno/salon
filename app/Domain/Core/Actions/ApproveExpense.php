<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\Expense;
use Illuminate\Support\Facades\DB;

class ApproveExpense
{
    public function execute(Expense $expense, ?int $approvedBy): Expense
    {
        abort_unless($expense->canTransitionTo('approved'), 409, "Cannot approve an expense that is currently {$expense->status}.");

        return DB::transaction(function () use ($expense, $approvedBy) {
            $expense->status = 'approved';
            $expense->approved_by = $approvedBy;
            $expense->approved_at = now();
            $expense->save();

            app(DebitCashRegisterForExpense::class)->execute($expense, $approvedBy);

            return $expense;
        });
    }
}
