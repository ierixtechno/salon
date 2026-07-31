<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Platform\Models\PlatformInvoice;
use App\Domain\Platform\Models\Tenant;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class PlatformDashboardController extends Controller
{
    public function index(): View
    {
        return view('platform.dashboard', [
            'counts' => [
                'trial' => Tenant::where('status', 'trial')->count(),
                'active' => Tenant::where('status', 'active')->count(),
                'suspended' => Tenant::where('status', 'suspended')->count(),
                'cancelled' => Tenant::where('status', 'cancelled')->count(),
            ],
            'totalIncome' => PlatformInvoice::sum('amount'),
            'currentMonthIncome' => PlatformInvoice::whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount'),
            'recentTenants' => Tenant::latest()->limit(10)->get(),
        ]);
    }
}
