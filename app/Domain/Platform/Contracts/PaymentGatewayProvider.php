<?php

namespace App\Domain\Platform\Contracts;

/**
 * CLAUDE.md §42: external integrations must be behind provider interfaces.
 * Swapping in the real Razorpay driver once test/live API keys are added is
 * a config change (PAYMENTS_DRIVER) — no caller changes.
 */
interface PaymentGatewayProvider
{
    /**
     * @return array{order_id: string, key: string}
     *
     * @throws \RuntimeException if the gateway isn't configured or the API call fails
     */
    public function createOrder(string $receiptId, string $amount, string $currency = 'INR'): array;

    public function verifyPayment(string $orderId, string $paymentId, string $signature): bool;
}
