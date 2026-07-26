<?php

namespace App\Http\Controllers\Core;

use App\Domain\Core\Actions\UpdateBranchModules;
use App\Domain\Core\Actions\UpsertBusinessHours;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\BusinessHour;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\StoreBranchRequest;
use App\Http\Requests\Core\UpdateBranchHoursRequest;
use App\Http\Requests\Core\UpdateBranchModulesRequest;
use App\Http\Requests\Core\UpdateBranchRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class BranchController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Branch::class, 'branch');
    }

    public function index(): View
    {
        return view('core.branches.index', [
            'branches' => Branch::withCount('resources')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('core.branches.create', [
            'tenantModules' => current_tenant()->tenantModules()->where('enabled', true)->with('module')->get(),
        ]);
    }

    public function store(StoreBranchRequest $request): RedirectResponse
    {
        $branch = Branch::create($request->validated());

        return redirect()->route('branches.edit', $branch)->with('status', 'Branch created.');
    }

    public function edit(Branch $branch): View
    {
        $existingHours = BusinessHour::where('owner_type', $branch->getMorphClass())
            ->where('owner_id', $branch->id)
            ->get()
            ->keyBy('day_of_week');

        $hours = collect(range(0, 6))->map(fn ($day) => $existingHours->get($day) ?? new BusinessHour([
            'day_of_week' => $day,
            'opens_at' => null,
            'closes_at' => null,
            'is_closed' => false,
        ]));

        return view('core.branches.edit', [
            'branch' => $branch,
            'tenantModules' => current_tenant()->tenantModules()->where('enabled', true)->with('module')->get(),
            'enabledModuleCodes' => $branch->modules()->wherePivot('enabled', true)->pluck('code'),
            'hours' => $hours,
        ]);
    }

    public function update(UpdateBranchRequest $request, Branch $branch): RedirectResponse
    {
        $branch->update($request->validated());

        return back()->with('status', 'Branch updated.');
    }

    /**
     * Branches are never hard-deleted once they may carry operational
     * history — this deactivates instead (CLAUDE.md §45/§48: lifecycle
     * states over deletion). There is deliberately no UI path to a true
     * destructive delete here.
     */
    public function destroy(Branch $branch): RedirectResponse
    {
        $branch->update(['is_active' => false]);

        return redirect()->route('branches.index')->with('status', 'Branch deactivated.');
    }

    public function updateModules(UpdateBranchModulesRequest $request, Branch $branch, UpdateBranchModules $updateBranchModules): RedirectResponse
    {
        $this->authorize('update', $branch);

        $updateBranchModules->execute($branch, $request->validated('modules', []));

        return back()->with('status', 'Branch modules updated.');
    }

    public function updateHours(UpdateBranchHoursRequest $request, Branch $branch, UpsertBusinessHours $upsertBusinessHours): RedirectResponse
    {
        $this->authorize('update', $branch);

        $upsertBusinessHours->execute($branch, $branch->tenant_id, $request->validated('hours'));

        return back()->with('status', 'Branch hours updated.');
    }
}
