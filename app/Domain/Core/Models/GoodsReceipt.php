<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Header only — the received quantities/costs per product are the
 * stock_movements rows referencing this receipt (reference_type=
 * 'goods_receipt'), not a separate line table.
 */
class GoodsReceipt extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'purchase_order_id', 'supplier_invoice_number', 'supplier_invoice_amount',
        'received_by', 'received_at',
    ];

    protected function casts(): array
    {
        return [
            'supplier_invoice_amount' => 'decimal:2',
            'received_at' => 'datetime',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'reference_id')->where('reference_type', 'goods_receipt');
    }
}
