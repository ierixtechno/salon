<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * `status` is deliberately not in $fillable — set only via IssueGiftCard/
 * CancelGiftCard (CLAUDE.md §28). Balance is never a mutable column — it
 * is always sum(transactions.amount), reconstructable from the ledger
 * alone (mirrors BranchStock/Product's stock-ledger philosophy, Phase 7).
 */
class GiftCard extends Model
{
    use BelongsToTenant;

    public const STATUSES = ['active', 'cancelled', 'expired'];

    protected $fillable = [
        'code', 'initial_value', 'customer_id', 'purchase_method', 'purchase_reference', 'expires_at', 'issued_by', 'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'initial_value' => 'decimal:2',
            'expires_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'issued_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(GiftCardTransaction::class);
    }

    public function balance(): string
    {
        return (string) $this->transactions()->sum('amount');
    }

    public function isUsable(): bool
    {
        return $this->status === 'active' && (! $this->expires_at || $this->expires_at->isFuture());
    }
}
