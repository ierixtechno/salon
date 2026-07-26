<?php

namespace App\Http\Controllers\Core;

use App\Domain\Core\Actions\CreateEmployee;
use App\Domain\Core\Actions\UpdateEmployee;
use App\Domain\Core\Actions\UpsertEmployeeSchedule;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\EmployeeProfile;
use App\Domain\Core\Models\EmployeeSchedule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\CreateEmployeeRequest;
use App\Http\Requests\Core\UpdateEmployeeRequest;
use App\Http\Requests\Core\UpdateEmployeeScheduleRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Spatie\Permission\Models\Role;

class EmployeeController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(EmployeeProfile::class, 'employee');
    }

    public function index(): View
    {
        return view('core.employees.index', [
            'employees' => EmployeeProfile::with('user')->get(),
        ]);
    }

    public function create(): View
    {
        return view('core.employees.create', [
            'branches' => Branch::orderBy('name')->get(),
            'roles' => Role::where('tenant_id', current_tenant_id())->pluck('name'),
        ]);
    }

    public function store(CreateEmployeeRequest $request, CreateEmployee $createEmployee): RedirectResponse
    {
        $createEmployee->execute(current_tenant(), $request->validated());

        return redirect()->route('employees.index')->with('status', 'Employee added.');
    }

    public function edit(EmployeeProfile $employee): View
    {
        $branches = Branch::orderBy('name')->get();

        return view('core.employees.edit', [
            'employee' => $employee,
            'branches' => $branches,
            'assignedBranchIds' => $employee->user->branches()->pluck('branches.id'),
            'roles' => Role::where('tenant_id', current_tenant_id())->pluck('name'),
            'currentRole' => $employee->user->roles()->first()?->name,
            'schedulesByBranch' => EmployeeSchedule::where('user_id', $employee->user_id)->get()->groupBy('branch_id'),
        ]);
    }

    public function update(UpdateEmployeeRequest $request, EmployeeProfile $employee, UpdateEmployee $updateEmployee): RedirectResponse
    {
        $updateEmployee->execute($employee->user, $employee, $request->validated());

        return back()->with('status', 'Employee updated.');
    }

    /**
     * Never hard-deleted — an employee may have historical commission,
     * attendance, or appointment data once later phases exist. This only
     * revokes access.
     */
    public function destroy(EmployeeProfile $employee): RedirectResponse
    {
        $employee->user->update(['is_active' => false]);

        return redirect()->route('employees.index')->with('status', 'Employee deactivated.');
    }

    public function updateSchedule(UpdateEmployeeScheduleRequest $request, EmployeeProfile $employee, UpsertEmployeeSchedule $upsertEmployeeSchedule): RedirectResponse
    {
        $this->authorize('update', $employee);

        $upsertEmployeeSchedule->execute($employee->user, $request->validated('branch_id'), $request->validated('shifts'));

        return back()->with('status', 'Schedule updated.');
    }
}
