<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\Appointment;
use App\Domain\Core\Models\CustomerPackageItem;
use App\Domain\Core\Models\PackageRedemption;
use Illuminate\Support\Facades\DB;

/**
 * A manual, staff-triggered redemption against a completed appointment —
 * same shape as RecordServiceConsumption (Phase 7): not silently wired
 * into CompleteAppointment (CLAUDE.md §70). Pre-redemption checks mirror
 * CLAUDE.md §14 Packages exactly: ownership, active status, validity,
 * applicable service, applicable branch, remaining quantity.
 *
 * Concurrency: the CustomerPackageItem row is locked before the
 * remaining-quantity check so two simultaneous redemption attempts for
 * the last remaining unit can never both succeed (.claude/skills/
 * beauty-saas-development/SKILL.md §18).
 */
class RedeemPackageItem
{
    public function execute(Appointment $appointment, CustomerPackageItem $item, ?int $redeemedBy): PackageRedemption
    {
        abort_unless($appointment->status === 'completed', 409, 'Only a completed appointment can redeem a package.');
        abort_unless($item->customerPackage->customer_id === $appointment->customer_id, 403, 'This package does not belong to the appointment\'s customer.');
        abort_unless($item->service_id === $appointment->service_id, 422, 'This package entitlement is not for the appointment\'s service.');
        abort_unless($item->service->isAvailableAtBranch($appointment->branch), 422, 'This service is not available at the redeeming branch.');
        abort_unless($item->customerPackage->isUsable(), 409, 'This package is not currently active or has expired.');
        abort_if(
            PackageRedemption::where('appointment_id', $appointment->id)->exists(),
            409,
            'A package redemption has already been recorded for this appointment.',
        );

        return DB::transaction(function () use ($appointment, $item, $redeemedBy) {
            $item = CustomerPackageItem::whereKey($item->id)->lockForUpdate()->firstOrFail();

            abort_if($item->quantityRemaining() <= 0, 409, 'No remaining quantity left on this package entitlement.');

            $item->quantity_redeemed++;
            $item->save();

            $redemption = PackageRedemption::create([
                'customer_package_item_id' => $item->id,
                'appointment_id' => $appointment->id,
                'branch_id' => $appointment->branch_id,
                'quantity' => 1,
                'redeemed_at' => now(),
                'redeemed_by' => $redeemedBy,
            ]);

            $customerPackage = $item->customerPackage()->lockForUpdate()->firstOrFail();
            $stillHasRemaining = $customerPackage->items()->get()->contains(fn ($i) => $i->quantityRemaining() > 0);
            if (! $stillHasRemaining) {
                $customerPackage->status = 'exhausted';
                $customerPackage->save();
            }

            return $redemption;
        });
    }
}
