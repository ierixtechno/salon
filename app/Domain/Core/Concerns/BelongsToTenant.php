<?php

namespace App\Domain\Core\Concerns;

use App\Domain\Core\Scopes\TenantScope;
use App\Domain\Platform\Models\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Apply to every tenant-owned model. Auto-scopes all queries to the current
 * tenant context (fail-closed, see TenantScope) and auto-fills tenant_id on
 * creation. Never rely on `Model::find($id)` alone for a tenant-owned model
 * without this guarantee — see .claude/skills/beauty-saas-development/SKILL.md §7.
 */
trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model): void {
            if (empty($model->tenant_id) && $tenantId = current_tenant_id()) {
                $model->tenant_id = $tenantId;
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
