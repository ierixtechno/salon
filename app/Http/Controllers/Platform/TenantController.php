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
use App\Domain\Platform\Actions\GrantBranches;
use App\Domain\Platform\Actions\RequestExtraBranches;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TenantController extends Controller
{
    public function index(): View
    {
        $tenants = Tenant::withCount('users')
            ->with([
                'subscriptions.plan',
                'quotations' => fn ($q) => $q->where('status', 'pending')->latest()->with('plan'),
            ])
            ->latest()
            ->paginate(20);

        // The owner is the tenant's first user — one query for the whole page, not one per row.
        $owners = User::withoutGlobalScopes()
            ->whereIn('tenant_id', $tenants->pluck('id'))
            ->orderBy('id')
            ->get()
            ->groupBy('tenant_id')
            ->map->first();

        return view('platform.tenants.index', ['tenants' => $tenants, 'owners' => $owners]);
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
                discountPercent: isset($data['discount_percent']) ? (float) $data['discount_percent'] : null,
            );

            PlatformAuditLog::record(
                $admin,
                'quotation.created',
                'Quotation',
                $quotation->id,
                $owner->tenant_id,
                ['quotation_number' => $quotation->quotation_number, 'amount' => (float) $quotation->amount, 'discount_percent' => (float) $quotation->discount_percent],
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

    /**
     * Super Admin adds branches to a tenant's subscription: either a pro-rata
     * quotation the tenant pays (mode=quote) or a free grant (mode=grant).
     * Their user limit rises with the branches (SubscriptionPlan::userLimitFor).
     */
    public function addBranches(Request $request, Tenant $tenant, RequestExtraBranches $requestExtra, GrantBranches $grant): RedirectResponse
    {
        $data = $request->validate([
            'additional' => ['required', 'integer', 'min:1', 'max:50'],
            'mode' => ['required', Rule::in(['quote', 'grant'])],
        ]);
        $admin = Auth::guard('platform')->user();

        if ($data['mode'] === 'grant') {
            $total = $grant->execute($tenant, (int) $data['additional']);

            PlatformAuditLog::record($admin, 'tenant.branches_granted', 'Tenant', $tenant->id, $tenant->id, ['added' => (int) $data['additional'], 'total_branches' => $total]);

            return back()->with('status', "{$data['additional']} branch(es) added free of charge — {$tenant->name} now has {$total}.");
        }

        $quotation = $requestExtra->execute($tenant, (int) $data['additional']);

        PlatformAuditLog::record($admin, 'quotation.created', 'Quotation', $quotation->id, $tenant->id, ['quotation_number' => $quotation->quotation_number, 'amount' => (float) $quotation->amount, 'reason' => 'additional_branches']);

        return redirect()->route('platform.quotations.show', $quotation)->with('status', 'Pro-rata quotation created for the additional branches. Record the payment here once received.');
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
        ] + ($request->exists('phone') ? ['phone' => $request->validated('phone')] : []));

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
