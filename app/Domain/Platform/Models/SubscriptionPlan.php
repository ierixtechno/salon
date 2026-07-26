<?php

namespace App\Domain\Platform\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SubscriptionPlan extends Model
{
    protected $fillable = ['code', 'name', 'price', 'billing_interval', 'is_active'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'plan_features')
            ->withPivot('limit_value')
            ->withTimestamps();
    }

    public function hasFeature(string $featureCode): bool
    {
        return $this->features()->where('code', $featureCode)->exists();
    }

    /**
     * Null means the feature is on with no numeric limit. This is never
     * something a client can override — plan features are always resolved
     * server-side (CLAUDE.md §10).
     */
    public function featureLimit(string $featureCode): ?int
    {
        return $this->features()->where('code', $featureCode)->first()?->pivot->limit_value;
    }
}
