<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Access Evaluation step 3 (CLAUDE.md §9): tenant status. A suspended or
 * cancelled tenant's users must not be able to operate, even though their
 * own account is otherwise valid. This is a business-rule/authorization
 * failure, not a generic 500 (CLAUDE.md §36/§39).
 */
class EnsureTenantActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->user('web')?->tenant;

        if ($tenant && ! $tenant->isActive()) {
            auth('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'This account is currently suspended. Please contact support.']);
        }

        return $next($request);
    }
}
