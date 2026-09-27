<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Platform\Actions\DecideBranchReductionRequest;
use App\Domain\Platform\Models\BranchReductionRequest;
use App\Domain\Platform\Models\PlatformAuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\RejectBranchReductionRequestRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class BranchReductionRequestController extends Controller
{
    public function index(): View
    {
        return view('platform.branch-reduction-requests.index', [
            'requests' => BranchReductionRequest::with(['tenant', 'requestedBy'])->latest()->paginate(20),
        ]);
    }

    public function approve(BranchReductionRequest $branchReductionRequest, DecideBranchReductionRequest $action): RedirectResponse
    {
        $admin = Auth::guard('platform')->user();
        $action->approve($branchReductionRequest, $admin);

        PlatformAuditLog::record(
            $admin,
            'tenant.branch_reduction_approved',
            'Tenant',
            $branchReductionRequest->tenant_id,
            $branchReductionRequest->tenant_id,
            ['from' => $branchReductionRequest->current_branch_count, 'to' => $branchReductionRequest->requested_branch_count],
        );

        return back()->with('status', 'Branch reduction approved — it takes effect from the tenant\'s next renewal.');
    }

    public function reject(RejectBranchReductionRequestRequest $request, BranchReductionRequest $branchReductionRequest, DecideBranchReductionRequest $action): RedirectResponse
    {
        $admin = Auth::guard('platform')->user();
        $action->reject($branchReductionRequest, $admin, $request->validated('reason'));

        PlatformAuditLog::record(
            $admin,
            'tenant.branch_reduction_rejected',
            'Tenant',
            $branchReductionRequest->tenant_id,
            $branchReductionRequest->tenant_id,
            ['requested' => $branchReductionRequest->requested_branch_count],
        );

        return back()->with('status', 'Branch reduction request rejected.');
    }
}
