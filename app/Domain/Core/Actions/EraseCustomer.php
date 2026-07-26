<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\AuditLog;
use App\Domain\Core\Models\Customer;
use Illuminate\Support\Facades\DB;

/**
 * DPDP erasure request (D-004, docs/decisions/README.md): anonymizes the
 * customer row in place — never deletes it, since Phase 5/6 will add
 * appointments/invoices holding a foreign key to customer_id that must
 * never dangle (CLAUDE.md §36/§45).
 *
 * Free-text notes ARE deleted (CLAUDE.md §36 explicitly calls sensitive
 * notes/media deletable). The consent ledger is deliberately left intact —
 * it is evidence of what was consented to and when, not personal content.
 */
class EraseCustomer
{
    public function execute(Customer $customer, ?int $actingUserId): void
    {
        DB::transaction(function () use ($customer, $actingUserId) {
            $customer->notes()->delete();

            $customer->update([
                'name' => 'Erased Customer',
                'email' => null,
                'phone' => null,
                'date_of_birth' => null,
                'gender' => null,
                'tags' => null,
                'preferences' => null,
                'marketing_consent' => false,
                'is_active' => false,
                'erased_at' => now(),
            ]);

            AuditLog::create([
                'tenant_id' => $customer->tenant_id,
                'user_id' => $actingUserId,
                'action' => 'customer.erased',
                'entity_type' => 'Customer',
                'entity_id' => $customer->id,
                'meta' => [],
            ]);
        });
    }
}
