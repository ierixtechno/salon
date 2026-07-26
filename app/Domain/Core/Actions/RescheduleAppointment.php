<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Actions\Concerns\AssertsAppointmentAvailability;
use App\Domain\Core\Models\Appointment;
use App\Domain\Core\Models\Resource;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Moves an existing appointment to a new start time by re-running the same
 * availability checks BookAppointment uses — see its docblock for the full
 * concurrency reasoning. Only valid while the appointment hasn't progressed
 * past `confirmed` (CLAUDE.md §32 — rescheduling something already checked
 * in or further along doesn't make business sense; cancel and rebook
 * instead). Branch/employee/resource/service never change here — changing
 * who or where is a different appointment, not a reschedule of this one; a
 * caller wanting that should cancel and create a new booking instead.
 */
class RescheduleAppointment
{
    use AssertsAppointmentAvailability;

    public function execute(Appointment $appointment, Carbon $newStartsAt): Appointment
    {
        abort_unless(
            in_array($appointment->status, ['pending', 'confirmed'], true),
            409,
            "Cannot reschedule an appointment that is currently {$appointment->status}.",
        );

        $branch = $appointment->branch;
        $employee = $appointment->employee;
        $resource = $appointment->resource;
        $durationMinutes = $appointment->starts_at->diffInMinutes($appointment->ends_at);

        // See BookAppointment's docblock/comment: Eloquent's `datetime` cast
        // doesn't normalize timezones on write, so every write path must
        // force UTC itself.
        $newStartsAt = $newStartsAt->copy()->utc();
        $newEndsAt = $newStartsAt->copy()->addMinutes($durationMinutes);
        $bufferMinutes = $appointment->service->buffer_minutes;

        return DB::transaction(function () use ($appointment, $branch, $employee, $resource, $newStartsAt, $newEndsAt, $bufferMinutes) {
            User::whereKey($employee->id)->lockForUpdate()->first();
            if ($resource) {
                Resource::whereKey($resource->id)->lockForUpdate()->first();
            }

            $this->assertWithinBusinessHours($branch, $newStartsAt, $newEndsAt);
            $this->assertNotOnHoliday($branch, $newStartsAt);
            $this->assertWithinEmployeeSchedule($employee, $branch, $newStartsAt, $newEndsAt);
            $this->assertEmployeeFree($employee, $newStartsAt, $newEndsAt, $bufferMinutes, ignoreAppointmentId: $appointment->id);

            if ($resource) {
                $this->assertResourceFree($resource, $newStartsAt, $newEndsAt, ignoreAppointmentId: $appointment->id);
            }

            $appointment->update([
                'starts_at' => $newStartsAt,
                'ends_at' => $newEndsAt,
            ]);

            return $appointment;
        });
    }
}
