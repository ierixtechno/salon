<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * State machine (see the create_purchase_orders_table migration for the
 * full reasoning): draft -> ordered -> {partially_received, received} ->
 * received. Cancellation only reachable before any goods are received.
 */
class PurchaseOrder extends Model
{
    use BelongsToTenant;

    public const STATUSES = ['draft', 'ordered', 'partially_received', 'received', 'cancelled'];

    public const TRANSITIONS = [
        'draft' => ['ordered', 'cancelled'],
        'ordered' => ['partially_received', 'received', 'cancelled'],
        'partially_received' => ['received'],
    ];

    // tenant_id/status deliberately excluded — never mass-assignable
    // (CLAUDE.md §28). status is transitioned only through Actions.
    protected $fillable = ['branch_id', 'supplier_id', 'expected_date', 'notes', 'created_by'];

    protected function casts(): array
    {
        return [
            'expected_date' => 'date',
            'ordered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function poNumber(): string
    {
        return 'PO-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }
}
