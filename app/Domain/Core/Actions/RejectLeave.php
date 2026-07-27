<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\LeaveRequest;

class RejectLeave
{
    public function execute(LeaveRequest $leaveRequest, ?string $reason, ?int $decidedBy): LeaveRequest
    {
        abort_unless($leaveRequest->canTransitionTo('rejected'), 409, "Cannot reject a leave request that is currently {$leaveRequest->status}.");

        $leaveRequest->status = 'rejected';
        $leaveRequest->decided_by = $decidedBy;
        $leaveRequest->decided_at = now();
        $leaveRequest->decision_reason = $reason;
        $leaveRequest->save();

        return $leaveRequest;
    }
}
