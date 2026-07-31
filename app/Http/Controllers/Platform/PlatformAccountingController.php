<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Platform\Models\PlatformInvoice;
use App\Domain\Platform\Models\TenantSubscription;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class PlatformAccountingController extends Controller
{
    public function index(): View
    {
        $currentMonthStart = now()->startOfMonth();
        $currentMonthEnd = now()->endOfMonth();

        $currentMonthInvoices = PlatformInvoice::with(['tenant', 'plan'])
            ->whereBetween('paid_at', [$currentMonthStart, $currentMonthEnd])
            ->orderByDesc('paid_at')
            ->get();

        $previousMonths = PlatformInvoice::selectRaw("DATE_FORMAT(paid_at, '%Y-%m') as month, SUM(amount) as total, COUNT(*) as invoice_count")
            ->where('paid_at', '<', $currentMonthStart)
            ->groupBy('month')
            ->orderByDesc('month')
            ->get();

        $renewingNextMonth = $this->subscriptionsRenewingNextMonth();

        return view('platform.accounting.index', [
            'totalIncome' => PlatformInvoice::sum('amount'),
            'currentMonthInvoices' => $currentMonthInvoices,
            'currentMonthTotal' => $currentMonthInvoices->sum('amount'),
            'previousMonths' => $previousMonths,
            'renewingNextMonth' => $renewingNextMonth,
            // Tax-inclusive, to stay consistent with totalIncome/currentMonthTotal
            // above (both sum PlatformInvoice.amount, which is tax-inclusive —
            // see CreateQuotation/PayQuotation).
            'expectedNextMonthTotal' => $renewingNextMonth->sum(
                fn ($subscription) => (float) $subscription->plan->price * (1 + config('platform.gst_rate_percent') / 100)
            ),
        ]);
    }

    /**
     * A tenant can end up with more than one 'active' TenantSubscription row
     * over time — PayQuotation creates a new row per paid plan rather than
     * superseding the tenant's previous one (preserving subscription
     * history) — so only the most recently activated active row per tenant
     * (highest id) is treated as their current subscription for this
     * projection.
     */
    private function subscriptionsRenewingNextMonth()
    {
        $nextMonthStart = now()->addMonthNoOverflow()->startOfMonth();
        $nextMonthEnd = now()->addMonthNoOverflow()->endOfMonth();

        $latestActivePerTenant = TenantSubscription::where('status', 'active')
            ->selectRaw('MAX(id) as id')
            ->groupBy('tenant_id');

        return TenantSubscription::with(['tenant', 'plan'])
            ->joinSub($latestActivePerTenant, 'latest_active', function ($join) {
                $join->on('tenant_subscriptions.id', '=', 'latest_active.id');
            })
            ->select('tenant_subscriptions.*')
            ->whereBetween('tenant_subscriptions.ends_at', [$nextMonthStart, $nextMonthEnd])
            ->orderBy('tenant_subscriptions.ends_at')
            ->get();
    }
}
