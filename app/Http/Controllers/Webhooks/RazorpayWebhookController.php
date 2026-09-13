<?php

namespace App\Http\Controllers\Webhooks;

use App\Domain\Platform\Actions\ProcessRazorpayWebhookPayment;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Server-to-server payment confirmation (CLAUDE.md §37/§42) — a resilience
 * backup for TenantBillingController::confirmPayment(): that flow only
 * fires if the customer's browser stays alive long enough to deliver the
 * client-side callback. Razorpay calls this endpoint directly regardless of
 * what happens to the browser, so a captured payment is never silently
 * lost. Authenticated by Razorpay's own HMAC-SHA256 webhook signature
 * (config('services.razorpay.webhook_secret') — a *separate* secret from
 * the checkout key/secret, generated in the Razorpay dashboard), not a
 * Laravel session — this route sits outside every auth group and is
 * CSRF-exempt (see bootstrap/app.php), same as any provider webhook must be
 * (CLAUDE.md §31).
 */
class RazorpayWebhookController extends Controller
{
    public function handle(Request $request, ProcessRazorpayWebhookPayment $action): Response
    {
        $secret = config('services.razorpay.webhook_secret');
        abort_if(blank($secret), 503, 'Webhook not configured.');

        $signature = (string) $request->header('X-Razorpay-Signature');
        abort_if(blank($signature), 400, 'Missing signature.');

        $expected = hash_hmac('sha256', $request->getContent(), $secret);
        abort_unless(hash_equals($expected, $signature), 400, 'Invalid signature.');

        $payload = json_decode($request->getContent(), true);

        // Razorpay sends many event types on one endpoint (refunds, order
        // events, etc.) — only payment.captured is actionable here; every
        // other event is acknowledged and ignored, never an error.
        if (is_array($payload) && ($payload['event'] ?? null) === 'payment.captured') {
            $action->execute($payload);
        }

        return response()->noContent();
    }
}
