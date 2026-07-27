<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * `status`/`approved_by`/`approved_at`/`decision_reason` are deliberately
 * not in $fillable — set only via CreateExpense/ApproveExpense/
 * RejectExpense (CLAUDE.md §28).
 */
class Expense extends Model
{
    use BelongsToTenant;

    public const STATUSES = ['pending', 'approved', 'rejected'];

    public const TRANSITIONS = [
        'pending' => ['approved', 'rejected'],
    ];

    public const PAYMENT_METHODS = ['cash', 'card', 'upi', 'bank_transfer'];

    protected $fillable = [
        'branch_id', 'expense_category_id', 'vendor_name', 'amount', 'tax_amount',
        'payment_method', 'expense_date', 'description', 'attachment_path', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'expense_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function expenseCategory(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
