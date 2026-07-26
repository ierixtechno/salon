<?php

namespace App\Domain\Core\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Fails closed: if no tenant context is resolvable, the query returns zero
 * rows rather than every tenant's rows. Tenant isolation is a security
 * boundary (CLAUDE.md §11), not a convenience filter — an unscoped query
 * must never silently become a cross-tenant query.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = current_tenant_id();

        if ($tenantId === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->getTable().'.tenant_id', $tenantId);
    }
}
