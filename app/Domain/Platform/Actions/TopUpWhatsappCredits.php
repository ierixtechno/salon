<?php

namespace App\Domain\Platform\Actions;

use App\Domain\Core\Models\WhatsappCreditTransaction;
use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Platform\Models\Tenant;

/**
 * The only way a tenant's WhatsApp credit balance ever goes up — always
 * Super Admin, matching a purchase made outside the app (there is no
 * tenant self-service checkout for this, by design). See
 * ChargeWhatsappCredit for the debit side.
 */
class TopUpWhatsappCredits
{
    public function execute(Tenant $tenant, int $amount, ?PlatformAdmin $admin, ?string $reason): WhatsappCreditTransaction
    {
        abort_unless($amount > 0, 422, 'Top-up amount must be a positive number of credits.');

        return WhatsappCreditTransaction::create([
            'tenant_id' => $tenant->id,
            'type' => 'credit',
            'amount' => $amount,
            'reason' => $reason,
            'platform_admin_id' => $admin?->id,
        ]);
    }
}
