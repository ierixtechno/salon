<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\BusinessProfile;
use App\Domain\Core\Models\Expense;
use App\Domain\Core\Models\ExpenseCategory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Approval is opt-in per tenant (BusinessProfile::expenseApprovalRequired(),
 * CLAUDE.md §14 "Approval where configured"). When off, the expense is
 * recorded as already `approved` and — if paid in cash and the branch has
 * an open cash register session — immediately debited from it (via
 * DebitCashRegisterForExpense). When on, it starts `pending` and the cash
 * effect is deferred to ApproveExpense, since the money hasn't been
 * confirmed spent from the register's point of view until a Manager/Owner
 * signs off.
 */
class CreateExpense
{
    public function execute(
        Branch $branch,
        ExpenseCategory $category,
        float $amount,
        float $taxAmount,
        string $paymentMethod,
        Carbon $expenseDate,
        ?string $vendorName,
        ?string $description,
        ?string $attachmentPath,
        ?int $createdBy,
    ): Expense {
        abort_unless(in_array($paymentMethod, Expense::PAYMENT_METHODS, true), 422, 'Invalid payment method.');
        abort_if($amount <= 0, 422, 'Expense amount must be greater than zero.');

        $approvalRequired = BusinessProfile::where('tenant_id', $branch->tenant_id)->first()?->expenseApprovalRequired() ?? false;

        return DB::transaction(function () use ($branch, $category, $amount, $taxAmount, $paymentMethod, $expenseDate, $vendorName, $description, $attachmentPath, $createdBy, $approvalRequired) {
            $expense = new Expense([
                'branch_id' => $branch->id,
                'expense_category_id' => $category->id,
                'vendor_name' => $vendorName,
                'amount' => $amount,
                'tax_amount' => $taxAmount,
                'payment_method' => $paymentMethod,
                'expense_date' => $expenseDate,
                'description' => $description,
                'attachment_path' => $attachmentPath,
                'created_by' => $createdBy,
            ]);

            if ($approvalRequired) {
                $expense->status = 'pending';
                $expense->save();

                return $expense;
            }

            $expense->status = 'approved';
            $expense->approved_by = $createdBy;
            $expense->approved_at = now();
            $expense->save();

            app(DebitCashRegisterForExpense::class)->execute($expense, $createdBy);

            return $expense;
        });
    }
}
