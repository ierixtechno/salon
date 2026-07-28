<?php

namespace App\Http\Middleware;

use App\Domain\Platform\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * The public booking routes (CLAUDE.md §75) have no authenticated `web`
 * session to derive tenant context from — tenant identity here comes from
 * a server-side database lookup keyed by the `{tenant_slug}` URL segment,
 * never trusted as-is from the request. Binds `guestTenant` into the
 * container so current_tenant_id()/current_tenant() (app/helpers.php) and
 * every BelongsToTenant model's TenantScope resolve correctly for the rest
 * of the request, with zero changes needed to the existing action layer.
 *
 * A missing or inactive (suspended/cancelled) tenant both 404 identically
 * — never revealing which, same as any other cross-tenant lookup
 * (CLAUDE.md §32).
 */
class ResolveTenantFromSlug
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Tenant::where('slug', $request->route('tenant_slug'))->first();

        abort_unless($tenant && $tenant->isActive(), 404);

        app()->instance('guestTenant', $tenant);
        $request->attributes->set('tenant', $tenant);
        Log::withContext(['tenant_id' => $tenant->id]);

        return $next($request);
    }
}
