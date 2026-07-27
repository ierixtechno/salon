<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\AttendanceRecord;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\LeaveRequest;
use Illuminate\Support\Facades\DB;

/**
 * Approving also marks every day in the range `on_leave` on
 * attendance_records (upsert — approval takes precedence over whatever
 * was there before), so the attendance register and the leave ledger
 * never disagree with each other.
 */
class ApproveLeave
{
    public function execute(LeaveRequest $leaveRequest, Branch $branch, ?int $decidedBy): LeaveRequest
    {
        abort_unless($leaveRequest->canTransitionTo('approved'), 409, "Cannot approve a leave request that is currently {$leaveRequest->status}.");

        return DB::transaction(function () use ($leaveRequest, $branch, $decidedBy) {
            $leaveRequest->status = 'approved';
            $leaveRequest->decided_by = $decidedBy;
            $leaveRequest->decided_at = now();
            $leaveRequest->save();

            $date = $leaveRequest->start_date->copy();
            while ($date->lte($leaveRequest->end_date)) {
                // Deliberately not updateOrCreate(): `status` is not
                // fillable (see AttendanceRecord), so a first-time insert
                // would otherwise omit the column entirely and violate its
                // NOT NULL constraint.
                $record = AttendanceRecord::firstOrNew(['user_id' => $leaveRequest->user_id, 'date' => $date->toDateString()]);
                $record->fill(['branch_id' => $branch->id, 'marked_by' => $decidedBy]);
                $record->status = 'on_leave';
                $record->save();

                $date->addDay();
            }

            return $leaveRequest;
        });
    }
}
