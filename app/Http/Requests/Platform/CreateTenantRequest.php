<?php

namespace App\Http\Requests\Platform;

use App\Rules\BranchCountWithinPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `timezone`/`currency` are deliberately not collected here — India is the
 * sole target market (D-003), so OnboardTenant defaults every tenant to
 * IST/INR from config('platform.default_timezone'/'default_currency')
 * rather than asking.
 *
 * `subscription_plan_id` is required: however a tenant is registered (here
 * by Super Admin, or by self-signup), it picks a package and a quotation
 * for that package is generated in the same request — see
 * TenantController::store(). The tenant's modules come from the package,
 * so they are not asked for separately, and `billing_state` is required
 * because CreateQuotation cannot compute GST without it. Any active plan
 * may be chosen here, including a zero-priced one Super Admin grants
 * deliberately (self-signup only offers paid plans).
 */
class CreateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255'],
            'branch_count' => ['nullable', 'integer', 'min:1', new BranchCountWithinPlan($this->input('subscription_plan_id'))],
            'subscription_plan_id' => ['required', 'integer', Rule::exists('subscription_plans', 'id')->where('is_active', true)],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'string', 'email', 'max:255'],
            'owner_password' => ['required', 'confirmed', 'string', 'min:8'],
            'billing_state' => ['required', 'string', Rule::in(config('india.states'))],
            'gstin' => ['nullable', 'string', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/'],
            'quotation_amount' => ['nullable', 'numeric', 'min:0'],
            'quotation_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'subscription_plan_id.required' => 'Choose the package this tenant is buying.',
            'billing_state.required' => 'Set the billing state so the quotation\'s GST can be calculated.',
        ];
    }
}
