<?php

namespace App\Http\Controllers\Core;

use App\Domain\Core\Actions\UpdateServiceBranches;
use App\Domain\Core\Actions\UpdateServiceStaff;
use App\Domain\Core\Actions\UpdateServiceVariants;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\EmployeeProfile;
use App\Domain\Core\Models\Service;
use App\Domain\Core\Models\ServiceCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\StoreServiceRequest;
use App\Http\Requests\Core\UpdateServiceBranchesRequest;
use App\Http\Requests\Core\UpdateServiceRequest;
use App\Http\Requests\Core\UpdateServiceStaffRequest;
use App\Http\Requests\Core\UpdateServiceVariantsRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ServiceController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Service::class, 'service');
    }

    public function index(): View
    {
        return view('core.services.index', [
            'services' => Service::with('category', 'module')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('core.services.create', [
            'categories' => ServiceCategory::where('is_active', true)->with('module')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreServiceRequest $request): RedirectResponse
    {
        $category = ServiceCategory::findOrFail($request->validated('service_category_id'));

        $service = Service::create([
            ...$request->validated(),
            'module_id' => $category->module_id,
        ]);

        return redirect()->route('services.edit', $service)->with('status', 'Service created.');
    }

    public function edit(Service $service): View
    {
        return view('core.services.edit', [
            'service' => $service,
            'categories' => ServiceCategory::where('is_active', true)->where('module_id', $service->module_id)->orderBy('name')->get(),
            'branches' => Branch::where('is_active', true)->orderBy('name')->get(),
            'branchPivots' => $service->branches()->get()->keyBy('id'),
            'employees' => EmployeeProfile::with('user')->get(),
            'capableEmployeeIds' => $service->capableEmployees()->pluck('users.id'),
        ]);
    }

    public function update(UpdateServiceRequest $request, Service $service): RedirectResponse
    {
        $category = ServiceCategory::findOrFail($request->validated('service_category_id'));

        $service->update([
            ...$request->validated(),
            'module_id' => $category->module_id,
        ]);

        return back()->with('status', 'Service updated.');
    }

    public function destroy(Service $service): RedirectResponse
    {
        $service->update(['is_active' => false]);

        return redirect()->route('services.index')->with('status', 'Service deactivated.');
    }

    public function updateVariants(UpdateServiceVariantsRequest $request, Service $service, UpdateServiceVariants $action): RedirectResponse
    {
        $action->execute($service, $request->validated('variants', []));

        return back()->with('status', 'Variants updated.');
    }

    public function updateBranches(UpdateServiceBranchesRequest $request, Service $service, UpdateServiceBranches $action): RedirectResponse
    {
        $action->execute($service, $request->validated('branches', []));

        return back()->with('status', 'Branch availability updated.');
    }

    public function updateStaff(UpdateServiceStaffRequest $request, Service $service, UpdateServiceStaff $action): RedirectResponse
    {
        $action->execute($service, $request->validated('user_ids', []));

        return back()->with('status', 'Staff capability updated.');
    }
}
