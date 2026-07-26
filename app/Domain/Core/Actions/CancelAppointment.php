<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\Appointment;

class CancelAppointment
{
    public function execute(Appointment $appointment, ?string $reason = null): Appointment
    {
        abort_unless($appointment->canTransitionTo('cancelled'), 409, "Cannot cancel an appointment that is currently {$appointment->status}.");

        // status/cancelled_at/cancellation_reason are deliberately not in
        // $fillable (CLAUDE.md §28), so update() would silently drop them
        // — set directly.
        $appointment->status = 'cancelled';
        $appointment->cancelled_at = now();
        $appointment->cancellation_reason = $reason;
        $appointment->save();

        return $appointment;
    }
}
