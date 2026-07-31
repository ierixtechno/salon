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

];
