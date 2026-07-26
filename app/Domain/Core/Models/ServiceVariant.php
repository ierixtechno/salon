<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceVariant extends Model
{
    use BelongsToTenant;

    protected $fillable = ['service_id', 'name', 'price', 'duration_minutes', 'is_active'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function effectivePrice(): string
    {
        return $this->price ?? $this->service->base_price;
    }

    public function effectiveDurationMinutes(): int
    {
        return $this->duration_minutes ?? $this->service->duration_minutes;
    }
}
