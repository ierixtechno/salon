<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceConsumable extends Model
{
    use BelongsToTenant;

    protected $fillable = ['service_id', 'product_id', 'quantity_per_use'];

    protected function casts(): array
    {
        return ['quantity_per_use' => 'decimal:3'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
