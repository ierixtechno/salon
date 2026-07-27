<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\AttendanceRecord;
use App\Domain\Core\Models\Branch;
use App\Models\User;

class ClockOut
{
    public function execute(User $employee, Branch $branch): AttendanceRecord
    {
        $today = now($branch->effectiveTimezone())->toDateString();

        $record = AttendanceRecord::where('user_id', $employee->id)->where('date', $today)->first();
        abort_unless($record && $record->check_in_at, 409, 'Not clocked in yet today.');
        abort_if($record->check_out_at, 409, 'Already clocked out today.');

        $record->check_out_at = now();
        $record->save();

        return $record;
    }
}
