<?php

namespace App\Http\Controllers\Core;

use App\Domain\Core\Actions\CancelCustomerPackage;
use App\Domain\Core\Actions\SellPackageToCustomer;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Customer;
use App\Domain\Core\Models\CustomerPackage;
use App\Domain\Core\Models\Package;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\StoreCustomerPackageRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class CustomerPackageController extends Controller
{
    public function index(Customer $customer): View
    {
        abort_unless(Auth::guard('web')->user()->can('packages.view'), 403);

        return view('core.customer-packages.index', [
            'customer' => $customer,
            'customerPackages' => $customer->customerPackages()->with(['package', 'items.service', 'branch'])->latest('purchased_at')->get(),
            'packages' => Package::where('is_active', true)->orderBy('name')->get(),
            'branches' => $this->accessibleBranches(),
        ]);
    }

    public function store(StoreCustomerPackageRequest $request, Customer $customer, SellPackageToCustomer $action): RedirectResponse
    {
        $action->execute(
            branch: Branch::findOrFail($request->validated('branch_id')),
            customer: $customer,
            package: Package::findOrFail($request->validated('package_id')),
            pricePaid: (float) $request->validated('price_paid'),
            purchaseMethod: $request->validated('purchase_method'),
            purchaseReference: $request->validated('purchase_reference'),
            createdBy: Auth::guard('web')->id(),
        );

        return back()->with('status', 'Package sold.');
    }

    public function cancel(Customer $customer, CustomerPackage $customerPackage, CancelCustomerPackage $action): RedirectResponse
    {
        abort_unless(Auth::guard('web')->user()->can('packages.sell'), 403);
        abort_unless($customerPackage->customer_id === $customer->id, 404);

        $action->execute($customerPackage, request('reason'));

        return back()->with('status', 'Package cancelled.');
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
