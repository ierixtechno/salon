<?php

namespace App\Domain\Platform\Payments;

use App\Domain\Platform\Contracts\PaymentGatewayProvider;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

/**
 * Thin wrapper over the official razorpay/razorpay SDK — chosen over a
 * hand-rolled HTTP client specifically for verifyPayment()'s HMAC-SHA256
 * signature check (CLAUDE.md §37/§42: payment signature verification is
 * easy to get subtly wrong by hand, and the SDK is the vendor's own,
 * actively maintained implementation).
 */
class RazorpayPaymentGatewayProvider implements PaymentGatewayProvider
{
    public function __construct(private readonly Api $api) {}

    public function createOrder(string $receiptId, string $amount, string $currency = 'INR'): array
    {
        $order = $this->api->order->create([
            'receipt' => $receiptId,
            'amount' => (int) round(((float) $amount) * 100), // Razorpay expects paise
            'currency' => $currency,
        ]);

        return [
            'order_id' => $order['id'],
            'key' => config('services.razorpay.key'),
        ];
    }

    public function verifyPayment(string $orderId, string $paymentId, string $signature): bool
    {
        try {
            $this->api->utility->verifyPaymentSignature([
                'razorpay_order_id' => $orderId,
                'razorpay_payment_id' => $paymentId,
                'razorpay_signature' => $signature,
            ]);

            return true;
        } catch (SignatureVerificationError) {
            return false;
        }
    }
}
