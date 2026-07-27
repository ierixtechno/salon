<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\Customer;
use App\Domain\Core\Models\CustomerSegment;
use App\Domain\Core\Scopes\TenantScope;
use Illuminate\Support\Collection;

/**
 * `type` is a closed whitelist switched on here — never a raw stored
 * query — so a tenant-editable segment can never become an arbitrary-SQL
 * vector (CLAUDE.md §29). Explicit tenant scoping (not the ambient
 * session) because this also runs from RunMarketingAutomations, a
 * console command with no authenticated user (CLAUDE.md §11).
 */
class ResolveSegmentCustomers
{
    public function execute(int $tenantId, CustomerSegment $segment): Collection
    {
        $query = Customer::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereNull('erased_at');

        match ($segment->type) {
            'all' => null,
            'tag' => $query->whereJsonContains('tags', (string) ($segment->criteria['tag'] ?? '__none__')),
            'inactive_days' => $query->whereDoesntHave('appointments', function ($q) use ($tenantId, $segment) {
                $q->withoutGlobalScope(TenantScope::class)
                    ->where('tenant_id', $tenantId)
                    ->where('starts_at', '>=', now()->subDays((int) ($segment->criteria['days'] ?? 90)));
            }),
            default => $query->whereRaw('1 = 0'),
        };

        return $query->get();
    }
}
