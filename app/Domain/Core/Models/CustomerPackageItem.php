<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * `quantity_redeemed` is deliberately not in $fillable — accumulated only
 * by RedeemPackageItem (CLAUDE.md §28), mirroring PurchaseOrderLine's
 * quantity_received (Phase 7).
 */
class CustomerPackageItem extends Model
{
    use BelongsToTenant;

    protected $fillable = ['customer_package_id', 'service_id', 'quantity_purchased'];

    public function customerPackage(): BelongsTo
    {
        return $this->belongsTo(CustomerPackage::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(PackageRedemption::class);
    }

    public function quantityRemaining(): int
    {
        return $this->quantity_purchased - $this->quantity_redeemed;
    }
}
