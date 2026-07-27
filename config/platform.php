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
    | Trial length
    |--------------------------------------------------------------------------
    */

    'trial_days' => env('PLATFORM_TRIAL_DAYS', 14),

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
