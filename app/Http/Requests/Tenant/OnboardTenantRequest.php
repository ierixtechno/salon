<?php

namespace App\Http\Requests\Tenant;

use App\Http\Controllers\Tenant\OnboardingController;
use App\Rules\BranchCountWithinPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `timezone`/`currency` are deliberately not collected here — India is the
 * sole target market (D-003), so OnboardTenant defaults every tenant to
 * IST/INR from config('platform.default_timezone'/'default_currency')
 * rather than asking.
 *
 * `subscription_plan_id` is required: every signup picks the package it
 * wants, and a quotation for that package is generated in the same request
 * (see OnboardingController). The tenant's modules come from the package,
 * so they are not asked for separately. Only active, paid plans with at
 * least one module are offered (see OnboardingController::signupPlans).
 *
 * `billing_state` IS required here (unlike Platform's own CreateTenantRequest,
 * where Super Admin may not know it yet and can set it later) — the tenant
 * knows their own registered state at signup, and CreateQuotation can't
 * bill them at all without it. `gstin` stays optional — not every tenant
 * is GST-registered (below the threshold, etc.) — but is shown as the
 * recipient GSTIN on their Platform Billing invoices when set, so they can
 * claim input tax credit.
 */
class OnboardTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255'],
            // Exactly the packages the signup page offers — never a hidden
            // one posted directly (see OnboardingController::signupPlans).
            'subscription_plan_id' => ['required', 'integer', Rule::in(OnboardingController::signupPlans()->pluck('id')->all())],
            'branch_count' => ['nullable', 'integer', 'min:1', new BranchCountWithinPlan($this->input('subscription_plan_id'))],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'string', 'email', 'max:255'],
            'owner_password' => ['required', 'confirmed', 'string', 'min:8'],
            'billing_state' => ['required', 'string', Rule::in(config('india.states'))],
            'gstin' => ['nullable', 'string', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'subscription_plan_id.required' => 'Please choose a package.',
            'subscription_plan_id.in' => 'Please choose one of the packages shown.',
        ];
    }
}
