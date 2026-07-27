<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * `status`/`closed_by`/`closed_at`/`actual_closing`/`expected_closing`/
 * `difference` are deliberately not in $fillable — set only via
 * OpenCashRegister/CloseCashRegister (CLAUDE.md §28).
 */
class CashRegisterSession extends Model
{
    use BelongsToTenant;

    public const STATUSES = ['open', 'closed'];

    protected $fillable = ['branch_id', 'opened_by', 'opened_at', 'opening_cash', 'notes'];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_cash' => 'decimal:2',
            'actual_closing' => 'decimal:2',
            'expected_closing' => 'decimal:2',
            'difference' => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    /**
     * The live running total while the session is still open — always
     * reconstructed from the ledger, never a mutable column (CLAUDE.md
     * §14/§45). Once closed, `expected_closing` is the frozen snapshot.
     */
    public function runningTotal(): float
    {
        return round((float) $this->opening_cash + (float) $this->cashMovements()->sum('amount'), 2);
    }
}
