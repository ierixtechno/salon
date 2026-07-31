<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Platform\Models\Feature;
use App\Domain\Platform\Models\Module;
use App\Domain\Platform\Models\PlatformAuditLog;
use App\Domain\Platform\Models\SubscriptionPlan;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StoreSubscriptionPlanRequest;
use App\Http\Requests\Platform\UpdateSubscriptionPlanRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class SubscriptionPlanController extends Controller
{
    public function index(): View
    {
        return view('platform.subscription-plans.index', [
            'plans' => SubscriptionPlan::with(['features', 'modules'])
                ->withCount('tenantSubscriptions')
                ->orderBy('price')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('platform.subscription-plans.create', [
            'features' => Feature::orderBy('name')->get(),
            'modules' => Module::orderBy('name')->get(),
        ]);
    }

    public function store(StoreSubscriptionPlanRequest $request): RedirectResponse
    {
        $plan = SubscriptionPlan::create([
            'code' => $request->validated('code'),
            'name' => $request->validated('name'),
            'price' => $request->validated('price'),
            'billing_interval' => $request->validated('billing_interval'),
            'is_active' => $request->boolean('is_active'),
        ]);

        $plan->features()->sync(Feature::whereIn('code', $request->validated('features'))->pluck('id'));
        $plan->modules()->sync(Module::whereIn('code', $request->validated('modules'))->pluck('id'));

        PlatformAuditLog::record(
            Auth::guard('platform')->user(),
            'subscription_plan.created',
            'SubscriptionPlan',
            $plan->id,
            null,
            ['name' => $plan->name, 'price' => (float) $plan->price],
        );

        return redirect()->route('platform.subscription-plans.index')->with('status', 'Subscription plan created.');
    }

    public function edit(SubscriptionPlan $subscriptionPlan): View
    {
        return view('platform.subscription-plans.edit', [
            'plan' => $subscriptionPlan,
            'features' => Feature::orderBy('name')->get(),
            'selectedFeatureCodes' => $subscriptionPlan->features()->pluck('code')->all(),
            'modules' => Module::orderBy('name')->get(),
            'selectedModuleCodes' => $subscriptionPlan->modules()->pluck('code')->all(),
        ]);
    }

    public function update(UpdateSubscriptionPlanRequest $request, SubscriptionPlan $subscriptionPlan): RedirectResponse
    {
        $subscriptionPlan->update([
            'name' => $request->validated('name'),
            'price' => $request->validated('price'),
            'billing_interval' => $request->validated('billing_interval'),
            'is_active' => $request->boolean('is_active'),
        ]);

        $featureIds = Feature::whereIn('code', $request->validated('features'))->pluck('id');
        $subscriptionPlan->features()->sync($featureIds);

        $moduleIds = Module::whereIn('code', $request->validated('modules'))->pluck('id');
        $subscriptionPlan->modules()->sync($moduleIds);

        PlatformAuditLog::record(
            Auth::guard('platform')->user(),
            'subscription_plan.updated',
            'SubscriptionPlan',
            $subscriptionPlan->id,
            null,
            ['name' => $subscriptionPlan->name, 'price' => (float) $subscriptionPlan->price, 'is_active' => $subscriptionPlan->is_active],
        );

        return redirect()->route('platform.subscription-plans.index')->with('status', 'Subscription plan updated.');
    }
}
