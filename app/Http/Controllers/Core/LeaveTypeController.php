<?php

namespace App\Http\Controllers\Core;

use App\Domain\Core\Models\LeaveType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\StoreLeaveTypeRequest;
use App\Http\Requests\Core\UpdateLeaveTypeRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class LeaveTypeController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:leave-types.manage');
    }

    public function index(): View
    {
        return view('core.leave-types.index', [
            'leaveTypes' => LeaveType::orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('core.leave-types.create');
    }

    public function store(StoreLeaveTypeRequest $request): RedirectResponse
    {
        LeaveType::create($request->validated());

        return redirect()->route('leave-types.index')->with('status', 'Leave type created.');
    }

    public function edit(LeaveType $leaveType): View
    {
        return view('core.leave-types.edit', ['leaveType' => $leaveType]);
    }

    public function update(UpdateLeaveTypeRequest $request, LeaveType $leaveType): RedirectResponse
    {
        $leaveType->update($request->validated());

        return redirect()->route('leave-types.index')->with('status', 'Leave type updated.');
    }

    public function destroy(LeaveType $leaveType): RedirectResponse
    {
        $leaveType->update(['is_active' => false]);

        return redirect()->route('leave-types.index')->with('status', 'Leave type deactivated.');
    }
}
