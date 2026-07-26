<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\Appointment;

class CheckInAppointment
{
    public function execute(Appointment $appointment): Appointment
    {
        abort_unless($appointment->canTransitionTo('checked_in'), 409, "Cannot check in an appointment that is currently {$appointment->status}.");

        // status/checked_in_at are deliberately not in $fillable (CLAUDE.md
        // §28), so update() would silently drop them — set directly.
        $appointment->status = 'checked_in';
        $appointment->checked_in_at = now();
        $appointment->save();

        return $appointment;
    }
}
