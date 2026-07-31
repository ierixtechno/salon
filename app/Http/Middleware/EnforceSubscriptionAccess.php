<?php

namespace App\Http\Middleware;

use App\Domain\Platform\Models\Tenant;
use App\Domain\Platform\Support\ResolveSubscriptionAccessState;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs after EnsureTenantActive (which only handles explicit Super-Admin
 * suspension). This is the finer-grained gate: a tenant that's never paid,
 * or whose subscription has lapsed, gets progressively restricted rather
 * than logged out — see docs on 'pending'/'grace'/'blocked' in
 * SubscriptionAccessState.
 */
class EnforceSubscriptionAccess
{
    public function __construct(private readonly ResolveSubscriptionAccessState $resolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Always allowed regardless of state — otherwise a blocked tenant
        // could never reach the page/routes that let them pay their way
        // back in. profile.* is included too: a pending/blocked user may
        // need to fix a typo'd email to actually receive their invoice.
        if ($request->routeIs('billing.*') || $request->routeIs('account.access')
            || $request->routeIs('logout') || $request->routeIs('profile.*')) {
            return $next($request);
        }

        $tenantId = $request->user('web')?->tenant_id;

        if (! $tenantId) {
            return $next($request);
        }

        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            return $next($request);
        }

        $state = $this->resolver->execute($tenant);

        if ($state->isPending() || $state->isBlocked()) {
            return redirect()->route('account.access');
        }

        if ($state->isGrace() && ! $request->isMethodSafe()) {
            return redirect()->back()->with(
                'subscription_blocked',
                'Your subscription has expired. You have read-only access during the grace period — renew now to make changes.',
            );
        }

        View::share('subscriptionAccessState', $state);

        return $next($request);
    }
}
