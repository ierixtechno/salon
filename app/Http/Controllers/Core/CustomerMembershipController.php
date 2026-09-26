<?php

namespace App\Http\Controllers\Core;

use App\Domain\Core\Actions\CancelCustomerMembership;
use App\Domain\Core\Actions\SellMembershipToCustomer;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Customer;
use App\Domain\Core\Models\CustomerMembership;
use App\Domain\Core\Models\MembershipPlan;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\StoreCustomerMembershipRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class CustomerMembershipController extends Controller
{
    public function index(Customer $customer): View
    {
        abort_unless(Auth::guard('web')->user()->can('memberships.view'), 403);

        return view('core.customer-memberships.index', [
            'customer' => $customer,
            'customerMemberships' => $customer->customerMemberships()->with(['membershipPlan', 'branch'])->latest('starts_at')->get(),
            'plans' => MembershipPlan::where('is_active', true)->orderBy('name')->get(),
            'branches' => $this->accessibleBranches(),
        ]);
    }

    public function store(StoreCustomerMembershipRequest $request, Customer $customer, SellMembershipToCustomer $action): RedirectResponse
    {
        $sold = $action->execute(
            branch: Branch::findOrFail($request->validated('branch_id')),
            customer: $customer,
            plan: MembershipPlan::findOrFail($request->validated('membership_plan_id')),
            price: (float) $request->validated('price_paid'),
            purchaseMethod: $request->validated('purchase_method'),
            purchaseReference: $request->validated('purchase_reference'),
            createdBy: Auth::guard('web')->id(),
        );

        return $sold->invoice_id
            ? redirect()->route('invoices.show', $sold->invoice_id)->with('status', 'Membership sold and invoice generated.')
            : back()->with('status', 'Membership sold.');
    }

    public function cancel(Customer $customer, CustomerMembership $customerMembership, CancelCustomerMembership $action): RedirectResponse
    {
        abort_unless(Auth::guard('web')->user()->can('memberships.sell'), 403);
        abort_unless($customerMembership->customer_id === $customer->id, 404);

        $action->execute($customerMembership, request('reason'));

        return back()->with('status', 'Membership cancelled.');
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
