<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment Gateway Driver
    |--------------------------------------------------------------------------
    |
    | Default to 'null' — throws a clear "not configured yet" error instead
    | of calling a real gateway. CLAUDE.md §37: never store raw card
    | details; use the provider's own hosted checkout/order flow. Set to
    | 'razorpay' once test (or live) keys are in config/services.php.
    | Must map to a class implementing
    | App\Domain\Platform\Contracts\PaymentGatewayProvider, registered in
    | App\Providers\AppServiceProvider::register().
    |
    */

    'driver' => env('PAYMENTS_DRIVER', 'null'),

];
