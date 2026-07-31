<?php

namespace App\Domain\Platform\Payments;

use App\Domain\Platform\Contracts\PaymentGatewayProvider;

/**
 * The default driver until real Razorpay keys are added to .env
 * (PAYMENTS_DRIVER=null) — never calls a real API, never silently succeeds.
 * createOrder() throws so the caller can show a clear "not configured yet"
 * business error instead of a confusing downstream failure.
 */
class NullPaymentGatewayProvider implements PaymentGatewayProvider
{
    public function createOrder(string $receiptId, string $amount, string $currency = 'INR'): array
    {
        throw new \RuntimeException('Online payment is not configured yet. Please contact support.');
    }

    public function verifyPayment(string $orderId, string $paymentId, string $signature): bool
    {
        return false;
    }
}
