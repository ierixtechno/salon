<?php

namespace App\Http\Controllers\Core;

use App\Domain\Platform\Models\Quotation;
use App\Domain\Platform\Models\Tenant;
use App\Domain\Platform\Support\ResolveSubscriptionAccessState;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Where EnforceSubscriptionAccess sends a locked tenant (never paid, or
 * lapsed past the grace period).
 *
 * A brand-new tenant that has a pending quotation goes straight to it —
 * the quotation and how to pay it is the whole point of their first login,
 * so there is no intermediate "you're locked out" page to click through.
 * Everyone else locked (a lapsed tenant, or a new one whose quotation was
 * cancelled) sees the explanatory page below.
 *
 * Deliberately reachable by ANY authenticated tenant user regardless of
 * permission (not gated by can:tenant.billing.manage) — a pending/blocked
 * tenant's non-Owner staff must still be able to see why they're locked
 * out, even though only the Owner can actually see/pay a Quotation's
 * amount (that permission boundary is preserved below).
 */
class AccountAccessController extends Controller
{
    public function show(ResolveSubscriptionAccessState $resolver): View|RedirectResponse
    {
        $tenant = Tenant::findOrFail(Auth::user()->tenant_id);
        $state = $resolver->execute($tenant);

        if (! $state->isLocked()) {
            return redirect()->route('dashboard');
        }

        $pendingQuotation = null;
        if (Auth::user()->can('tenant.billing.manage')) {
            $pendingQuotation = Quotation::where('tenant_id', $tenant->id)
                ->where('status', 'pending')
                ->latest()
                ->first();
        }

        if ($state->isPending() && $pendingQuotation) {
            return redirect()->route('billing.quotations.show', $pendingQuotation);
        }

        return view('core.account-access', [
            'state' => $state,
            'pendingQuotation' => $pendingQuotation,
        ]);
    }
}
