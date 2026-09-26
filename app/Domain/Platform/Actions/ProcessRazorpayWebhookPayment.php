<?php

namespace App\Domain\Platform\Actions;

use App\Domain\Platform\Models\PlatformInvoice;
use App\Domain\Platform\Models\Quotation;
use Illuminate\Support\Facades\Log;

/**
 * Server-to-server backup for the browser-side confirm in
 * TenantBillingController::confirmPayment() — signature verification
 * happens in RazorpayWebhookController before this ever runs, so this
 * class only has to worry about *which* payment it is and whether it's
 * still safe to apply (CLAUDE.md §37: verify expected event, amount,
 * currency, idempotency — the signature/provider check is the caller's
 * job).
 *
 * Every branch below is a deliberate no-op, not an error: a webhook that
 * doesn't match anything we can act on must still be acknowledged (200/204)
 * so Razorpay doesn't retry it forever. Problems are logged, not thrown.
 */
class ProcessRazorpayWebhookPayment
{
    public function __construct(private readonly PayQuotation $payQuotation) {}

    public function execute(array $payload): void
    {
        $entity = data_get($payload, 'payload.payment.entity');

        if (! is_array($entity)) {
            return;
        }

        $orderId = $entity['order_id'] ?? null;
        $paymentId = $entity['id'] ?? null;

        if (blank($orderId) || blank($paymentId)) {
            Log::warning('Razorpay webhook: payment.captured payload missing order_id/payment id.', [
                'event' => $payload['event'] ?? null,
            ]);

            return;
        }

        $quotation = Quotation::where('razorpay_order_id', $orderId)->first();

        if (! $quotation) {
            // A paid quotation is deleted, so a duplicate delivery (or the browser
            // confirm having won the race) lands here — expected, not an error.
            if (PlatformInvoice::where('payment_reference', $paymentId)->exists()) {
                return;
            }

            Log::warning('Razorpay webhook: no quotation matches this order_id.', ['order_id' => $orderId]);

            return;
        }

        if ($quotation->status !== 'pending') {
            // Already paid (most likely the browser-side confirm got there
            // first) or cancelled. Duplicate webhook delivery is expected
            // Razorpay behaviour, not an error — must stay a silent no-op.
            return;
        }

        $currency = $entity['currency'] ?? null;

        if ($currency !== 'INR') {
            Log::error('Razorpay webhook: currency mismatch, payment not applied.', [
                'quotation_id' => $quotation->id, 'order_id' => $orderId, 'currency' => $currency,
            ]);

            return;
        }

        // Quotation amounts are rupees (DECIMAL 12,2); Razorpay reports
        // paise. Comparing in integer paise avoids float rounding noise.
        $expectedPaise = (int) round(((float) $quotation->total_amount) * 100);
        $receivedPaise = (int) ($entity['amount'] ?? 0);

        if ($receivedPaise !== $expectedPaise) {
            Log::error('Razorpay webhook: amount mismatch, payment not applied.', [
                'quotation_id' => $quotation->id, 'order_id' => $orderId,
                'expected_paise' => $expectedPaise, 'received_paise' => $receivedPaise,
            ]);

            return;
        }

        $this->payQuotation->execute($quotation, 'razorpay', $paymentId);
    }
}
