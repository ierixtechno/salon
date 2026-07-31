<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `timezone`/`currency` are deliberately not collected here — India is the
 * sole target market (D-003), so OnboardTenant defaults every tenant to
 * IST/INR from config('platform.default_timezone'/'default_currency')
 * rather than asking.
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
            'modules' => ['required', 'array', 'min:1'],
            'modules.*' => ['string', 'exists:modules,code'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'string', 'email', 'max:255'],
            'owner_password' => ['required', 'confirmed', 'string', 'min:8'],
            // Optional here — CreateQuotation requires it be set before the
            // tenant can actually be billed, but Super Admin may not know it
            // yet at signup time and can fill it in later from the tenant
            // detail page.
            'billing_state' => ['nullable', 'string', Rule::in(config('india.states'))],
            'gstin' => ['nullable', 'string', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/'],
        ];
    }
}
