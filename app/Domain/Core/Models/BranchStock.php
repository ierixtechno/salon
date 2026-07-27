<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * `quantity` is a cached balance, never independently authoritative — it
 * must always equal the sum of this branch/product's StockMovement rows,
 * and is only ever mutated by RecordStockMovement inside the same locked
 * transaction as the movement it derives from (CLAUDE.md §46).
 */
class BranchStock extends Model
{
    use BelongsToTenant;

    // tenant_id/quantity deliberately excluded — never mass-assignable
    // (CLAUDE.md §28). quantity is set only by RecordStockMovement.
    protected $fillable = ['branch_id', 'product_id', 'reorder_level_override'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'reorder_level_override' => 'decimal:3',
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

    public function isLowStock(): bool
    {
        $threshold = $this->reorder_level_override ?? $this->product->reorder_level;

        return $threshold > 0 && (float) $this->quantity <= (float) $threshold;
    }
}
