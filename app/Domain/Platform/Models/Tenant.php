<?php

namespace App\Domain\Platform\Models;

use App\Domain\Core\Models\Branch;
use App\Domain\Core\Scopes\TenantScope;
use App\Models\User;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'status', 'timezone', 'currency', 'trial_ends_at', 'suspended_at',
    ];

    // Laravel's factory-name convention assumes App\Models\X; models under
    // App\Domain\* need an explicit pointer to their factory.
    protected static function newFactory(): TenantFactory
    {
        return TenantFactory::new();
    }

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    /**
     * These relations are queried from Super Admin (Platform) contexts,
     * which have no `web`-guard tenant session — so BelongsToTenant's
     * fail-closed TenantScope (see docs/02-TENANCY.md) would otherwise
     * zero out the results. That's safe to bypass here specifically
     * because a `hasMany` relation is already intrinsically scoped to
     * *this* tenant's id; it is not an unscoped ad hoc query.
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class)->withoutGlobalScope(TenantScope::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class)->withoutGlobalScope(TenantScope::class);
    }

    public function tenantModules(): HasMany
    {
        return $this->hasMany(TenantModule::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class);
    }

    public function currentSubscription(): ?TenantSubscription
    {
        return $this->subscriptions()
            ->whereIn('status', ['trialing', 'active'])
            ->latest('starts_at')
            ->first();
    }

    public function hasModuleEnabled(string $moduleCode): bool
    {
        return $this->tenantModules()
            ->where('enabled', true)
            ->whereHas('module', fn ($q) => $q->where('code', $moduleCode))
            ->exists();
    }

    /**
     * Tenant status is the upper boundary for whether the tenant can operate
     * at all — a suspended/cancelled tenant fails Access Evaluation step 3
     * (CLAUDE.md §9) regardless of anything else.
     */
    public function isActive(): bool
    {
        return in_array($this->status, ['trial', 'active'], true);
    }
}
