<?php

namespace App\Domain\Platform\Models;

use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\WhatsappCreditTransaction;
use App\Domain\Core\Scopes\TenantScope;
use App\Models\User;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'status', 'timezone', 'currency', 'billing_state', 'gstin', 'trial_ends_at', 'suspended_at',
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

    /**
     * 'expired' is included deliberately — ProcessSubscriptionRenewals
     * cosmetically flips a lapsed row to 'expired' for reporting, but the
     * row is still the tenant's "current" (most relevant) subscription for
     * grace-period/blocked-state computation (EnforceSubscriptionAccess
     * always recomputes the real state from ends_at, never trusts this
     * status column alone).
     */
    public function currentSubscription(): ?TenantSubscription
    {
        return $this->subscriptions()
            ->whereIn('status', ['trialing', 'active', 'expired'])
            ->latest('starts_at')
            ->first();
    }

    /**
     * Active branches this tenant may run: what their subscription covers (the
     * plan's included branches plus any bought extras), or
     * the one default branch when they have no subscription yet (pending
     * payment). Branches a tenant already has above the limit are never
     * removed — the limit only stops new ones being added.
     */
    public function branchLimit(): int
    {
        $subscription = $this->currentSubscription();

        return $subscription ? $subscription->currentBranchCount() : 1;
    }

    /**
     * Active users this tenant may have, from its plan and the branches it has
     * bought — null means unlimited (no plan limit set, or no subscription yet).
     */
    public function userLimit(): ?int
    {
        $subscription = $this->currentSubscription();

        return $subscription?->plan?->userLimitFor($subscription->currentBranchCount());
    }

    public function activeUserCount(): int
    {
        return $this->users()->where('is_active', true)->count();
    }

    /**
     * Checked on effectively every module-gated request (CLAUDE.md §56
     * names "module assignments" as a good cache candidate). The 10-minute
     * TTL is a correctness safety net, not the primary invalidation
     * mechanism — UpdateTenantModules explicitly forgets this key the
     * moment it changes, so staleness in practice is bounded by "did the
     * write path forget to call forgetModuleCache", not by the TTL.
     */
    public function hasModuleEnabled(string $moduleCode): bool
    {
        return Cache::remember(
            self::moduleCacheKey($this->id, $moduleCode),
            600,
            fn () => $this->tenantModules()
                ->where('enabled', true)
                ->whereHas('module', fn ($q) => $q->where('code', $moduleCode))
                ->exists(),
        );
    }

    public static function forgetModuleCache(int $tenantId, string $moduleCode): void
    {
        Cache::forget(self::moduleCacheKey($tenantId, $moduleCode));
    }

    private static function moduleCacheKey(int $tenantId, string $moduleCode): string
    {
        return "tenant:{$tenantId}:module:{$moduleCode}";
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

    /**
     * The narrow "Super Admin explicitly suspended/cancelled this tenant"
     * check used by EnsureTenantActive's hard logout. Deliberately does NOT
     * include 'pending_payment' — those tenants must still be able to log
     * in and see their account status (EnforceSubscriptionAccess handles
     * that gating separately, with a softer landing page instead of a
     * forced logout).
     */
    public function isBlocked(): bool
    {
        return in_array($this->status, ['suspended', 'cancelled'], true);
    }

    /**
     * A credit count, not money — 1 credit = 1 WhatsApp message actually
     * sent. Always derived from the whatsapp_credit_transactions ledger
     * (CLAUDE.md §20), never a mutable column. See ChargeWhatsappCredit
     * (the debit side) and TopUpWhatsappCredits (the credit side).
     */
    public function whatsappCreditBalance(): int
    {
        return (int) WhatsappCreditTransaction::where('tenant_id', $this->id)->sum('amount');
    }

    /**
     * EnforceSubscriptionAccess's resolved state is cheap to compute but
     * checked on effectively every tenant request, so it's cached the same
     * way module-enablement is (see hasModuleEnabled() above). PayQuotation
     * calls this the moment a payment lands so the tenant's very next
     * request reflects the unlock immediately.
     */
    public static function forgetSubscriptionCache(int $tenantId): void
    {
        Cache::forget(self::subscriptionCacheKey($tenantId));
    }

    public static function subscriptionCacheKey(int $tenantId): string
    {
        return "tenant:{$tenantId}:subscription-access-state";
    }
}
