<?php

/*
| Web Push (phone/desktop notifications for the installed app). Generate the
| key pair once with `php artisan webpush:generate-keys` and put the three
| values in .env. Leave them blank to switch push off — the in-app bell keeps
| working either way. Never change the keys later: every device would have to
| re-subscribe.
*/
return [
    'public_key' => env('VAPID_PUBLIC_KEY'),
    'private_key' => env('VAPID_PRIVATE_KEY'),
    'subject' => env('VAPID_SUBJECT', 'mailto:admin@example.com'),
];
