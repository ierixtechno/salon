<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Platform\Actions\CreateQuotation;
use App\Domain\Platform\Actions\OnboardTenant;
use App\Domain\Platform\Actions\TopUpWhatsappCredits;
use App\Domain\Platform\Actions\UpdateTenantModules;
use App\Domain\Platform\Models\Module;
use App\Domain\Platform\Models\PlatformAuditLog;
use App\Domain\Platform\Models\SubscriptionPlan;
use App\Domain\Platform\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\CreateTenantRequest;
use App\Http\Requests\Platform\TopUpWhatsappCreditsRequest;
use App\Http\Requests\Platform\UpdateTenantBillingStateRequest;
use App\Http\Requests\Platform\UpdateTenantModulesRequest;
use App\Http\Requests\Platform\UpdateTenantStatusRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class TenantController extends Controller
{
    public function index(): View
    {
        return view('platform.tenants.index', [
            'tenants' => Tenant::withCount('users')->latest()->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('platform.tenants.create', [
            'modules' => Module::orderBy('name')->get(),
            'plans' => SubscriptionPlan::where('is_active', true)->with('modules')->orderBy('price')->get(),
        ]);
    }

    /**
     * Picking a plan here skips the separate "Quotations > Create" step —
     * the quotation is generated in the same request, right after the
     * tenant. Leaving the plan blank keeps the original two-step flow
     * (manual modules now, a quotation created later).
     */
    public function store(CreateTenantRequest $request, OnboardTenant $onboardTenant, CreateQuotation $createQuotation): RedirectResponse
    {
        $data = $request->validated();
        $plan = isset($data['subscription_plan_id']) ? SubscriptionPlan::find($data['subscription_plan_id']) : null;

        // No plan chosen -> modules weren't asked for, nothing to derive.
        // Plan chosen -> the plan's own modules stand in for the manual
        // picker (PayQuotation would overwrite them to this exact set on
        // payment anyway, so asking twice adds nothing).
        if ($plan) {
            $data['modules'] = $plan->modules->pluck('code')->all();
        }

        $owner = $onboardTenant->execute($data);

        PlatformAuditLog::record(
            Auth::guard('platform')->user(),
            'tenant.created',
            'Tenant',
            $owner->tenant_id,
            $owner->tenant_id,
        );

        if (! $plan) {
            return redirect()->route('platform.tenants.show', $owner->tenant_id)
                ->with('status', 'Tenant created.');
        }

        $tenant = Tenant::findOrFail($owner->tenant_id);

        $quotation = $createQuotation->execute(
            tenant: $tenant,
            plan: $plan,
            createdBy: Auth::guard('platform')->user(),
            amountOverride: $data['quotation_amount'] ?? null,
            notes: $data['quotation_notes'] ?? null,
        );

        PlatformAuditLog::record(
            Auth::guard('platform')->user(),
            'quotation.created',
            'Quotation',
            $quotation->id,
            $tenant->id,
            ['quotation_number' => $quotation->quotation_number, 'amount' => (float) $quotation->amount],
        );

        return redirect()->route('platform.quotations.show', $quotation)
            ->with('status', 'Tenant created — quotation generated, ready to record payment.');
    }

    public function show(Tenant $tenant): View
    {
        return view('platform.tenants.show', [
            'tenant' => $tenant,
            'modules' => Module::orderBy('name')->get(),
            'enabledModuleCodes' => $tenant->tenantModules()
                ->where('enabled', true)
                ->with('module')
                ->get()
                ->pluck('module.code'),
            'subscription' => $tenant->currentSubscription(),
            'whatsappCreditBalance' => $tenant->whatsappCreditBalance(),
        ]);
    }

    public function updateStatus(UpdateTenantStatusRequest $request, Tenant $tenant): RedirectResponse
    {
        $previousStatus = $tenant->status;

        $tenant->update([
            'status' => $request->validated('status'),
            'suspended_at' => $request->validated('status') === 'suspended' ? now() : null,
        ]);

        PlatformAuditLog::record(
            Auth::guard('platform')->user(),
            'tenant.status_changed',
            'Tenant',
            $tenant->id,
            $tenant->id,
            ['from' => $previousStatus, 'to' => $tenant->status],
        );

        return back()->with('status', 'Tenant status updated.');
    }

    public function updateBillingState(UpdateTenantBillingStateRequest $request, Tenant $tenant): RedirectResponse
    {
        $previousState = $tenant->billing_state;
        $previousGstin = $tenant->gstin;

        $tenant->update([
            'billing_state' => $request->validated('billing_state'),
            'gstin' => $request->validated('gstin'),
        ]);

        PlatformAuditLog::record(
            Auth::guard('platform')->user(),
            'tenant.billing_state_changed',
            'Tenant',
            $tenant->id,
            $tenant->id,
            [
                'billing_state' => ['from' => $previousState, 'to' => $tenant->billing_state],
                'gstin' => ['from' => $previousGstin, 'to' => $tenant->gstin],
            ],
        );

        return back()->with('status', 'Billing details updated.');
    }

    public function topUpWhatsappCredits(TopUpWhatsappCreditsRequest $request, Tenant $tenant, TopUpWhatsappCredits $topUp): RedirectResponse
    {
        $amount = $request->validated('amount');

        $topUp->execute($tenant, $amount, Auth::guard('platform')->user(), $request->validated('reason'));

        PlatformAuditLog::record(
            Auth::guard('platform')->user(),
            'tenant.whatsapp_credits_topped_up',
            'Tenant',
            $tenant->id,
            $tenant->id,
            ['amount' => $amount, 'reason' => $request->validated('reason')],
        );

        return back()->with('status', "Added {$amount} WhatsApp credits.");
    }

    public function updateModules(UpdateTenantModulesRequest $request, Tenant $tenant, UpdateTenantModules $updateTenantModules): RedirectResponse
    {
        $updateTenantModules->execute($tenant, $request->validated('modules', []));

        PlatformAuditLog::record(
            Auth::guard('platform')->user(),
            'tenant.modules_changed',
            'Tenant',
            $tenant->id,
            $tenant->id,
            ['modules' => $request->validated('modules', [])],
        );

        return back()->with('status', 'Modules updated.');
    }
}
