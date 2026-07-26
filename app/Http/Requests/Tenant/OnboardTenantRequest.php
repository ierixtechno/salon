<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

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
            'timezone' => ['required', 'string', 'timezone'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'modules' => ['required', 'array', 'min:1'],
            'modules.*' => ['string', 'exists:modules,code'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'string', 'email', 'max:255'],
            'owner_password' => ['required', 'confirmed', 'string', 'min:8'],
        ];
    }
}
