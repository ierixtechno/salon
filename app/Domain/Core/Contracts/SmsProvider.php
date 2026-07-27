<?php

namespace App\Domain\Core\Contracts;

/**
 * CLAUDE.md §42: external integrations must be behind provider interfaces.
 * Swapping in a real provider (MSG91, Twilio, etc.) once DLT/TRAI
 * registration is complete is a config change (NOTIFICATIONS_SMS_DRIVER)
 * plus a new class implementing this interface — no caller changes.
 */
interface SmsProvider
{
    /**
     * @return string a provider-assigned reference/message id
     *
     * @throws \RuntimeException on delivery failure
     */
    public function send(string $toPhone, string $message): string;
}
