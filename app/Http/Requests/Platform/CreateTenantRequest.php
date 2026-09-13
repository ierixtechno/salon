<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `timezone`/`currency` are deliberately not collected here — India is the
 * sole target market (D-003), so OnboardTenant defaults every tenant to
 * IST/INR from config('platform.default_timezone'/'default_currency')
 * rather than asking.
 *
 * `subscription_plan_id` is optional: Super Admin can create a tenant
 * without committing to a plan yet (modules picked manually, quotation
 * created later from Quotations), or pick a plan here to skip the separate
 * "create a quotation" step entirely — see TenantController::store(). When
 * a plan is chosen, `modules` is no longer asked (the plan's own modules
 * are used, matching what PayQuotation syncs to on payment anyway) and
 * `billing_state` becomes required, since CreateQuotation cannot compute
 * GST without it.
 */
class CreateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $planChosen = $this->filled('subscription_plan_id');

        return [
            'business_name' => ['required', 'string', 'max:255'],
            'subscription_plan_id' => ['nullable', 'integer', 'exists:subscription_plans,id'],
            'modules' => [Rule::requiredIf(! $planChosen), 'array'],
            'modules.*' => ['string', 'exists:modules,code'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'string', 'email', 'max:255'],
            'owner_password' => ['required', 'confirmed', 'string', 'min:8'],
            'billing_state' => [Rule::requiredIf($planChosen), 'nullable', 'string', Rule::in(config('india.states'))],
            'gstin' => ['nullable', 'string', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/'],
            'quotation_amount' => ['nullable', 'numeric', 'min:0'],
            'quotation_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'billing_state.required' => 'Set the billing state so a quotation can be generated for this plan.',
        ];
    }
}
