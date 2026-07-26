<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\Appointment;

class CompleteAppointment
{
    public function execute(Appointment $appointment): Appointment
    {
        abort_unless($appointment->canTransitionTo('completed'), 409, "Cannot complete an appointment that is currently {$appointment->status}.");

        // status/completed_at are deliberately not in $fillable (CLAUDE.md
        // §28), so update() would silently drop them — set directly.
        $appointment->status = 'completed';
        $appointment->completed_at = now();
        $appointment->save();

        return $appointment;
    }
}
