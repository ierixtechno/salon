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

    /**
     * Where this service can be delivered. 'branch' is always implicitly
     * available (gated by the existing service_branch pivot); 'home' and
     * 'venue' are opt-in per service via home_service_enabled/
     * venue_service_enabled.
     */
    public const SERVICE_MODES = ['branch', 'home', 'venue'];

    protected static function newFactory(): ServiceFactory
    {
        return ServiceFactory::new();
    }

    // tenant_id deliberately excluded — never mass-assignable (CLAUDE.md
    // §28). BelongsToTenant auto-fills it from the authenticated session.
    protected $fillable = [
        'service_category_id', 'module_id', 'name', 'description',
        'duration_minutes', 'buffer_minutes', 'base_price', 'tax_rate_percent', 'sac_code', 'is_active',
        'home_service_enabled', 'home_service_fee', 'venue_service_enabled', 'venue_service_fee', 'travel_buffer_minutes',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'tax_rate_percent' => 'decimal:2',
            'is_active' => 'boolean',
            'home_service_enabled' => 'boolean',
            'home_service_fee' => 'decimal:2',
            'venue_service_enabled' => 'boolean',
            'venue_service_fee' => 'decimal:2',
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

    public function consumables(): HasMany
    {
        return $this->hasMany(ServiceConsumable::class);
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

    /**
     * Flat surcharge for a non-branch delivery mode, resolved once at
     * booking time (CLAUDE.md §14 Pricing, §20 Money — never trust a
     * client-submitted fee).
     */
    public function feeForMode(string $mode): string
    {
        return match ($mode) {
            'home' => (string) ($this->home_service_fee ?? '0.00'),
            'venue' => (string) ($this->venue_service_fee ?? '0.00'),
            default => '0.00',
        };
    }

    public function isAvailableForMode(string $mode): bool
    {
        return match ($mode) {
            'home' => (bool) $this->home_service_enabled,
            'venue' => (bool) $this->venue_service_enabled,
            default => true,
        };
    }
}
