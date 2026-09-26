<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Platform\Actions\CreateQuotation;
use App\Domain\Platform\Actions\NotifyPlatformAdmins;
use App\Domain\Platform\Actions\OnboardTenant;
use App\Domain\Platform\Models\Quotation;
use App\Domain\Platform\Models\SubscriptionPlan;
use App\Domain\Platform\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\OnboardTenantRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class OnboardingController extends Controller
{
    public function create(): View
    {
        return view('tenant.onboarding', [
            'plans' => self::signupPlans(),
        ]);
    }

    /**
     * Creates the tenant AND the quotation for the package they picked, in
     * one transaction — a signup never ends up as a tenant with nothing to
     * pay (CLAUDE.md §23). The tenant's modules are the package's modules.
     *
     * Deliberately does NOT log the new owner in; they land on the login
     * page. When they log in they are taken straight to their quotation and
     * can see nothing else — no side menu, no other page — until it is
     * paid online or Super Admin records the payment
     * (EnforceSubscriptionAccess / AccountAccessController).
     */
    public function store(OnboardTenantRequest $request, OnboardTenant $onboardTenant, CreateQuotation $createQuotation): RedirectResponse
    {
        $data = $request->validated();
        $plan = SubscriptionPlan::with('modules')->findOrFail($data['subscription_plan_id']);
        $data['modules'] = $plan->modules->pluck('code')->all();

        [$owner, $quotation] = DB::transaction(function () use ($data, $plan, $onboardTenant, $createQuotation) {
            $owner = $onboardTenant->execute($data);

            // Emails the quotation (with how to pay) to the new owner.
            $quotation = $createQuotation->execute(
                tenant: Tenant::findOrFail($owner->tenant_id),
                plan: $plan,
                createdBy: null,
            );

            return [$owner, $quotation];
        });

        $this->alertSuperAdmins($owner, $quotation, $data);

        return redirect()->route('login')->with(
            'status',
            "Your account has been created and quotation {$quotation->quotation_number} has been emailed to you. "
                .'Log in to view it and pay — your account unlocks as soon as the payment is received.',
        );
    }

    /**
     * Packages a new business can pick for itself — the single source of
     * truth for both the signup page and its validation: active, paid, and
     * with at least one module. A zero-priced plan can't be paid online, and
     * a plan with no modules would leave the tenant with an empty app after
     * paying, so both stay Super Admin's to grant (Platform > Tenants > New
     * tenant lists every active plan).
     */
    public static function signupPlans()
    {
        return SubscriptionPlan::where('is_active', true)
            ->where('price', '>', 0)
            ->whereHas('modules')
            ->with('modules')
            ->orderBy('price')
            ->orderBy('name')
            ->get();
    }

    /**
     * The account already exists by this point — a mail/queue hiccup must
     * never turn a successful signup into an error page (CLAUDE.md §37),
     * so this is isolated and only reported.
     */
    private function alertSuperAdmins(User $owner, Quotation $quotation, array $data): void
    {
        try {
            app(NotifyPlatformAdmins::class)->execute(
                subject: "New signup: {$data['business_name']}",
                body: "A new business has registered and has been sent a quotation.\n\n"
                    ."Business: {$data['business_name']}\n"
                    ."Owner: {$owner->name} <{$owner->email}>\n"
                    ."Package: {$quotation->plan->name}\n"
                    ."Quotation: {$quotation->quotation_number} (₹".number_format((float) $quotation->total_amount, 2).")\n"
                    .'Billing state: '.($data['billing_state'] ?? 'not given')."\n"
                    .'GSTIN: '.($data['gstin'] ?? 'not given')."\n\n"
                    ."When they pay outside the app, record it here:\n".route('platform.quotations.show', $quotation),
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
