<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Platform\Models\PlatformInvoice;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class PlatformIncomeController extends Controller
{
    public function index(): View
    {
        $monthly = PlatformInvoice::selectRaw("DATE_FORMAT(paid_at, '%Y-%m') as month, SUM(amount) as total, COUNT(*) as invoice_count")
            ->groupBy('month')
            ->orderByDesc('month')
            ->get();

        return view('platform.income.index', [
            'monthly' => $monthly,
            'totalIncome' => PlatformInvoice::sum('amount'),
        ]);
    }
}
