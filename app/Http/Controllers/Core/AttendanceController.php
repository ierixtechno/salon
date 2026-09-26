<?php

namespace App\Http\Controllers\Core;

use App\Domain\Core\Actions\ClockIn;
use App\Domain\Core\Actions\ClockOut;
use App\Domain\Core\Actions\MarkAttendance;
use App\Domain\Core\Models\AttendanceRecord;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\EmployeeProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\StoreAttendanceMarkRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class AttendanceController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:attendance.view')->only(['index']);
        $this->middleware('can:attendance.mark')->only(['mark']);
        $this->middleware('can:attendance.clock-self')->only(['clockIn', 'clockOut']);
    }

    /**
     * The daily register for a branch — every active employee assigned to
     * it, with today's (or the requested date's) status, so a Manager can
     * mark everyone in one pass.
     */
    public function index(Request $request): View
    {
        $branch = $this->resolveBranch($request);
        $date = $request->date('date') ?? now($branch?->effectiveTimezone() ?? 'UTC')->startOfDay();

        $employees = $branch
            ? EmployeeProfile::with('user')->get()->filter(fn (EmployeeProfile $e) => $e->user->canAccessBranch($branch))->values()
            : collect();

        $records = $branch
            ? AttendanceRecord::where('branch_id', $branch->id)
                ->whereDate('date', $date->toDateString())
                ->get()
                ->keyBy('user_id')
            : collect();

        return view('core.attendance.index', [
            'branches' => $this->accessibleBranches(),
            'branch' => $branch,
            'date' => $date,
            'employees' => $employees,
            'records' => $records,
        ]);
    }

    public function mark(StoreAttendanceMarkRequest $request, MarkAttendance $action): RedirectResponse
    {
        $action->execute(
            employee: User::findOrFail($request->validated('user_id')),
            branch: Branch::findOrFail($request->validated('branch_id')),
            date: Carbon::parse($request->validated('date')),
            status: $request->validated('status'),
            notes: $request->validated('notes'),
            markedBy: Auth::guard('web')->id(),
        );

        return back()->with('status', 'Attendance recorded.');
    }

    public function clockIn(Request $request, ClockIn $action): RedirectResponse
    {
        $branch = Branch::findOrFail($request->integer('branch_id'));
        abort_unless(Auth::guard('web')->user()->canAccessBranch($branch), 403);

        $action->execute(Auth::guard('web')->user(), $branch);

        return back()->with('status', 'Clocked in.');
    }

    public function clockOut(Request $request, ClockOut $action): RedirectResponse
    {
        $branch = Branch::findOrFail($request->integer('branch_id'));
        abort_unless(Auth::guard('web')->user()->canAccessBranch($branch), 403);

        $action->execute(Auth::guard('web')->user(), $branch);

        return back()->with('status', 'Clocked out.');
    }

    /**
     * Self-service view of my own attendance history — no permission gate
     * beyond authentication, same as Profile (CLAUDE.md §9 is already
     * satisfied: it's always scoped to the current user, never an id from
     * the request).
     */
    public function my(): View
    {
        $user = Auth::guard('web')->user();

        return view('core.attendance.my', [
            'records' => AttendanceRecord::with('branch.tenant')->where('user_id', $user->id)->orderByDesc('date')->limit(60)->get(),
            'accessibleBranches' => $this->accessibleBranches(),
        ]);
    }

    private function resolveBranch(Request $request): ?Branch
    {
        $accessible = $this->accessibleBranches();

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
