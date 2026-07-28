<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every log line for this request carries a correlation id (CLAUDE.md §41
 * — "request ID... on every logged failure"), surfaced back in the
 * response too so a support conversation can reference it without ever
 * needing a stack trace (CLAUDE.md §40). Registered globally, first in the
 * stack, since it has no dependency on auth/tenant resolution — those are
 * added to the same Log context later, by SetPermissionsTeamFromTenant and
 * ResolveTenantFromSlug respectively, once they're known.
 */
class AttachRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) Str::ulid();
        $request->attributes->set('request_id', $requestId);

        Log::withContext(['request_id' => $requestId]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
