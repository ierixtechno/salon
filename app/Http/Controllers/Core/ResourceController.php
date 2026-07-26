<?php

namespace App\Http\Controllers\Core;

use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Resource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\StoreResourceRequest;
use App\Http\Requests\Core\UpdateResourceRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ResourceController extends Controller
{
    public function index(Branch $branch): View
    {
        $this->authorize('view', $branch);
        $this->authorize('viewAny', Resource::class);

        return view('core.resources.index', [
            'branch' => $branch,
            'resources' => $branch->resources()->orderBy('type')->orderBy('name')->get(),
        ]);
    }

    public function create(Branch $branch): View
    {
        $this->authorize('view', $branch);
        $this->authorize('create', Resource::class);

        return view('core.resources.create', ['branch' => $branch, 'types' => Resource::TYPES]);
    }

    public function store(StoreResourceRequest $request, Branch $branch): RedirectResponse
    {
        $this->authorize('view', $branch);

        $branch->resources()->create($request->validated());

        return redirect()->route('branches.resources.index', $branch)->with('status', 'Resource added.');
    }

    public function edit(Resource $resource): View
    {
        $this->authorize('update', $resource);

        return view('core.resources.edit', ['resource' => $resource, 'types' => Resource::TYPES]);
    }

    public function update(UpdateResourceRequest $request, Resource $resource): RedirectResponse
    {
        $this->authorize('update', $resource);

        $resource->update($request->validated());

        return redirect()->route('branches.resources.index', $resource->branch_id)->with('status', 'Resource updated.');
    }

    public function destroy(Resource $resource): RedirectResponse
    {
        $this->authorize('delete', $resource);

        $branchId = $resource->branch_id;
        $resource->delete();

        return redirect()->route('branches.resources.index', $branchId)->with('status', 'Resource removed.');
    }
}
