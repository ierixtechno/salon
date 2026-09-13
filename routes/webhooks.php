<?php

use App\Http\Controllers\Webhooks\RazorpayWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Provider webhooks (unauthenticated, signature-verified)
|--------------------------------------------------------------------------
|
| These have no Laravel session — the caller is Razorpay's own server, not
| a browser — so they sit outside every auth group and are CSRF-exempt
| (see bootstrap/app.php's validateCsrfTokens(except:)). Authenticity comes
| entirely from the provider-specific signature check inside the
| controller (CLAUDE.md §31/§37), never from anything in the request that
| a caller could simply assert (tenant_id, quotation id, etc.).
*/

Route::post('webhooks/razorpay', [RazorpayWebhookController::class, 'handle'])->name('webhooks.razorpay');
