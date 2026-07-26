<?php

use App\Domain\Platform\Models\Tenant;

if (! function_exists('current_tenant_id')) {
    /**
     * Tenant identity always comes from the authenticated server-side
     * session — never from request input. CLAUDE.md §11.
     */
    function current_tenant_id(): ?int
    {
        return auth()->guard('web')->user()?->tenant_id;
    }
}

if (! function_exists('current_tenant')) {
    function current_tenant(): ?Tenant
    {
        return auth()->guard('web')->user()?->tenant;
    }
}
