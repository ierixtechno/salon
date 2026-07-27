<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

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
        ];
    }
}
