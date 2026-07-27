<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Header only — same reasoning as GoodsReceipt: the returned quantities/
 * costs are the stock_movements rows referencing this return
 * (reference_type='purchase_return').
 */
class PurchaseReturn extends Model
{
    use BelongsToTenant;

    protected $fillable = ['branch_id', 'supplier_id', 'purchase_order_id', 'reason', 'created_by'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'reference_id')->where('reference_type', 'purchase_return');
    }
}
