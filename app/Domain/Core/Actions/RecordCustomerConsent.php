<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\Customer;
use App\Domain\Core\Models\CustomerConsent;
use Illuminate\Support\Facades\DB;

/**
 * Consent is a ledger, not a mutable flag (D-004, docs/decisions/README.md)
 * — every grant/revoke is its own row. `customers.marketing_consent` is
 * only a denormalized "current state" read model kept in sync here.
 */
class RecordCustomerConsent
{
    public function execute(Customer $customer, string $purpose, bool $granted, ?int $recordedBy, ?string $notes = null): CustomerConsent
    {
        return DB::transaction(function () use ($customer, $purpose, $granted, $recordedBy, $notes) {
            $consent = CustomerConsent::create([
                'tenant_id' => $customer->tenant_id,
                'customer_id' => $customer->id,
                'purpose' => $purpose,
                'granted' => $granted,
                'recorded_by' => $recordedBy,
                'notes' => $notes,
            ]);

            if ($purpose === 'marketing') {
                $customer->update(['marketing_consent' => $granted]);
            }

            return $consent;
        });
    }
}
