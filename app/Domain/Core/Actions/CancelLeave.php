<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\AttendanceRecord;
use App\Domain\Core\Models\LeaveRequest;
use Illuminate\Support\Facades\DB;

/**
 * A pending request can always be cancelled. An already-approved request
 * can only be cancelled while it hasn't started yet — once leave is under
 * way (or past), reversing it retroactively is a bigger decision than a
 * self-service cancel button should make. Cancelling an approved future
 * request removes the `on_leave` attendance rows ApproveLeave created for
 * it, since those days never actually happened.
 */
class CancelLeave
{
    public function execute(LeaveRequest $leaveRequest, ?string $reason): LeaveRequest
    {
        $wasApproved = $leaveRequest->status === 'approved';

        abort_unless($leaveRequest->canTransitionTo('cancelled'), 409, "Cannot cancel a leave request that is currently {$leaveRequest->status}.");
        abort_if($wasApproved && $leaveRequest->start_date->isPast(), 409, 'Cannot cancel a leave request that has already started.');

        return DB::transaction(function () use ($leaveRequest, $reason, $wasApproved) {
            $leaveRequest->status = 'cancelled';
            $leaveRequest->decision_reason = $reason;
            $leaveRequest->save();

            if ($wasApproved) {
                AttendanceRecord::where('user_id', $leaveRequest->user_id)
                    ->where('status', 'on_leave')
                    ->whereBetween('date', [$leaveRequest->start_date->toDateString(), $leaveRequest->end_date->toDateString()])
                    ->delete();
            }

            return $leaveRequest;
        });
    }
}
