<?php

namespace App\Auth;

use App\Domain\Core\Scopes\TenantScope;
use Illuminate\Auth\EloquentUserProvider;

/**
 * Every authenticated request re-hydrates the session user by ID, and every
 * login/password-reset resolves a user by email — all *before* any tenant
 * context exists (that's the whole point: the user IS how tenant context
 * gets established). BelongsToTenant's fail-closed TenantScope would
 * otherwise block these lookups unconditionally, breaking login/sessions
 * for everyone.
 *
 * This is the one deliberate, narrow bypass for identity resolution only —
 * it does not affect any other query against the User model elsewhere in
 * the app, which remain correctly tenant-scoped. See docs/02-TENANCY.md.
 */
class TenantAwareUserProvider extends EloquentUserProvider
{
    protected function newModelQuery($model = null)
    {
        return parent::newModelQuery($model)->withoutGlobalScope(TenantScope::class);
    }
}
