<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Platform\Actions\CancelQuotation;
use App\Domain\Platform\Actions\CreateQuotation;
use App\Domain\Platform\Models\PlatformAuditLog;
use App\Domain\Platform\Models\Quotation;
use App\Domain\Platform\Models\SubscriptionPlan;
use App\Domain\Platform\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StoreQuotationRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class QuotationController extends Controller
{
    public function index(): View
    {
        return view('platform.quotations.index', [
            'quotations' => Quotation::with(['tenant', 'plan'])->latest()->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('platform.quotations.create', [
            'tenants' => Tenant::orderBy('name')->get(),
            'plans' => SubscriptionPlan::where('is_active', true)->orderBy('price')->get(),
        ]);
    }

    public function store(StoreQuotationRequest $request, CreateQuotation $action): RedirectResponse
    {
        $tenant = Tenant::findOrFail($request->validated('tenant_id'));
        $plan = SubscriptionPlan::findOrFail($request->validated('subscription_plan_id'));

        $quotation = $action->execute(
            tenant: $tenant,
            plan: $plan,
            createdBy: Auth::guard('platform')->user(),
            amountOverride: $request->validated('amount'),
            notes: $request->validated('notes'),
        );

        PlatformAuditLog::record(
            Auth::guard('platform')->user(),
            'quotation.created',
            'Quotation',
            $quotation->id,
            $tenant->id,
            ['quotation_number' => $quotation->quotation_number, 'amount' => (float) $quotation->amount],
        );

        return redirect()->route('platform.quotations.show', $quotation)->with('status', 'Quotation created.');
    }

    public function show(Quotation $quotation): View
    {
        $quotation->load(['tenant', 'plan.modules', 'createdBy', 'invoice']);

        return view('platform.quotations.show', ['quotation' => $quotation]);
    }

    public function cancel(Quotation $quotation, CancelQuotation $action): RedirectResponse
    {
        $action->execute($quotation);

        PlatformAuditLog::record(
            Auth::guard('platform')->user(),
            'quotation.cancelled',
            'Quotation',
            $quotation->id,
            $quotation->tenant_id,
        );

        return back()->with('status', 'Quotation cancelled.');
    }
}
