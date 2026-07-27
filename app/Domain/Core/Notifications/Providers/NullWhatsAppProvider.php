<?php

namespace App\Domain\Core\Notifications\Providers;

use App\Domain\Core\Contracts\WhatsAppProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The default driver until the WhatsApp Business API opt-in/registration
 * requirements are met (CLAUDE.md §36) — records what would have been
 * sent to the log instead of calling a real API. Never fails, never
 * incurs cost, never sends a real message. Swap
 * NOTIFICATIONS_WHATSAPP_DRIVER to a real provider once registered.
 */
class NullWhatsAppProvider implements WhatsAppProvider
{
    public function send(string $toPhone, string $message): string
    {
        $reference = 'null-whatsapp-'.Str::uuid();

        Log::info('[NullWhatsAppProvider] WhatsApp message not actually sent (no registered provider configured)', [
            'to' => $toPhone,
            'message' => $message,
            'reference' => $reference,
        ]);

        return $reference;
    }
}
