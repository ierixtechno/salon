<?php

namespace App\Domain\Platform\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $fillable = ['code', 'name', 'price', 'billing_interval', 'branch_limit', 'is_active'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'branch_limit' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'plan_features')
            ->withPivot('limit_value')
            ->withTimestamps();
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'plan_modules')->withTimestamps();
    }

    public function tenantSubscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class);
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
