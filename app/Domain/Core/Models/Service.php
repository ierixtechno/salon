<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use App\Domain\Platform\Models\Module;
use App\Models\User;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use BelongsToTenant, HasFactory;

    protected static function newFactory(): ServiceFactory
    {
        return ServiceFactory::new();
    }

    // tenant_id deliberately excluded — never mass-assignable (CLAUDE.md
    // §28). BelongsToTenant auto-fills it from the authenticated session.
    protected $fillable = [
        'service_category_id', 'module_id', 'name', 'description',
        'duration_minutes', 'buffer_minutes', 'base_price', 'tax_rate_percent', 'sac_code', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'tax_rate_percent' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ServiceVariant::class);
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'service_branch')
            ->withPivot('is_available', 'price_override')
            ->withTimestamps();
    }

    public function capableEmployees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'service_user')->withTimestamps();
    }

    /**
     * Server-side price resolution (CLAUDE.md §14 Pricing, §20 Money):
     * branch override > base price. Never trust a client-submitted price.
     */
    public function priceForBranch(Branch $branch): string
    {
        $pivot = $this->branches()->where('branches.id', $branch->id)->first()?->pivot;

        return $pivot?->price_override ?? $this->base_price;
    }

    public function isAvailableAtBranch(Branch $branch): bool
    {
        $pivot = $this->branches()->where('branches.id', $branch->id)->first()?->pivot;

        return (bool) ($pivot?->is_available ?? false);
    }
}
