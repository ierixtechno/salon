<?php

namespace App\Http\Controllers\Core;

use App\Domain\Core\Actions\UpdateMembershipPlanApplicability;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\MembershipPlan;
use App\Domain\Core\Models\Service;
use App\Domain\Platform\Models\Module;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\StoreMembershipPlanRequest;
use App\Http\Requests\Core\UpdateMembershipPlanApplicabilityRequest;
use App\Http\Requests\Core\UpdateMembershipPlanRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class MembershipPlanController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(MembershipPlan::class, 'membership_plan');
    }

    public function index(): View
    {
        return view('core.membership-plans.index', [
            'plans' => MembershipPlan::withCount('customerMemberships')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('core.membership-plans.create');
    }

    public function store(StoreMembershipPlanRequest $request): RedirectResponse
    {
        MembershipPlan::create($request->validated());

        return redirect()->route('membership-plans.index')->with('status', 'Membership plan created.');
    }

    public function edit(MembershipPlan $membershipPlan): View
    {
        return view('core.membership-plans.edit', [
            'plan' => $membershipPlan,
            'modules' => Module::orderBy('name')->get(),
            'branches' => Branch::where('is_active', true)->orderBy('name')->get(),
            'services' => Service::where('is_active', true)->orderBy('name')->get(),
            'selectedModuleIds' => $membershipPlan->modules()->pluck('modules.id')->all(),
            'selectedBranchIds' => $membershipPlan->branches()->pluck('branches.id')->all(),
            'selectedServiceIds' => $membershipPlan->services()->pluck('services.id')->all(),
        ]);
    }

    public function update(UpdateMembershipPlanRequest $request, MembershipPlan $membershipPlan): RedirectResponse
    {
        $membershipPlan->update($request->validated());

        return redirect()->route('membership-plans.index')->with('status', 'Membership plan updated.');
    }

    public function destroy(MembershipPlan $membershipPlan): RedirectResponse
    {
        $membershipPlan->update(['is_active' => false]);

        return redirect()->route('membership-plans.index')->with('status', 'Membership plan deactivated.');
    }

    public function updateApplicability(UpdateMembershipPlanApplicabilityRequest $request, MembershipPlan $membershipPlan, UpdateMembershipPlanApplicability $action): RedirectResponse
    {
        $action->execute(
            $membershipPlan,
            $request->validated('module_ids'),
            $request->validated('branch_ids'),
            $request->validated('service_ids'),
        );

        return back()->with('status', 'Applicability updated.');
    }
}
