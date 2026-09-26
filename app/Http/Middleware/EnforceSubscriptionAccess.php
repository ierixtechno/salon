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
 *
 * A tenant that has never paid (or is fully lapsed) CAN log in — that is
 * how they reach their quotation and pay it — but is confined to the
 * payment screens: everything else redirects to account.access, which
 * sends them on to their pending quotation.
 */
class EnforceSubscriptionAccess
{
    public function __construct(private readonly ResolveSubscriptionAccessState $resolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = $request->user('web')?->tenant_id;

        if (! $tenantId) {
            return $next($request);
        }

        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            return $next($request);
        }

        $state = $this->resolver->execute($tenant);

        // Shared before the exemptions below, not after: the payment
        // screens themselves are exempt, and they are exactly where the
        // layout needs to know the tenant is locked so it can hide the
        // side menu.
        View::share('subscriptionAccessState', $state);

        // Always allowed regardless of state — otherwise a locked tenant
        // could never reach the page/routes that let them pay their way
        // back in. profile.* is included too: a pending/blocked user may
        // need to fix a typo'd email to actually receive their invoice.
        if ($request->routeIs('billing.*') || $request->routeIs('account.access')
            || $request->routeIs('logout') || $request->routeIs('profile.*')) {
            return $next($request);
        }

        if ($state->isLocked()) {
            return redirect()->route('account.access');
        }

        if ($state->isGrace() && ! $request->isMethodSafe()) {
            return redirect()->back()->with(
                'subscription_blocked',
                'Your subscription has expired. You have read-only access during the grace period — renew now to make changes.',
            );
        }

        return $next($request);
    }
}
