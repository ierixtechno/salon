<?php

namespace App\Http\Controllers\Core;

use App\Domain\Core\Actions\UpdatePackageServices;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Customer;
use App\Domain\Core\Models\Package;
use App\Domain\Core\Models\Service;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\StorePackageRequest;
use App\Http\Requests\Core\UpdatePackageRequest;
use App\Http\Requests\Core\UpdatePackageServicesRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class PackageController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Package::class, 'package');
    }

    public function index(): View
    {
        return view('core.packages.index', [
            'packages' => Package::withCount('customerPackages')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('core.packages.create', [
            'services' => Service::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StorePackageRequest $request): RedirectResponse
    {
        Package::create($request->validated());

        return redirect()->route('packages.index')->with('status', 'Package created.');
    }

    public function edit(Package $package): View
    {
        return view('core.packages.edit', [
            'package' => $package,
            'services' => Service::where('is_active', true)->orderBy('name')->get(),
            'items' => $package->services()->get(),
        ]);
    }

    public function update(UpdatePackageRequest $request, Package $package): RedirectResponse
    {
        $package->update($request->validated());

        return redirect()->route('packages.index')->with('status', 'Package updated.');
    }

    public function destroy(Package $package): RedirectResponse
    {
        $package->update(['is_active' => false]);

        return redirect()->route('packages.index')->with('status', 'Package deactivated.');
    }

    public function updateServices(UpdatePackageServicesRequest $request, Package $package, UpdatePackageServices $action): RedirectResponse
    {
        $action->execute($package, $request->validated('items'));

        return back()->with('status', 'Package contents updated.');
    }

    /**
     * Pick a customer and sell this package; the sale form posts to the
     * customer's own store route, which bills it on an invoice.
     */
    public function sell(Package $package): View
    {
        abort_unless(Auth::guard('web')->user()->can('packages.sell'), 403);

        $user = Auth::guard('web')->user();

        return view('core.sell.create', [
            'type' => 'package',
            'item' => $package,
            'customers' => Customer::where('is_active', true)->orderBy('name')->get(),
            'branches' => Branch::where('is_active', true)->orderBy('name')->get()->filter(fn (Branch $branch) => $user->canAccessBranch($branch))->values(),
        ]);
    }
}
