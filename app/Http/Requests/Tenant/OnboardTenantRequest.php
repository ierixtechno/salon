<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `timezone`/`currency` are deliberately not collected here — India is the
 * sole target market (D-003), so OnboardTenant defaults every tenant to
 * IST/INR from config('platform.default_timezone'/'default_currency')
 * rather than asking.
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
            'modules' => ['required', 'array', 'min:1'],
            'modules.*' => ['string', 'exists:modules,code'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'string', 'email', 'max:255'],
            'owner_password' => ['required', 'confirmed', 'string', 'min:8'],
            'billing_state' => ['required', 'string', Rule::in(config('india.states'))],
            'gstin' => ['nullable', 'string', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/'],
        ];
    }
}
