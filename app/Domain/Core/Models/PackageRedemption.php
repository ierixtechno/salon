<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageRedemption extends Model
{
    use BelongsToTenant;

    protected $fillable = ['customer_package_item_id', 'appointment_id', 'branch_id', 'quantity', 'redeemed_at', 'redeemed_by'];

    protected function casts(): array
    {
        return ['redeemed_at' => 'datetime'];
    }

    public function customerPackageItem(): BelongsTo
    {
        return $this->belongsTo(CustomerPackageItem::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function redeemedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'redeemed_by');
    }
}
