<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\AttendanceRecord;
use App\Domain\Core\Models\Branch;
use App\Models\User;

/**
 * Self-service clock-in. If today's record was already marked on_leave (an
 * approved leave request) or absent, clocking in overrides it to present —
 * the employee physically showing up is the most authoritative signal.
 */
class ClockIn
{
    public function execute(User $employee, Branch $branch): AttendanceRecord
    {
        $today = now($branch->effectiveTimezone())->toDateString();

        $record = AttendanceRecord::where('user_id', $employee->id)->where('date', $today)->first();
        abort_if($record && $record->check_in_at, 409, 'Already clocked in today.');

        $record ??= new AttendanceRecord(['user_id' => $employee->id, 'branch_id' => $branch->id, 'date' => $today]);
        $record->branch_id = $branch->id;
        $record->check_in_at = now();
        $record->status = 'present';
        $record->save();

        return $record;
    }
}
