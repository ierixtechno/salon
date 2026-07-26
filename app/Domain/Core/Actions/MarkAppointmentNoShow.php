<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\Appointment;

class MarkAppointmentNoShow
{
    public function execute(Appointment $appointment): Appointment
    {
        abort_unless($appointment->canTransitionTo('no_show'), 409, "Cannot mark as no-show an appointment that is currently {$appointment->status}.");

        // status/no_show_at are deliberately not in $fillable (CLAUDE.md
        // §28), so update() would silently drop them — set directly.
        $appointment->status = 'no_show';
        $appointment->no_show_at = now();
        $appointment->save();

        return $appointment;
    }
}
