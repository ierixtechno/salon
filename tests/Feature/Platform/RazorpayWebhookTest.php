<?php

use App\Domain\Platform\Actions\CreateQuotation;
use App\Domain\Platform\Models\Module;
use App\Domain\Platform\Models\PlatformInvoice;
use App\Domain\Platform\Models\Quotation;
use App\Domain\Platform\Models\SubscriptionPlan;
use App\Domain\Platform\Models\Tenant;

// Tests post the raw JSON body directly (via $this->call, not
// postJson()/json()) with a signature computed over those exact bytes —
// using a helper that re-encodes the payload would no longer match a
// signature computed over the original string, same as Razorpay's own
// server would send.
function paymentCapturedPayload(string $orderId, string $paymentId, int $amountPaise, string $currency = 'INR'): array
{
    return [
        'entity' => 'event',
        'event' => 'payment.captured',
        'payload' => [
            'payment' => [
                'entity' => [
                    'id' => $paymentId,
                    'entity' => 'payment',
                    'order_id' => $orderId,
                    'amount' => $amountPaise,
                    'currency' => $currency,
                    'status' => 'captured',
                ],
            ],
        ],
    ];
}

function pendingQuotationWithOrder(string $orderId): Quotation
{
    $plan = SubscriptionPlan::where('code', 'growth')->firstOrFail();
    $plan->modules()->sync(Module::whereIn('code', ['salon'])->pluck('id'));

    $owner = onboard(['modules' => ['salon']]);

    $quotation = app(CreateQuotation::class)->execute(
        tenant: Tenant::findOrFail($owner->tenant_id),
        plan: $plan,
        createdBy: null,
    );
    $quotation->update(['razorpay_order_id' => $orderId]);

    return $quotation->fresh();
}

test('a valid payment.captured webhook pays the matching quotation', function () {
    config(['services.razorpay.webhook_secret' => 'whsec_test_secret']);

    $quotation = pendingQuotationWithOrder('order_abc123');
    $expectedPaise = (int) round(((float) $quotation->total_amount) * 100);

    $payload = paymentCapturedPayload('order_abc123', 'pay_xyz789', $expectedPaise);
    $body = json_encode($payload);
    $signature = hash_hmac('sha256', $body, 'whsec_test_secret');

    $response = $this->call('POST', '/webhooks/razorpay', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_RAZORPAY_SIGNATURE' => $signature,
    ], $body);

    $response->assertNoContent();

    expect(Quotation::find($quotation->id))->toBeNull(); // paid quotations are removed

    $invoice = PlatformInvoice::where('quotation_number', $quotation->quotation_number)->first();
    expect($invoice)->not->toBeNull();
    expect($invoice->payment_method)->toBe('razorpay');
    expect($invoice->payment_reference)->toBe('pay_xyz789');
});

test('an invalid signature is rejected and does not pay the quotation', function () {
    config(['services.razorpay.webhook_secret' => 'whsec_test_secret']);

    $quotation = pendingQuotationWithOrder('order_bad_sig');
    $payload = paymentCapturedPayload('order_bad_sig', 'pay_1', 100000);
    $body = json_encode($payload);

    $response = $this->call('POST', '/webhooks/razorpay', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_RAZORPAY_SIGNATURE' => 'not-the-real-signature',
    ], $body);

    $response->assertStatus(400);
    expect($quotation->fresh()->status)->toBe('pending');
});

test('a missing signature header is rejected', function () {
    config(['services.razorpay.webhook_secret' => 'whsec_test_secret']);

    $payload = paymentCapturedPayload('order_no_sig', 'pay_1', 100000);

    $response = $this->call('POST', '/webhooks/razorpay', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], json_encode($payload));

    $response->assertStatus(400);
});

test('the webhook refuses to process when no webhook secret is configured yet', function () {
    config(['services.razorpay.webhook_secret' => null]);

    $quotation = pendingQuotationWithOrder('order_unconfigured');
    $payload = paymentCapturedPayload('order_unconfigured', 'pay_1', (int) round(((float) $quotation->total_amount) * 100));
    $body = json_encode($payload);

    $response = $this->call('POST', '/webhooks/razorpay', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $body, 'anything'),
    ], $body);

    $response->assertStatus(503);
    expect($quotation->fresh()->status)->toBe('pending');
});

test('a duplicate webhook delivery on an already-paid quotation is a safe no-op', function () {
    config(['services.razorpay.webhook_secret' => 'whsec_test_secret']);

    $quotation = pendingQuotationWithOrder('order_dup');
    $expectedPaise = (int) round(((float) $quotation->total_amount) * 100);
    $payload = paymentCapturedPayload('order_dup', 'pay_dup', $expectedPaise);
    $body = json_encode($payload);
    $signature = hash_hmac('sha256', $body, 'whsec_test_secret');

    $this->call('POST', '/webhooks/razorpay', [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_SIGNATURE' => $signature,
    ], $body)->assertNoContent();

    // Razorpay redelivers the identical event — must not double-invoice.
    $this->call('POST', '/webhooks/razorpay', [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_SIGNATURE' => $signature,
    ], $body)->assertNoContent();

    expect(PlatformInvoice::where('quotation_number', $quotation->quotation_number)->count())->toBe(1);
});

test('an amount mismatch is not applied', function () {
    config(['services.razorpay.webhook_secret' => 'whsec_test_secret']);

    $quotation = pendingQuotationWithOrder('order_wrong_amount');
    $payload = paymentCapturedPayload('order_wrong_amount', 'pay_1', 1); // 1 paisa, not the real total
    $body = json_encode($payload);
    $signature = hash_hmac('sha256', $body, 'whsec_test_secret');

    $this->call('POST', '/webhooks/razorpay', [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_SIGNATURE' => $signature,
    ], $body)->assertNoContent();

    expect($quotation->fresh()->status)->toBe('pending');
    expect(PlatformInvoice::where('quotation_number', $quotation->quotation_number)->exists())->toBeFalse();
});

test('a webhook for an order that matches no quotation is acknowledged without error', function () {
    config(['services.razorpay.webhook_secret' => 'whsec_test_secret']);

    $payload = paymentCapturedPayload('order_never_created', 'pay_1', 100000);
    $body = json_encode($payload);
    $signature = hash_hmac('sha256', $body, 'whsec_test_secret');

    $this->call('POST', '/webhooks/razorpay', [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_SIGNATURE' => $signature,
    ], $body)->assertNoContent();
});

test('a non-payment.captured event is acknowledged and ignored', function () {
    config(['services.razorpay.webhook_secret' => 'whsec_test_secret']);

    $quotation = pendingQuotationWithOrder('order_other_event');
    $payload = [
        'entity' => 'event',
        'event' => 'payment.failed',
        'payload' => ['payment' => ['entity' => ['id' => 'pay_1', 'order_id' => 'order_other_event', 'amount' => 100000, 'currency' => 'INR']]],
    ];
    $body = json_encode($payload);
    $signature = hash_hmac('sha256', $body, 'whsec_test_secret');

    $this->call('POST', '/webhooks/razorpay', [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_SIGNATURE' => $signature,
    ], $body)->assertNoContent();

    expect($quotation->fresh()->status)->toBe('pending');
});
