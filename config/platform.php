<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Super Admin seed credentials
    |--------------------------------------------------------------------------
    |
    | Used only by database\seeders\PlatformAdminSeeder to create the first
    | Super Admin account on a fresh install. Change these in .env before any
    | shared or production deployment.
    |
    */

    'admin_email' => env('PLATFORM_ADMIN_EMAIL', 'admin@example.com'),
    'admin_password' => env('PLATFORM_ADMIN_PASSWORD', 'password'),

    /*
    |--------------------------------------------------------------------------
    | Super Admin console brand name
    |--------------------------------------------------------------------------
    |
    | Deliberately separate from APP_NAME/config('app.name') — that name is
    | shown to tenants (salon/beauty/spa businesses using the app), while
    | this one is shown only inside the Platform (Super Admin) guard.
    |
    */

    'brand_name' => env('PLATFORM_BRAND_NAME', 'StyloBiz'),

    /*
    |--------------------------------------------------------------------------
    | Support contact shown in billing emails
    |--------------------------------------------------------------------------
    |
    | Optional. When set, quotation/payment/renewal emails end with
    | "Questions? Write to <address>". Left blank rather than defaulting to
    | MAIL_FROM_ADDRESS, since that is often a no-reply placeholder nobody
    | actually reads.
    |
    */

    'support_email' => env('PLATFORM_SUPPORT_EMAIL'),

    /*
    |--------------------------------------------------------------------------
    | Default tenant timezone/currency
    |--------------------------------------------------------------------------
    |
    | D-003 (docs/decisions/README.md): India is the sole target market, so
    | every tenant is defaulted to IST/INR at onboarding rather than asking —
    | India has a single national timezone, and D-002 already restricts a
    | tenant to one currency for its lifetime. Kept configurable (not
    | hardcoded inline) in case that market assumption is ever revisited.
    |
    */

    'default_timezone' => env('PLATFORM_DEFAULT_TIMEZONE', 'Asia/Kolkata'),
    'default_currency' => env('PLATFORM_DEFAULT_CURRENCY', 'INR'),

    /*
    |--------------------------------------------------------------------------
    | GST on Platform Billing
    |--------------------------------------------------------------------------
    |
    | Applies to StyloBiz billing its own tenants (Quotation/PlatformInvoice)
    | — a different, newer path than the tenant-level POS invoicing GST
    | (Service::tax_rate_percent, CLAUDE.md §21) already built in Phase 6.
    | 18% is the standard GST rate for software/SaaS services in India
    | (SAC 998313). gstin stays blank until Super Admin has a real one —
    | same "fill in later" precedent as the Razorpay keys.
    |
    | 'state' is the platform's own registered state (Tenant::billing_state
    | is compared against this — see CreateQuotation) to determine
    | CGST+SGST (same state as the platform) vs IGST (different state).
    | Unlike Phase 6's tenant-to-customer invoicing — which still only does
    | CGST+SGST, IGST deliberately deferred there per D-003 in
    | docs/decisions/README.md, since a tenant's own customers' state isn't
    | tracked — Platform Billing DOES need both, because StyloBiz's tenants
    | are spread across India while the platform itself has one fixed home
    | state.
    |
    */

    'gst_rate_percent' => (float) env('PLATFORM_GST_RATE_PERCENT', 18),
    'gstin' => env('PLATFORM_GSTIN'),
    'gst_sac_code' => env('PLATFORM_GST_SAC_CODE', '998313'),
    'state' => env('PLATFORM_STATE', 'Haryana'),

    /*
    |--------------------------------------------------------------------------
    | UPI QR payment (manual reconciliation)
    |--------------------------------------------------------------------------
    |
    | A scannable UPI QR on a pending Quotation — needs no payment gateway
    | integration at all, just the business's own UPI VPA. Not
    | auto-reconciled (there's no webhook for a plain UPI transfer the way
    | there is for Razorpay — see ProcessRazorpayWebhookPayment): Super
    | Admin still confirms the payment landed and uses the existing
    | "Record payment" action (QuotationController::recordPayment)
    | afterward. Blank until a real VPA is configured — see
    | BuildUpiPaymentUri, which returns null (QR hidden) until then.
    |
    */

    'upi_vpa' => env('PLATFORM_UPI_VPA'),
    'upi_payee_name' => env('PLATFORM_UPI_PAYEE_NAME', env('PLATFORM_BRAND_NAME', 'StyloBiz')),

];
