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
use Illuminate\Support\Facades\DB;

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
            'plans' => SubscriptionPlan::where('is_active', true)->with('modules')->orderBy('price')->get(),
        ]);
    }

    /**
     * Creates the tenant and the quotation for its package together, in one
     * transaction — a tenant is never left with nothing to pay. Same rule as
     * self-signup (OnboardingController): the package is required and its
     * modules become the tenant's modules. The new owner can log in straight
     * away but sees only this quotation until it is paid or recorded.
     */
    public function store(CreateTenantRequest $request, OnboardTenant $onboardTenant, CreateQuotation $createQuotation): RedirectResponse
    {
        $data = $request->validated();
        $plan = SubscriptionPlan::with('modules')->findOrFail($data['subscription_plan_id']);
        $data['modules'] = $plan->modules->pluck('code')->all();
        $admin = Auth::guard('platform')->user();

        $quotation = DB::transaction(function () use ($data, $plan, $admin, $onboardTenant, $createQuotation) {
            $owner = $onboardTenant->execute($data);

            PlatformAuditLog::record($admin, 'tenant.created', 'Tenant', $owner->tenant_id, $owner->tenant_id);

            $quotation = $createQuotation->execute(
                tenant: Tenant::findOrFail($owner->tenant_id),
                plan: $plan,
                createdBy: $admin,
                amountOverride: $data['quotation_amount'] ?? null,
                notes: $data['quotation_notes'] ?? null,
                branchCount: $data['branch_count'] ?? null,
            );

            PlatformAuditLog::record(
                $admin,
                'quotation.created',
                'Quotation',
                $quotation->id,
                $owner->tenant_id,
                ['quotation_number' => $quotation->quotation_number, 'amount' => (float) $quotation->amount],
            );

            return $quotation;
        });

        return redirect()->route('platform.quotations.show', $quotation)
            ->with('status', 'Tenant created and quotation emailed to the owner. Record the payment here once it is received.');
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
