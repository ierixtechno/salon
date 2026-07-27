<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Platform\Models\Feature;
use App\Domain\Platform\Models\PlatformAuditLog;
use App\Domain\Platform\Models\SubscriptionPlan;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\UpdateSubscriptionPlanRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class SubscriptionPlanController extends Controller
{
    public function index(): View
    {
        return view('platform.subscription-plans.index', [
            'plans' => SubscriptionPlan::withCount('features')
                ->withCount('tenantSubscriptions')
                ->orderBy('price')
                ->get(),
        ]);
    }

    public function edit(SubscriptionPlan $subscriptionPlan): View
    {
        return view('platform.subscription-plans.edit', [
            'plan' => $subscriptionPlan,
            'features' => Feature::orderBy('name')->get(),
            'selectedFeatureCodes' => $subscriptionPlan->features()->pluck('code')->all(),
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
