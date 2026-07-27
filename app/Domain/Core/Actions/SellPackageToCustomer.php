<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Customer;
use App\Domain\Core\Models\CustomerPackage;
use App\Domain\Core\Models\Package;
use Illuminate\Support\Facades\DB;

/**
 * Records the sale directly (method/reference/price_paid) rather than
 * through the Invoice/GST engine — see docs/decisions/README.md D-006.
 * The recipe is snapshotted into CustomerPackageItem rows at the moment of
 * sale so a later edit to the template's recipe never retroactively
 * changes an already-sold instance (CLAUDE.md §45).
 */
class SellPackageToCustomer
{
    public function execute(
        Branch $branch,
        Customer $customer,
        Package $package,
        float $pricePaid,
        string $purchaseMethod,
        ?string $purchaseReference,
        ?int $createdBy,
    ): CustomerPackage {
        return DB::transaction(function () use ($branch, $customer, $package, $pricePaid, $purchaseMethod, $purchaseReference, $createdBy) {
            $purchasedAt = now();

            $customerPackage = new CustomerPackage([
                'customer_id' => $customer->id,
                'package_id' => $package->id,
                'branch_id' => $branch->id,
                'price_paid' => $pricePaid,
                'purchase_method' => $purchaseMethod,
                'purchase_reference' => $purchaseReference,
                'purchased_at' => $purchasedAt,
                'expires_at' => $purchasedAt->copy()->addDays($package->validity_days),
                'created_by' => $createdBy,
            ]);
            $customerPackage->status = 'active';
            $customerPackage->save();

            foreach ($package->services()->get() as $service) {
                $customerPackage->items()->create([
                    'service_id' => $service->id,
                    'quantity_purchased' => $service->pivot->quantity,
                ]);
            }

            return $customerPackage;
        });
    }
}
