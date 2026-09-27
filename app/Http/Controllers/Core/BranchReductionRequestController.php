<?php

namespace App\Http\Controllers\Core;

use App\Domain\Platform\Actions\RequestBranchReduction;
use App\Domain\Platform\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\StoreBranchReductionRequestRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class BranchReductionRequestController extends Controller
{
    public function store(StoreBranchReductionRequestRequest $request, RequestBranchReduction $action): RedirectResponse
    {
        $user = Auth::guard('web')->user();

        $action->execute(
            tenant: Tenant::findOrFail($user->tenant_id),
            requestedBy: $user,
            requestedBranchCount: (int) $request->validated('requested_branch_count'),
            reason: $request->validated('reason'),
        );

        return back()->with('status', 'Your request has been sent to Super Admin for approval.');
    }
}
