<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\WhatsappCreditTransaction;
use App\Domain\Platform\Models\Tenant;

/**
 * WhatsApp is tenant-specific and credit-gated: a tenant must have been
 * topped up (Super Admin, see TopUpWhatsappCredits) before their messages
 * actually send — this is the single gate DeliverNotification calls
 * through. 1 credit = 1 WhatsApp message; a failed send is never charged
 * (see chargeForSuccessfulSend()).
 */
class ChargeWhatsappCredit
{
    /**
     * Called before attempting to send. False means "do not call the
     * provider" — the caller is responsible for marking the
     * NotificationLog 'skipped' itself.
     */
    public function hasBalance(int $tenantId): bool
    {
        return Tenant::find($tenantId)?->whatsappCreditBalance() >= 1;
    }

    /**
     * Called only after the provider confirms the message actually sent.
     */
    public function chargeForSuccessfulSend(int $tenantId, int $notificationLogId): void
    {
        WhatsappCreditTransaction::create([
            'tenant_id' => $tenantId,
            'type' => 'debit',
            'amount' => -1,
            'reference_type' => 'NotificationLog',
            'reference_id' => $notificationLogId,
            'reason' => 'WhatsApp message sent',
        ]);
    }
}
