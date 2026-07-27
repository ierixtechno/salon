<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderLine extends Model
{
    use BelongsToTenant;

    // tenant_id/quantity_received deliberately excluded — never
    // mass-assignable (CLAUDE.md §28). quantity_received is updated only
    // by ReceiveGoods, from the stock_movements it creates.
    protected $fillable = ['purchase_order_id', 'product_id', 'quantity_ordered', 'unit_cost'];

    protected function casts(): array
    {
        return [
            'quantity_ordered' => 'decimal:3',
            'quantity_received' => 'decimal:3',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function quantityOutstanding(): string
    {
        return (string) round((float) $this->quantity_ordered - (float) $this->quantity_received, 3);
    }
}
