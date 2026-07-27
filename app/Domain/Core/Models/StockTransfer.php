<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Header only — the per-product detail is the pair of stock_movements
 * (transfer_out at from_branch, transfer_in at to_branch) referencing this
 * row, created atomically by TransferStock.
 */
class StockTransfer extends Model
{
    use BelongsToTenant;

    protected $fillable = ['from_branch_id', 'to_branch_id', 'notes', 'created_by', 'transferred_at'];

    protected function casts(): array
    {
        return ['transferred_at' => 'datetime'];
    }

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'reference_id')->where('reference_type', 'stock_transfer');
    }
}
