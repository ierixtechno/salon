<?php

namespace App\Http\Controllers\Core;

use App\Domain\Platform\Actions\RequestEmployeeReduction;
use App\Domain\Platform\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\StoreEmployeeReductionRequestRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class EmployeeReductionRequestController extends Controller
{
    public function store(StoreEmployeeReductionRequestRequest $request, RequestEmployeeReduction $action): RedirectResponse
    {
        $user = Auth::guard('web')->user();

        $action->execute(
            tenant: Tenant::findOrFail($user->tenant_id),
            requestedBy: $user,
            requestedExtraUserCount: (int) $request->validated('requested_extra_user_count'),
            reason: $request->validated('reason'),
        );

        return back()->with('status', 'Your request has been sent to Super Admin for approval.');
    }
}
