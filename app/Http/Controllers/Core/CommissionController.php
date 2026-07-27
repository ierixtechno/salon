<?php

namespace App\Http\Controllers\Core;

use App\Domain\Core\Actions\UpsertCommissionRule;
use App\Domain\Core\Models\CommissionEntry;
use App\Domain\Core\Models\EmployeeProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\StoreCommissionRuleRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class CommissionController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:commission.manage')->only(['rules', 'updateRule']);
        $this->middleware('can:commission.view')->only(['ledger']);
    }

    /**
     * Every employee and their current commission rule, if any.
     */
    public function rules(): View
    {
        return view('core.commission.rules', [
            'employees' => EmployeeProfile::with(['user', 'user.commissionRule'])->get(),
        ]);
    }

    public function updateRule(StoreCommissionRuleRequest $request, User $user, UpsertCommissionRule $action): RedirectResponse
    {
        abort_unless($user->tenant_id === Auth::guard('web')->user()->tenant_id, 404);

        $action->execute(
            employee: $user,
            type: $request->validated('type'),
            rate: (float) $request->validated('rate'),
            isActive: $request->boolean('is_active'),
        );

        return back()->with('status', 'Commission rule saved.');
    }

    public function ledger(): View
    {
        return view('core.commission.ledger', [
            'entries' => CommissionEntry::with(['user', 'invoice'])->latest()->paginate(30),
        ]);
    }

    /**
     * My own commission ledger — no permission gate beyond authentication,
     * same self-service precedent as attendance/leave "my" pages.
     */
    public function my(): View
    {
        $user = Auth::guard('web')->user();

        return view('core.commission.my', [
            'entries' => CommissionEntry::where('user_id', $user->id)->with('invoice')->latest()->limit(60)->get(),
            'total' => CommissionEntry::where('user_id', $user->id)->sum('amount'),
        ]);
    }
}
