<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\LeaveRequest;
use App\Domain\Core\Models\LeaveType;
use App\Models\User;
use Illuminate\Support\Carbon;

class RequestLeave
{
    public function execute(User $employee, LeaveType $leaveType, Carbon $startDate, Carbon $endDate, ?string $reason, ?int $createdBy): LeaveRequest
    {
        abort_if($endDate->lt($startDate), 422, 'End date cannot be before the start date.');

        $request = new LeaveRequest([
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'reason' => $reason,
            'created_by' => $createdBy,
        ]);
        $request->days = $startDate->diffInDays($endDate) + 1;
        $request->status = 'pending';
        $request->save();

        return $request;
    }
}
