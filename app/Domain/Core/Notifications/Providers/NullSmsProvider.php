<?php

namespace App\Domain\Core\Notifications\Providers;

use App\Domain\Core\Contracts\SmsProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The default driver until a tenant/platform has DLT/TRAI SMS registration
 * (CLAUDE.md §36) — records what would have been sent to the log instead
 * of calling a real gateway. Never fails, never incurs cost, never sends
 * a real message. Swap NOTIFICATIONS_SMS_DRIVER to a real provider once
 * registration is complete.
 */
class NullSmsProvider implements SmsProvider
{
    public function send(string $toPhone, string $message): string
    {
        $reference = 'null-sms-'.Str::uuid();

        Log::info('[NullSmsProvider] SMS not actually sent (no registered provider configured)', [
            'to' => $toPhone,
            'message' => $message,
            'reference' => $reference,
        ]);

        return $reference;
    }
}
