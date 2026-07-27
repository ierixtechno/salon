<?php

namespace App\Http\Controllers\Core;

use App\Domain\Core\Models\Customer;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class LoyaltyController extends Controller
{
    public function show(Customer $customer): View
    {
        abort_unless(Auth::guard('web')->user()->can('loyalty.view'), 403);

        return view('core.customers.loyalty', [
            'customer' => $customer,
            'balance' => $customer->loyaltyPointsBalance(),
            'entries' => $customer->loyaltyLedgerEntries()->latest()->limit(50)->get(),
        ]);
    }
}
