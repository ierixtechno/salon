<?php

namespace App\Http\Controllers\Core;

use App\Domain\Core\Actions\CreditWallet;
use App\Domain\Core\Models\Customer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\StoreWalletCreditRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class WalletController extends Controller
{
    public function show(Customer $customer): View
    {
        abort_unless(Auth::guard('web')->user()->can('wallet.view'), 403);

        return view('core.customers.wallet', [
            'customer' => $customer,
            'balance' => $customer->walletBalance(),
            'transactions' => $customer->walletTransactions()->latest()->limit(50)->get(),
        ]);
    }

    public function credit(StoreWalletCreditRequest $request, Customer $customer, CreditWallet $action): RedirectResponse
    {
        $action->execute(
            customer: $customer,
            amount: (float) $request->validated('amount'),
            reason: $request->validated('reason') ?? 'Manual top-up',
            createdBy: Auth::guard('web')->id(),
        );

        return back()->with('status', 'Wallet credited.');
    }
}
