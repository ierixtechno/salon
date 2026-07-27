<?php

namespace App\Http\Controllers\Core;

use App\Domain\Core\Actions\CancelGiftCard;
use App\Domain\Core\Actions\IssueGiftCard;
use App\Domain\Core\Models\Customer;
use App\Domain\Core\Models\GiftCard;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\StoreGiftCardRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class GiftCardController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:gift-cards.view')->only(['index']);
        $this->middleware('can:gift-cards.create')->only(['create', 'store']);
    }

    public function index(): View
    {
        return view('core.gift-cards.index', [
            'giftCards' => GiftCard::with('customer')->latest('issued_at')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('core.gift-cards.create', [
            'customers' => Customer::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreGiftCardRequest $request, IssueGiftCard $action): RedirectResponse
    {
        $action->execute(
            initialValue: (float) $request->validated('initial_value'),
            customer: $request->validated('customer_id') ? Customer::findOrFail($request->validated('customer_id')) : null,
            purchaseMethod: $request->validated('purchase_method'),
            purchaseReference: $request->validated('purchase_reference'),
            expiresAt: $request->validated('expires_at') ? Carbon::parse($request->validated('expires_at')) : null,
            issuedBy: Auth::guard('web')->id(),
        );

        return redirect()->route('gift-cards.index')->with('status', 'Gift card issued.');
    }

    public function cancel(GiftCard $giftCard, CancelGiftCard $action): RedirectResponse
    {
        $this->authorize('update', $giftCard);
        $action->execute($giftCard, request('reason'), Auth::guard('web')->id());

        return back()->with('status', 'Gift card cancelled.');
    }
}
