<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Customer;
use App\Domain\Core\Models\CustomerPackage;
use App\Domain\Core\Models\Package;
use Illuminate\Support\Facades\DB;

/**
 * Bills the sale on a GST invoice (CreateSaleInvoice) and records the
 * package instance against it — see docs/decisions/README.md D-006 (superseded).
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
        float $price,
        string $purchaseMethod,
        ?string $purchaseReference,
        ?int $createdBy,
    ): CustomerPackage {
        return DB::transaction(function () use ($branch, $customer, $package, $price, $purchaseMethod, $purchaseReference, $createdBy) {
            $purchasedAt = now();

            $invoice = app(CreateSaleInvoice::class)->execute(
                branch: $branch,
                customer: $customer,
                description: 'Package: '.$package->name,
                price: $price,
                taxRatePercent: (float) $package->tax_rate_percent,
                paymentMethod: $purchaseMethod,
                paymentReference: $purchaseReference,
                createdBy: $createdBy,
            );

            $customerPackage = new CustomerPackage([
                'customer_id' => $customer->id,
                'package_id' => $package->id,
                'branch_id' => $branch->id,
                'invoice_id' => $invoice?->id,
                // What was actually collected: the tax-inclusive invoice total.
                'price_paid' => $invoice ? (float) $invoice->grand_total : $price,
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
