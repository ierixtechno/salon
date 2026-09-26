<?php

namespace App\Domain\Platform\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $fillable = ['code', 'name', 'price', 'compare_at_price', 'billing_interval', 'branch_limit', 'additional_branch_price', 'max_branches', 'is_active'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'branch_limit' => 'integer',
            'additional_branch_price' => 'decimal:2',
            'max_branches' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** True when a regular price is set above the charged price (an offer is running). */
    public function hasPromo(): bool
    {
        return $this->compare_at_price !== null && (float) $this->compare_at_price > (float) $this->price;
    }

    /** Whole-number percentage off the regular price. */
    public function promoPercent(): int
    {
        return $this->hasPromo() ? (int) round((1 - (float) $this->price / (float) $this->compare_at_price) * 100) : 0;
    }

    /** True when this plan sells branches beyond the included ones. */
    public function sellsExtraBranches(): bool
    {
        return (float) $this->additional_branch_price > 0;
    }

    /** The most branches a tenant can have on this plan (included + purchasable extras). */
    public function maxBranches(): int
    {
        if (! $this->sellsExtraBranches()) {
            return $this->branch_limit;
        }

        return max($this->branch_limit, (int) ($this->max_branches ?? 50));
    }

    /** Bring any requested branch count inside what this plan allows. */
    public function clampBranches(?int $count): int
    {
        return min(max($count ?? $this->branch_limit, $this->branch_limit), $this->maxBranches());
    }

    /**
     * Price for a given TOTAL number of branches: the plan price covers the
     * included ones, each further branch adds `additional_branch_price`.
     * Always resolved server-side (CLAUDE.md §20).
     */
    public function priceForBranches(?int $count): float
    {
        $count = $this->clampBranches($count);

        return round((float) $this->price + ($count - $this->branch_limit) * (float) $this->additional_branch_price, 2);
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
