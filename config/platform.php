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

];
