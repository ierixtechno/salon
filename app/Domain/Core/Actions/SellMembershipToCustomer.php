<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Customer;
use App\Domain\Core\Models\CustomerMembership;
use App\Domain\Core\Models\MembershipPlan;

/**
 * Records the sale directly rather than through the Invoice/GST engine —
 * see docs/decisions/README.md D-006 (same scope note as
 * SellPackageToCustomer).
 */
class SellMembershipToCustomer
{
    public function execute(
        Branch $branch,
        Customer $customer,
        MembershipPlan $plan,
        float $pricePaid,
        string $purchaseMethod,
        ?string $purchaseReference,
        ?int $createdBy,
    ): CustomerMembership {
        $startsAt = now();

        $membership = new CustomerMembership([
            'customer_id' => $customer->id,
            'membership_plan_id' => $plan->id,
            'branch_id' => $branch->id,
            'price_paid' => $pricePaid,
            'purchase_method' => $purchaseMethod,
            'purchase_reference' => $purchaseReference,
            'starts_at' => $startsAt,
            'expires_at' => $startsAt->copy()->addDays($plan->validity_days),
            'created_by' => $createdBy,
        ]);
        $membership->status = 'active';
        $membership->save();

        return $membership;
    }
}
