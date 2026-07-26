<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * spatie/laravel-permission's "teams" feature is used with tenant_id as the
 * team key (see config/permission.php and docs/04-RBAC.md). This tells the
 * package which team/tenant to evaluate roles and permissions against for
 * the current request — resolved from the authenticated user, never from
 * client input.
 */
class SetPermissionsTeamFromTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = $request->user('web')?->tenant_id;

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);

        return $next($request);
    }
}
