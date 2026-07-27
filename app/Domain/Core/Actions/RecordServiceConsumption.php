<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\Appointment;
use App\Domain\Core\Models\StockMovement;

/**
 * Deliberately a manual, staff-triggered action (a button on a completed
 * appointment), not an automatic hook wired into Phase 5's
 * CompleteAppointment — silently changing that already-shipped action's
 * behavior isn't something to invent without asking (CLAUDE.md §70).
 *
 * Negative stock IS allowed here (`allowNegative: true` on every movement)
 * — refusing to record that a service consumed its usual products just
 * because the recorded balance is running low would be worse than letting
 * the ledger go negative until the branch restocks; the physical product
 * was used regardless of what the system thinks is on the shelf.
 */
class RecordServiceConsumption
{
    public function execute(Appointment $appointment, ?int $performedBy): array
    {
        abort_unless($appointment->status === 'completed', 409, 'Consumption can only be recorded for a completed appointment.');

        $alreadyRecorded = StockMovement::where('reference_type', 'appointment_consumption')
            ->where('reference_id', $appointment->id)
            ->exists();
        abort_if($alreadyRecorded, 409, 'Consumption has already been recorded for this appointment.');

        $movements = [];

        foreach ($appointment->service->consumables as $consumable) {
            $movements[] = app(RecordStockMovement::class)->execute(
                branch: $appointment->branch,
                product: $consumable->product,
                type: 'service_consumption',
                quantity: -(float) $consumable->quantity_per_use,
                referenceType: 'appointment_consumption',
                referenceId: $appointment->id,
                performedBy: $performedBy,
                allowNegative: true,
            );
        }

        return $movements;
    }
}
