<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use Database\Factories\ResourceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Resource extends Model
{
    use BelongsToTenant, HasFactory;

    public const TYPES = [
        'chair', 'room', 'treatment_bed', 'nail_station',
        'wash_station', 'steam_room', 'sauna', 'other',
    ];

    protected static function newFactory(): ResourceFactory
    {
        return ResourceFactory::new();
    }

    protected $fillable = ['tenant_id', 'branch_id', 'type', 'name', 'capacity', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
