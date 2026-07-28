<?php

namespace App\Http\Controllers\Core;

use App\Domain\Core\Models\ServiceCategory;
use App\Domain\Platform\Models\Module;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\StoreServiceCategoryRequest;
use App\Http\Requests\Core\UpdateServiceCategoryRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServiceCategoryController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(ServiceCategory::class, 'service_category');
    }

    /**
     * `?module=<code>` is a display-only filter, same reasoning as
     * ServiceController::index().
     */
    public function index(Request $request): View
    {
        $moduleFilter = $request->filled('module')
            ? Module::where('code', $request->string('module'))->first()
            : null;

        $query = ServiceCategory::with('module')->withCount('services')->orderBy('sort_order')->orderBy('name');
        if ($moduleFilter) {
            $query->where('module_id', $moduleFilter->id);
        }

        return view('core.service-categories.index', [
            'categories' => $query->get(),
            'moduleFilter' => $moduleFilter,
        ]);
    }

    public function create(): View
    {
        return view('core.service-categories.create', [
            'tenantModules' => current_tenant()->tenantModules()->where('enabled', true)->with('module')->get(),
        ]);
    }

    public function store(StoreServiceCategoryRequest $request): RedirectResponse
    {
        ServiceCategory::create($request->validated());

        return redirect()->route('service-categories.index')->with('status', 'Category created.');
    }

    public function edit(ServiceCategory $serviceCategory): View
    {
        return view('core.service-categories.edit', ['category' => $serviceCategory]);
    }

    public function update(UpdateServiceCategoryRequest $request, ServiceCategory $serviceCategory): RedirectResponse
    {
        $serviceCategory->update($request->validated());

        return redirect()->route('service-categories.index')->with('status', 'Category updated.');
    }

    public function destroy(ServiceCategory $serviceCategory): RedirectResponse
    {
        $serviceCategory->update(['is_active' => false]);

        return redirect()->route('service-categories.index')->with('status', 'Category deactivated.');
    }
}
