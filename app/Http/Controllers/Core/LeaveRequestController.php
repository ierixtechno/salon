<?php

namespace App\Http\Controllers\Core;

use App\Domain\Core\Actions\ApproveLeave;
use App\Domain\Core\Actions\CancelLeave;
use App\Domain\Core\Actions\RejectLeave;
use App\Domain\Core\Actions\RequestLeave;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\LeaveRequest;
use App\Domain\Core\Models\LeaveType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\DecideLeaveRequestRequest;
use App\Http\Requests\Core\StoreLeaveRequestRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class LeaveRequestController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:leave.view')->only(['index']);
        $this->middleware('can:leave.request')->only(['my', 'store']);
    }

    /**
     * All requests across the tenant — the approver's queue.
     */
    public function index(): View
    {
        return view('core.leave.index', [
            'leaveRequests' => LeaveRequest::with(['user', 'leaveType'])->latest('start_date')->get(),
        ]);
    }

    /**
     * My own requests + the form to submit a new one.
     */
    public function my(): View
    {
        $user = Auth::guard('web')->user();

        return view('core.leave.my', [
            'leaveRequests' => LeaveRequest::where('user_id', $user->id)->with('leaveType')->latest('start_date')->get(),
            'leaveTypes' => LeaveType::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreLeaveRequestRequest $request, RequestLeave $action): RedirectResponse
    {
        $user = Auth::guard('web')->user();

        $action->execute(
            employee: $user,
            leaveType: LeaveType::findOrFail($request->validated('leave_type_id')),
            startDate: Carbon::parse($request->validated('start_date')),
            endDate: Carbon::parse($request->validated('end_date')),
            reason: $request->validated('reason'),
            createdBy: $user->id,
        );

        return redirect()->route('leave.my')->with('status', 'Leave requested.');
    }

    public function approve(DecideLeaveRequestRequest $request, LeaveRequest $leaveRequest, ApproveLeave $action): RedirectResponse
    {
        $user = Auth::guard('web')->user();
        $branch = $this->resolveApprovalBranch($user, $leaveRequest);

        $action->execute($leaveRequest, $branch, $user->id);

        return back()->with('status', 'Leave approved.');
    }

    public function reject(DecideLeaveRequestRequest $request, LeaveRequest $leaveRequest, RejectLeave $action): RedirectResponse
    {
        $action->execute($leaveRequest, $request->validated('reason'), Auth::guard('web')->id());

        return back()->with('status', 'Leave rejected.');
    }

    public function cancel(LeaveRequest $leaveRequest, CancelLeave $action): RedirectResponse
    {
        $user = Auth::guard('web')->user();
        abort_unless($leaveRequest->user_id === $user->id || $user->can('leave.approve'), 403);

        $action->execute($leaveRequest, request('reason'));

        return back()->with('status', 'Leave cancelled.');
    }

    /**
     * Attendance rows created by an approval need a branch — use the
     * employee's own first accessible branch (they may not have one
     * explicitly assigned if all_branches is set, in which case fall back
     * to the tenant's first active branch).
     */
    private function resolveApprovalBranch($approver, LeaveRequest $leaveRequest): Branch
    {
        $employee = $leaveRequest->user;

        $branch = $employee->all_branches
            ? Branch::where('is_active', true)->orderBy('name')->first()
            : $employee->branches()->where('branches.is_active', true)->orderBy('name')->first();

        abort_unless($branch, 422, 'This employee has no active branch to record attendance against.');

        return $branch;
    }
}
