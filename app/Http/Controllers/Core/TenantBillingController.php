<?php

namespace App\Http\Controllers\Core;

use App\Domain\Platform\Actions\PayQuotation;
use App\Domain\Platform\Contracts\PaymentGatewayProvider;
use App\Domain\Platform\Models\PlatformInvoice;
use App\Domain\Platform\Models\Quotation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\ConfirmQuotationPaymentRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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

        $quotation->load('plan.modules');

        return view('core.billing.quotations.show', ['quotation' => $quotation]);
    }

    public function createCheckout(Quotation $quotation, PaymentGatewayProvider $provider): JsonResponse
    {
        abort_unless($quotation->tenant_id === Auth::user()->tenant_id, 404);
        abort_if($quotation->status !== 'pending', 409, 'This quotation is no longer payable.');

        try {
            $order = $provider->createOrder(receiptId: $quotation->quotation_number, amount: $quotation->amount);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $quotation->update(['razorpay_order_id' => $order['order_id']]);

        return response()->json([
            'order_id' => $order['order_id'],
            'key' => $order['key'],
            'amount' => $quotation->amount,
        ]);
    }

    public function confirmPayment(ConfirmQuotationPaymentRequest $request, Quotation $quotation, PaymentGatewayProvider $provider, PayQuotation $payQuotation): RedirectResponse
    {
        abort_unless($quotation->tenant_id === Auth::user()->tenant_id, 404);
        abort_if($quotation->status !== 'pending', 409, 'This quotation is no longer payable.');
        abort_unless($quotation->razorpay_order_id === $request->validated('razorpay_order_id'), 422, 'Order mismatch.');

        $verified = $provider->verifyPayment(
            orderId: $request->validated('razorpay_order_id'),
            paymentId: $request->validated('razorpay_payment_id'),
            signature: $request->validated('razorpay_signature'),
        );

        abort_unless($verified, 422, 'Payment could not be verified.');

        $payQuotation->execute($quotation, 'razorpay', $request->validated('razorpay_payment_id'));

        return redirect()->route('billing.quotations.show', $quotation)->with('status', 'Payment received — invoice generated.');
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

        $invoice->load('plan');

        return view('core.billing.invoices.show', ['invoice' => $invoice]);
    }
}
