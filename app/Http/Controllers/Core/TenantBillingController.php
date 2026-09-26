<?php

namespace App\Http\Controllers\Core;

use App\Domain\Platform\Actions\PayQuotation;
use App\Domain\Platform\Actions\RequestExtraBranches;
use App\Domain\Platform\Actions\RequestPlanUpgrade;
use App\Domain\Platform\Contracts\PaymentGatewayProvider;
use App\Domain\Platform\Models\PlatformInvoice;
use App\Domain\Platform\Models\Quotation;
use App\Domain\Platform\Models\SubscriptionPlan;
use App\Domain\Platform\Models\Tenant;
use App\Domain\Platform\Support\RenderPlatformInvoicePdf;
use App\Domain\Platform\Support\RenderQuotationPdf;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\ConfirmQuotationPaymentRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Quotation/PlatformInvoice are platform-owned records, not BelongsToTenant
 * (see their model docblocks) — every method here explicitly checks
 * tenant_id ownership before touching a row. Route-model-binding alone
 * does NOT scope these to the current tenant (CLAUDE.md §32 IDOR
 * prevention).
 */
class TenantBillingController extends Controller
{
    public function plans(): View
    {
        $tenant = Tenant::findOrFail(Auth::user()->tenant_id);
        $currentSubscription = $tenant->currentSubscription();

        // The plan the tenant is on comes first; the rest stay cheapest-first.
        // (sortBy is stable, so equal keys keep the price ordering.)
        $plans = SubscriptionPlan::where('is_active', true)
            ->with(['features', 'modules'])
            ->orderBy('price')
            ->get()
            ->sortBy(fn (SubscriptionPlan $plan) => $plan->id === $currentSubscription?->subscription_plan_id ? 0 : 1)
            ->values();

        return view('core.billing.plans.index', [
            'plans' => $plans,
            'currentSubscription' => $currentSubscription,
        ]);
    }

    public function upgrade(SubscriptionPlan $plan, RequestPlanUpgrade $action): RedirectResponse
    {
        $tenant = Tenant::findOrFail(Auth::user()->tenant_id);

        $quotation = $action->execute($tenant, $plan);

        return redirect()->route('billing.quotations.show', $quotation)
            ->with('status', 'Upgrade quotation created — pay to complete the switch.');
    }

    public function addBranches(Request $request, RequestExtraBranches $action): RedirectResponse
    {
        $data = $request->validate(['additional' => ['required', 'integer', 'min:1', 'max:50']]);

        $quotation = $action->execute(Tenant::findOrFail(Auth::user()->tenant_id), (int) $data['additional']);

        return redirect()->route('billing.quotations.show', $quotation)
            ->with('status', 'Quotation created for the additional branches — pay to add them.');
    }

    public function quotations(): View
    {
        return view('core.billing.quotations.index', [
            'quotations' => Quotation::with('plan')
                ->where('tenant_id', Auth::user()->tenant_id)
                ->latest()
                ->paginate(20),
        ]);
    }

    public function showQuotation(Quotation $quotation): View
    {
        abort_unless($quotation->tenant_id === Auth::user()->tenant_id, 404);

        $quotation->load('plan.modules', 'tenant');

        return view('core.billing.quotations.show', ['quotation' => $quotation]);
    }

    public function createCheckout(Quotation $quotation, PaymentGatewayProvider $provider): JsonResponse
    {
        abort_unless($quotation->tenant_id === Auth::user()->tenant_id, 404);
        abort_if($quotation->status !== 'pending', 409, 'This quotation is no longer payable.');

        try {
            // Charge the tax-inclusive total, not the pre-GST amount.
            $order = $provider->createOrder(receiptId: $quotation->quotation_number, amount: $quotation->total_amount);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $quotation->update(['razorpay_order_id' => $order['order_id']]);

        return response()->json([
            'order_id' => $order['order_id'],
            'key' => $order['key'],
            'amount' => $quotation->total_amount,
        ]);
    }

    /**
     * `$quotationId` is deliberately not route-model-bound: once paid the quotation is
     * deleted, and if the Razorpay webhook got there first this browser confirm must land
     * on the invoice rather than a 404.
     */
    public function confirmPayment(ConfirmQuotationPaymentRequest $request, string $quotationId, PaymentGatewayProvider $provider, PayQuotation $payQuotation): RedirectResponse
    {
        $quotation = Quotation::find($quotationId);

        if (! $quotation) {
            $paid = PlatformInvoice::where('tenant_id', Auth::user()->tenant_id)
                ->where('payment_reference', $request->validated('razorpay_payment_id'))
                ->first();
            abort_unless($paid, 404);

            return redirect()->route('billing.invoices.show', $paid)->with('status', 'Payment received — invoice generated.');
        }

        abort_unless($quotation->tenant_id === Auth::user()->tenant_id, 404);
        abort_if($quotation->status !== 'pending', 409, 'This quotation is no longer payable.');
        abort_unless($quotation->razorpay_order_id === $request->validated('razorpay_order_id'), 422, 'Order mismatch.');

        $verified = $provider->verifyPayment(
            orderId: $request->validated('razorpay_order_id'),
            paymentId: $request->validated('razorpay_payment_id'),
            signature: $request->validated('razorpay_signature'),
        );

        abort_unless($verified, 422, 'Payment could not be verified.');

        $invoice = $payQuotation->execute($quotation, 'razorpay', $request->validated('razorpay_payment_id'));

        return redirect()->route('billing.invoices.show', $invoice)->with('status', 'Payment received — invoice generated.');
    }

    public function invoices(): View
    {
        return view('core.billing.invoices.index', [
            'invoices' => PlatformInvoice::with('plan')
                ->where('tenant_id', Auth::user()->tenant_id)
                ->latest('paid_at')
                ->paginate(20),
        ]);
    }

    public function showInvoice(PlatformInvoice $invoice): View
    {
        abort_unless($invoice->tenant_id === Auth::user()->tenant_id, 404);

        $invoice->load('plan', 'tenant');

        return view('core.billing.invoices.show', ['invoice' => $invoice]);
    }

    public function downloadQuotationPdf(Quotation $quotation, RenderQuotationPdf $renderer): Response
    {
        abort_unless($quotation->tenant_id === Auth::user()->tenant_id, 404);

        return response($renderer->render($quotation), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.str_replace(['/', '\\'], '-', $quotation->quotation_number).'.pdf"',
        ]);
    }

    public function downloadInvoicePdf(PlatformInvoice $invoice, RenderPlatformInvoicePdf $renderer): Response
    {
        abort_unless($invoice->tenant_id === Auth::user()->tenant_id, 404);

        return response($renderer->render($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.str_replace(['/', '\\'], '-', $invoice->invoice_number).'.pdf"',
        ]);
    }
}
