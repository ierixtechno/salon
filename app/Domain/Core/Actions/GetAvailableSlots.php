<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\Appointment;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\EmployeeSchedule;
use App\Domain\Core\Models\Holiday;
use App\Domain\Core\Models\Service;
use App\Domain\Core\Models\ServiceVariant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A read-only PREVIEW for the public booking UI (CLAUDE.md §75) —
 * deliberately never authoritative. It auto-scans every employee capable
 * of the service (public booking doesn't let a customer pick a specific
 * staff member — CLAUDE.md §75 "never expose more than needed", the full
 * staff roster included) and reports a slot as open if at least one of
 * them appears free. BookAppointment re-verifies the actual chosen
 * employee fresh, inside a lock, at submit time — this class's output is
 * never trusted as a guarantee (SKILL.md §16/§17), and doesn't touch
 * resources (public booking doesn't assign one; see PublicBookingController).
 */
class GetAvailableSlots
{
    private const STEP_MINUTES = 15;

    public function execute(Branch $branch, Service $service, ?ServiceVariant $variant, Carbon $date): array
    {
        $timezone = $branch->effectiveTimezone();
        $localDate = $date->copy()->setTimezone($timezone)->startOfDay();
        $dayOfWeek = (int) $localDate->format('w');

        $hours = $branch->effectiveHoursFor($dayOfWeek);
        if ($hours->is_closed) {
            return [];
        }

        $isHoliday = Holiday::where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branch->id))
            ->whereDate('date', $localDate->toDateString())
            ->exists();
        if ($isHoliday) {
            return [];
        }

        $employees = $service->capableEmployees()->get();
        if ($employees->isEmpty()) {
            return [];
        }

        $durationMinutes = $variant?->effectiveDurationMinutes() ?? $service->duration_minutes;
        $bufferMinutes = $service->buffer_minutes;

        $opens = Carbon::parse($localDate->toDateString().' '.$hours->opens_at, $timezone);
        $closes = Carbon::parse($localDate->toDateString().' '.$hours->closes_at, $timezone);

        $schedules = EmployeeSchedule::whereIn('user_id', $employees->pluck('id'))
            ->where('branch_id', $branch->id)
            ->where('day_of_week', $dayOfWeek)
            ->get()
            ->keyBy('user_id');

        if ($schedules->isEmpty()) {
            return [];
        }

        $existingByEmployee = Appointment::whereIn('user_id', $employees->pluck('id'))
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->whereBetween('starts_at', [$localDate->copy()->subHours(15), $localDate->copy()->endOfDay()->addHours(15)])
            ->with('service:id,buffer_minutes')
            ->get()
            ->groupBy('user_id');

        $now = now($timezone);
        $isToday = $localDate->isSameDay($now);

        $slots = [];
        $cursor = $opens->copy();

        while ($cursor->copy()->addMinutes($durationMinutes)->lte($closes)) {
            $slotStart = $cursor->copy();

            if (! ($isToday && $slotStart->lte($now))
                && $this->anyEmployeeFree($employees, $schedules, $existingByEmployee, $localDate, $timezone, $slotStart, $durationMinutes, $bufferMinutes)) {
                $slots[] = $slotStart->copy()->utc();
            }

            $cursor->addMinutes(self::STEP_MINUTES);
        }

        return $slots;
    }

    private function anyEmployeeFree(
        Collection $employees,
        Collection $schedules,
        Collection $existingByEmployee,
        Carbon $localDate,
        string $timezone,
        Carbon $slotStart,
        int $durationMinutes,
        int $bufferMinutes,
    ): bool {
        $slotEnd = $slotStart->copy()->addMinutes($durationMinutes);
        $busyEnd = $slotEnd->copy()->addMinutes($bufferMinutes);

        foreach ($employees as $employee) {
            $schedule = $schedules->get($employee->id);
            if (! $schedule) {
                continue;
            }

            $shiftStart = Carbon::parse($localDate->toDateString().' '.$schedule->starts_at, $timezone);
            $shiftEnd = Carbon::parse($localDate->toDateString().' '.$schedule->ends_at, $timezone);
            if ($slotStart->lt($shiftStart) || $slotEnd->gt($shiftEnd)) {
                continue;
            }

            $conflict = false;
            foreach ($existingByEmployee->get($employee->id, collect()) as $row) {
                $existingBusyEnd = $row->ends_at->copy()->addMinutes($row->service->buffer_minutes ?? 0);
                if ($slotStart->lt($existingBusyEnd) && $busyEnd->gt($row->starts_at)) {
                    $conflict = true;
                    break;
                }
            }

            if (! $conflict) {
                return true;
            }
        }

        return false;
    }
}
