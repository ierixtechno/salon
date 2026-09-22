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

if (! function_exists('human_file_size')) {
    /**
     * "2.4 MB" style size. Deliberately hand-rolled rather than
     * Illuminate\Support\Number::fileSize(), which throws unless PHP's `intl`
     * extension is installed — and intl is not guaranteed on shared hosting
     * (CLAUDE.md §64), so a page or command that depended on it would crash
     * in production while working fine in development.
     */
    function human_file_size(int|float|string|null $bytes, int $decimals = 1): string
    {
        $bytes = max(0.0, (float) $bytes);
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $power = $bytes > 0 ? min((int) floor(log($bytes, 1024)), count($units) - 1) : 0;
        $value = $bytes / (1024 ** $power);

        return ($power === 0 ? number_format($value, 0) : number_format($value, $decimals)).' '.$units[$power];
    }
}
