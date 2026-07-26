<?php

namespace App\Http\Middleware;

use App\Domain\Core\Models\Branch;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Module Authorization (CLAUDE.md §9, SKILL.md §9): before any vertical
 * (salon/beauty/spa) functionality executes, verify the tenant has the
 * module enabled and, when a branch is in play, that the branch does too.
 * Applied as `module:salon` / `module:beauty` / `module:spa` route
 * middleware. Fails as a 403 business/authorization error, never a 500,
 * and fails closed even on manually-requested routes.
 */
class EnsureModuleEnabled
{
    public function handle(Request $request, Closure $next, string $moduleCode): Response
    {
        $tenant = $request->user('web')?->tenant;

        abort_unless($tenant && $tenant->hasModuleEnabled($moduleCode), 403, "The {$moduleCode} module is not enabled for this account.");

        $branchId = $request->route('branch') instanceof Branch
            ? $request->route('branch')->id
            : $request->session()->get('current_branch_id');

        if ($branchId) {
            $branch = Branch::find($branchId);

            abort_unless($branch && $branch->hasModuleEnabled($moduleCode), 403, "The {$moduleCode} module is not enabled for this branch.");
        }

        return $next($request);
    }
}
