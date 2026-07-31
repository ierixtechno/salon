<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubscriptionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:100', 'alpha_dash', 'unique:subscription_plans,code'],
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'billing_interval' => ['required', Rule::in(['trial', 'monthly', 'yearly'])],
            'is_active' => ['nullable', 'boolean'],
            'features' => ['present', 'array'],
            'features.*' => ['string', 'exists:features,code'],
            'modules' => ['present', 'array'],
            'modules.*' => ['string', 'exists:modules,code'],
        ];
    }
}
