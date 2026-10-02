<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Platform\Actions\DecideEmployeeReductionRequest;
use App\Domain\Platform\Models\EmployeeReductionRequest;
use App\Domain\Platform\Models\PlatformAuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\RejectEmployeeReductionRequestRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class EmployeeReductionRequestController extends Controller
{
    public function index(): View
    {
        return view('platform.employee-reduction-requests.index', [
            'requests' => EmployeeReductionRequest::with(['tenant', 'requestedBy'])->latest()->paginate(20),
        ]);
    }

    public function approve(EmployeeReductionRequest $employeeReductionRequest, DecideEmployeeReductionRequest $action): RedirectResponse
    {
        $admin = Auth::guard('platform')->user();
        $action->approve($employeeReductionRequest, $admin);

        PlatformAuditLog::record(
            $admin,
            'tenant.employee_reduction_approved',
            'Tenant',
            $employeeReductionRequest->tenant_id,
            $employeeReductionRequest->tenant_id,
            ['from' => $employeeReductionRequest->current_extra_user_count, 'to' => $employeeReductionRequest->requested_extra_user_count],
        );

        return back()->with('status', 'Employee reduction approved — it takes effect from the tenant\'s next renewal.');
    }

    public function reject(RejectEmployeeReductionRequestRequest $request, EmployeeReductionRequest $employeeReductionRequest, DecideEmployeeReductionRequest $action): RedirectResponse
    {
        $admin = Auth::guard('platform')->user();
        $action->reject($employeeReductionRequest, $admin, $request->validated('reason'));

        PlatformAuditLog::record(
            $admin,
            'tenant.employee_reduction_rejected',
            'Tenant',
            $employeeReductionRequest->tenant_id,
            $employeeReductionRequest->tenant_id,
            ['requested' => $employeeReductionRequest->requested_extra_user_count],
        );

        return back()->with('status', 'Employee reduction request rejected.');
    }
}
