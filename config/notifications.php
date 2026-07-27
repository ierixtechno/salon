<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SMS / WhatsApp Providers
    |--------------------------------------------------------------------------
    |
    | Default to 'null' — logs what would have been sent without calling a
    | real gateway. CLAUDE.md §36: before switching either driver to a real
    | provider, confirm DLT/TRAI SMS registration and/or WhatsApp Business
    | API opt-in requirements are met. Each driver key must map to a class
    | implementing the corresponding App\Domain\Core\Contracts interface,
    | registered in App\Providers\AppServiceProvider::register().
    |
    */

    'sms' => [
        'driver' => env('NOTIFICATIONS_SMS_DRIVER', 'null'),
    ],

    'whatsapp' => [
        'driver' => env('NOTIFICATIONS_WHATSAPP_DRIVER', 'null'),
    ],

];
