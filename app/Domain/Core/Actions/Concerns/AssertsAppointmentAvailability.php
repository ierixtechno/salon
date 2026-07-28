<?php

namespace App\Domain\Core\Actions\Concerns;

use App\Domain\Core\Models\Appointment;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\EmployeeSchedule;
use App\Domain\Core\Models\Holiday;
use App\Domain\Core\Models\Resource;
use App\Models\User;
use Carbon\Carbon;

/**
 * Shared by BookAppointment and RescheduleAppointment — every one of these
 * checks is time-sensitive and must be re-verified fresh, inside the
 * caller's locked transaction, immediately before insert/update
 * (SKILL.md §16/§17: "availability shown to a user is never a guarantee").
 * See BookAppointment's class docblock for the full concurrency reasoning.
 */
trait AssertsAppointmentAvailability
{
    private function assertWithinBusinessHours(Branch $branch, Carbon $startsAt, Carbon $endsAt): void
    {
        $localStart = $startsAt->copy()->setTimezone($branch->effectiveTimezone());
        $localEnd = $endsAt->copy()->setTimezone($branch->effectiveTimezone());
        $hours = $branch->effectiveHoursFor((int) $localStart->format('w'));

        abort_if($hours->is_closed, 409, 'The branch is closed on the selected day.');

        $opens = Carbon::parse($localStart->toDateString().' '.$hours->opens_at, $branch->effectiveTimezone());
        $closes = Carbon::parse($localStart->toDateString().' '.$hours->closes_at, $branch->effectiveTimezone());

        abort_if($localStart->lt($opens) || $localEnd->gt($closes), 409, 'The selected time is outside the branch\'s business hours.');
    }

    private function assertNotOnHoliday(Branch $branch, Carbon $startsAt): void
    {
        $localDate = $startsAt->copy()->setTimezone($branch->effectiveTimezone())->toDateString();

        $isHoliday = Holiday::where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branch->id))
            ->whereDate('date', $localDate)
            ->exists();

        abort_if($isHoliday, 409, 'The branch is closed for a holiday on the selected day.');
    }

    private function assertWithinEmployeeSchedule(User $employee, Branch $branch, Carbon $startsAt, Carbon $endsAt): void
    {
        $localStart = $startsAt->copy()->setTimezone($branch->effectiveTimezone());
        $localEnd = $endsAt->copy()->setTimezone($branch->effectiveTimezone());

        $schedule = EmployeeSchedule::where('user_id', $employee->id)
            ->where('branch_id', $branch->id)
            ->where('day_of_week', (int) $localStart->format('w'))
            ->first();

        abort_if(! $schedule, 409, 'This staff member is not scheduled to work at this branch on the selected day.');

        $shiftStart = Carbon::parse($localStart->toDateString().' '.$schedule->starts_at, $branch->effectiveTimezone());
        $shiftEnd = Carbon::parse($localStart->toDateString().' '.$schedule->ends_at, $branch->effectiveTimezone());

        abort_if($localStart->lt($shiftStart) || $localEnd->gt($shiftEnd), 409, 'The selected time is outside this staff member\'s working hours.');
    }

    /**
     * $ignoreAppointmentId excludes the appointment being rescheduled from
     * its own conflict check (it would otherwise always "conflict" with
     * itself).
     */
    private function assertEmployeeFree(User $employee, Carbon $startsAt, Carbon $endsAt, int $bufferMinutes, ?int $ignoreAppointmentId = null): void
    {
        $candidateBusyEnd = $endsAt->copy()->addMinutes($bufferMinutes);

        $existing = Appointment::where('user_id', $employee->id)
            ->when($ignoreAppointmentId, fn ($q) => $q->whereKeyNot($ignoreAppointmentId))
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->whereBetween('starts_at', [$startsAt->copy()->subHours(15), $candidateBusyEnd])
            ->with('service:id,buffer_minutes,travel_buffer_minutes')
            ->get();

        foreach ($existing as $row) {
            // A non-branch appointment's busy time includes its own travel
            // buffer too — otherwise a back-to-back home/venue booking could
            // slot in right after this one ends, before the employee could
            // realistically have traveled away from it.
            $rowBufferMinutes = ($row->service->buffer_minutes ?? 0)
                + ($row->service_mode !== 'branch' ? ($row->service->travel_buffer_minutes ?? 0) : 0);
            $existingBusyEnd = $row->ends_at->copy()->addMinutes($rowBufferMinutes);

            if ($startsAt->lt($existingBusyEnd) && $candidateBusyEnd->gt($row->starts_at)) {
                abort(409, 'This staff member is no longer available at the selected time.');
            }
        }
    }

    /**
     * `capacity` > 1 means this resource can host that many simultaneous
     * appointments (e.g. a couple spa room, CLAUDE.md §14 Resource
     * Management) — a conflict only exists once the overlapping count would
     * reach capacity, not on the first overlap.
     */
    private function assertResourceFree(Resource $resource, Carbon $startsAt, Carbon $endsAt, ?int $ignoreAppointmentId = null): void
    {
        $candidateBusyEnd = $endsAt->copy()->addMinutes($resource->turnaround_minutes);

        $existing = Appointment::where('resource_id', $resource->id)
            ->when($ignoreAppointmentId, fn ($q) => $q->whereKeyNot($ignoreAppointmentId))
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->whereBetween('starts_at', [$startsAt->copy()->subHours(15), $candidateBusyEnd])
            ->get();

        $overlapping = 0;

        foreach ($existing as $row) {
            $existingBusyEnd = $row->ends_at->copy()->addMinutes($resource->turnaround_minutes);

            if ($startsAt->lt($existingBusyEnd) && $candidateBusyEnd->gt($row->starts_at)) {
                $overlapping++;
            }
        }

        abort_if($overlapping >= $resource->capacity, 409, 'The selected room/resource is no longer available at the selected time.');
    }
}
