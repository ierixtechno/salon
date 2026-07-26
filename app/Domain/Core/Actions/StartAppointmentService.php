<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\Appointment;

class StartAppointmentService
{
    public function execute(Appointment $appointment): Appointment
    {
        abort_unless($appointment->canTransitionTo('in_service'), 409, "Cannot start an appointment that is currently {$appointment->status}.");

        // status/started_at are deliberately not in $fillable (CLAUDE.md
        // §28), so update() would silently drop them — set directly.
        $appointment->status = 'in_service';
        $appointment->started_at = now();
        $appointment->save();

        return $appointment;
    }
}
