<?php

use App\Domain\Platform\Models\Tenant;

if (! function_exists('current_tenant_id')) {
    /**
     * Tenant identity always comes from server-side context — never from
     * request input (CLAUDE.md §11). The authenticated `web` session is the
     * primary source; the `guestTenant` container binding is the one
     * deliberate exception, populated only by ResolveTenantFromSlug on the
     * public booking routes (CLAUDE.md §75), which resolves it from a
     * database lookup keyed by the URL's tenant slug — never trusted
     * directly from the request. An authenticated session always wins if
     * somehow both are present.
     */
    function current_tenant_id(): ?int
    {
        if ($tenantId = auth()->guard('web')->user()?->tenant_id) {
            return $tenantId;
        }

        return app()->bound('guestTenant') ? app('guestTenant')->id : null;
    }
}

if (! function_exists('current_tenant')) {
    function current_tenant(): ?Tenant
    {
        if ($tenant = auth()->guard('web')->user()?->tenant) {
            return $tenant;
        }

        return app()->bound('guestTenant') ? app('guestTenant') : null;
    }
}
