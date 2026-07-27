<?php

namespace App\Domain\Core\Contracts;

/**
 * CLAUDE.md §42: external integrations must be behind provider interfaces.
 * Swapping in the real WhatsApp Business API once opt-in/registration
 * requirements are met (CLAUDE.md §36) is a config change
 * (NOTIFICATIONS_WHATSAPP_DRIVER) plus a new class implementing this
 * interface — no caller changes.
 */
interface WhatsAppProvider
{
    /**
     * @return string a provider-assigned reference/message id
     *
     * @throws \RuntimeException on delivery failure
     */
    public function send(string $toPhone, string $message): string;
}
