<?php

namespace App\Domain\Core\Actions;

use App\Domain\BeautyParlour\Models\SkinConsultation;
use App\Domain\BeautyParlour\Models\SkinProfile;
use App\Domain\Core\Models\AuditLog;
use App\Domain\Core\Models\Customer;
use App\Domain\Salon\Models\HairConsultation;
use App\Domain\Salon\Models\HairProfile;
use App\Domain\Spa\Models\SpaConsultation;
use App\Domain\Spa\Models\SpaProfile;
use Illuminate\Support\Facades\DB;

/**
 * DPDP erasure request (D-004, docs/decisions/README.md): anonymizes the
 * customer row in place — never deletes it, since Phase 5/6 will add
 * appointments/invoices holding a foreign key to customer_id that must
 * never dangle (CLAUDE.md §36/§45).
 *
 * Free-text notes ARE deleted (CLAUDE.md §36 explicitly calls sensitive
 * notes/media deletable), and so are the vertical hair/skin/spa
 * profiles + consultation history — CLAUDE.md §36 names "skin/hair
 * consultation notes" explicitly as the sensitive data this workflow must
 * reach. The consent ledger is deliberately left intact — it is evidence of
 * what was consented to and when, not personal content.
 */
class EraseCustomer
{
    public function execute(Customer $customer, ?int $actingUserId): void
    {
        DB::transaction(function () use ($customer, $actingUserId) {
            $customer->notes()->delete();

            HairProfile::where('customer_id', $customer->id)->delete();
            HairConsultation::where('customer_id', $customer->id)->delete();
            SkinProfile::where('customer_id', $customer->id)->delete();
            SkinConsultation::where('customer_id', $customer->id)->delete();
            SpaProfile::where('customer_id', $customer->id)->delete();
            SpaConsultation::where('customer_id', $customer->id)->delete();

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
