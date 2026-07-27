<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\AttendanceRecord;
use App\Domain\Core\Models\Branch;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Manual marking by a Manager/Owner — upserts on (user_id, date), so
 * re-marking the same day corrects rather than duplicates.
 */
class MarkAttendance
{
    public function execute(User $employee, Branch $branch, Carbon $date, string $status, ?string $notes, ?int $markedBy): AttendanceRecord
    {
        abort_unless(in_array($status, AttendanceRecord::STATUSES, true), 422, 'Invalid attendance status.');

        // Deliberately not updateOrCreate(): `status` is not fillable (see
        // AttendanceRecord), so a first-time insert would otherwise omit
        // the column entirely and violate its NOT NULL constraint. A single
        // firstOrNew()+save() keeps this to one INSERT/UPDATE either way.
        $record = AttendanceRecord::firstOrNew(['user_id' => $employee->id, 'date' => $date->toDateString()]);
        $record->fill(['branch_id' => $branch->id, 'notes' => $notes, 'marked_by' => $markedBy]);
        $record->status = $status;
        $record->save();

        return $record;
    }
}
