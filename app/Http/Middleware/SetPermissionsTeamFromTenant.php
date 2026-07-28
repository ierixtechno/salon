<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * spatie/laravel-permission's "teams" feature is used with tenant_id as the
 * team key (see config/permission.php and docs/04-RBAC.md). This tells the
 * package which team/tenant to evaluate roles and permissions against for
 * the current request — resolved from the authenticated user, never from
 * client input.
 *
 * This is also the first point in every authenticated tenant request where
 * tenant/user identity is known, so it doubles as where that identity joins
 * the request's Log context (CLAUDE.md §41) — AttachRequestId already set
 * request_id earlier, before auth was resolved.
 */
class SetPermissionsTeamFromTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('web');
        $tenantId = $user?->tenant_id;

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);

        Log::withContext(array_filter(['tenant_id' => $tenantId, 'user_id' => $user?->id]));

        return $next($request);
    }
}
