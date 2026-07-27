<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The ledger (CLAUDE.md §46) — never created directly; always through
 * RecordStockMovement, which locks the corresponding BranchStock row and
 * keeps its cached quantity in sync inside the same transaction.
 */
class StockMovement extends Model
{
    use BelongsToTenant;

    public const TYPES = [
        'purchase', 'sale', 'service_consumption', 'transfer_in',
        'transfer_out', 'adjustment', 'return', 'damage', 'expiry',
    ];

    // tenant_id deliberately excluded — never mass-assignable (CLAUDE.md
    // §28). BelongsToTenant auto-fills it from the authenticated session.
    protected $fillable = [
        'branch_id', 'product_id', 'type', 'quantity', 'unit_cost',
        'reference_type', 'reference_id', 'notes', 'performed_by', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
