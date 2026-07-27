<?php

namespace App\Http\Controllers\Core;

use App\Domain\Core\Actions\CloseCashRegister;
use App\Domain\Core\Actions\OpenCashRegister;
use App\Domain\Core\Actions\RecordCashMovement;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\CashRegisterSession;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\CloseCashRegisterRequest;
use App\Http\Requests\Core\OpenCashRegisterRequest;
use App\Http\Requests\Core\StoreCashMovementRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class CashRegisterController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:cash-register.view')->only(['index', 'history']);
        $this->middleware('can:cash-register.manage')->only(['open', 'close', 'cashIn', 'cashOut']);
    }

    public function index(Request $request): View
    {
        $branches = $this->accessibleBranches();
        $branch = $this->resolveBranch($request, $branches);

        $session = $branch
            ? CashRegisterSession::where('branch_id', $branch->id)->where('status', 'open')->with('cashMovements')->first()
            : null;

        return view('core.cash-register.index', [
            'branches' => $branches,
            'branch' => $branch,
            'session' => $session,
        ]);
    }

    public function history(Request $request): View
    {
        $branches = $this->accessibleBranches();
        $branch = $this->resolveBranch($request, $branches);

        $query = CashRegisterSession::where('status', 'closed')->with(['branch', 'openedBy', 'closedBy'])->latest('closed_at');
        if ($branch) {
            $query->where('branch_id', $branch->id);
        } else {
            $query->whereIn('branch_id', $branches->pluck('id'));
        }

        return view('core.cash-register.history', [
            'branches' => $branches,
            'branch' => $branch,
            'sessions' => $query->paginate(20)->withQueryString(),
        ]);
    }

    public function open(OpenCashRegisterRequest $request, OpenCashRegister $action): RedirectResponse
    {
        $branch = Branch::findOrFail($request->validated('branch_id'));

        $action->execute(
            branch: $branch,
            openingCash: (float) $request->validated('opening_cash'),
            openedBy: Auth::guard('web')->id(),
            notes: $request->validated('notes'),
        );

        return redirect()->route('cash-register.index', ['branch_id' => $branch->id])->with('status', 'Cash register opened.');
    }

    public function close(CloseCashRegisterRequest $request, CashRegisterSession $cashRegisterSession, CloseCashRegister $action): RedirectResponse
    {
        $this->authorizeBranchAccess($cashRegisterSession);

        $action->execute(
            session: $cashRegisterSession,
            actualClosing: (float) $request->validated('actual_closing'),
            closedBy: Auth::guard('web')->id(),
            notes: $request->validated('notes'),
        );

        return redirect()->route('cash-register.index', ['branch_id' => $cashRegisterSession->branch_id])->with('status', 'Cash register closed.');
    }

    public function cashIn(StoreCashMovementRequest $request, CashRegisterSession $cashRegisterSession, RecordCashMovement $action): RedirectResponse
    {
        $this->authorizeBranchAccess($cashRegisterSession);

        $action->execute(
            session: $cashRegisterSession,
            type: 'cash_in',
            amount: (float) $request->validated('amount'),
            reason: $request->validated('reason'),
            createdBy: Auth::guard('web')->id(),
        );

        return back()->with('status', 'Cash in recorded.');
    }

    public function cashOut(StoreCashMovementRequest $request, CashRegisterSession $cashRegisterSession, RecordCashMovement $action): RedirectResponse
    {
        $this->authorizeBranchAccess($cashRegisterSession);

        $action->execute(
            session: $cashRegisterSession,
            type: 'cash_out',
            amount: -(float) $request->validated('amount'),
            reason: $request->validated('reason'),
            createdBy: Auth::guard('web')->id(),
        );

        return back()->with('status', 'Cash out recorded.');
    }

    private function authorizeBranchAccess(CashRegisterSession $session): void
    {
        abort_unless(Auth::guard('web')->user()->canAccessBranch($session->branch), 403);
    }

    private function resolveBranch(Request $request, Collection $accessible): ?Branch
    {
        if ($request->filled('branch_id')) {
            $requested = $accessible->firstWhere('id', (int) $request->integer('branch_id'));
            if ($requested) {
                return $requested;
            }
        }

        return $accessible->first();
    }

    private function accessibleBranches(): Collection
    {
        $user = Auth::guard('web')->user();

        return Branch::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->filter(fn (Branch $branch) => $user->canAccessBranch($branch))
            ->values();
    }
}
