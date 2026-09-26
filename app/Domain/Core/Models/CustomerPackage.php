<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A purchased instance of a Package template. `status` is deliberately not
 * in $fillable — set only via SellPackageToCustomer/CancelCustomerPackage/
 * RedeemPackageItem (CLAUDE.md §28).
 */
class CustomerPackage extends Model
{
    use BelongsToTenant;

    public const STATUSES = ['active', 'expired', 'exhausted', 'cancelled'];

    protected $fillable = [
        'customer_id', 'package_id', 'branch_id', 'invoice_id', 'price_paid',
        'purchase_method', 'purchase_reference', 'purchased_at', 'expires_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'price_paid' => 'decimal:2',
            'purchased_at' => 'datetime',
            'expires_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CustomerPackageItem::class);
    }

    public function isUsable(): bool
    {
        return $this->status === 'active' && $this->expires_at->isFuture();
    }
}
