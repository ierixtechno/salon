<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Customer;
use App\Domain\Core\Models\CustomerMembership;
use App\Domain\Core\Models\MembershipPlan;
use Illuminate\Support\Facades\DB;

/**
 * Bills the sale on a GST invoice (CreateSaleInvoice) and records the
 * membership against it — see docs/decisions/README.md D-006 (superseded).
 */
class SellMembershipToCustomer
{
    public function execute(
        Branch $branch,
        Customer $customer,
        MembershipPlan $plan,
        float $price,
        string $purchaseMethod,
        ?string $purchaseReference,
        ?int $createdBy,
    ): CustomerMembership {
        return DB::transaction(function () use ($branch, $customer, $plan, $price, $purchaseMethod, $purchaseReference, $createdBy) {
            $startsAt = now();

            $invoice = app(CreateSaleInvoice::class)->execute(
                branch: $branch,
                customer: $customer,
                description: 'Membership: '.$plan->name,
                price: $price,
                taxRatePercent: (float) $plan->tax_rate_percent,
                paymentMethod: $purchaseMethod,
                paymentReference: $purchaseReference,
                createdBy: $createdBy,
            );
            $membership = new CustomerMembership([
            'customer_id' => $customer->id,
            'membership_plan_id' => $plan->id,
            'branch_id' => $branch->id,
                'invoice_id' => $invoice?->id,
                'price_paid' => $invoice ? (float) $invoice->grand_total : $price,
            'purchase_method' => $purchaseMethod,
            'purchase_reference' => $purchaseReference,
            'starts_at' => $startsAt,
            'expires_at' => $startsAt->copy()->addDays($plan->validity_days),
            'created_by' => $createdBy,
        ]);
            $membership->status = 'active';
            $membership->save();

            return $membership;
        });
    }
}
