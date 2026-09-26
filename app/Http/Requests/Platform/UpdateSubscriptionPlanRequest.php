<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubscriptionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'billing_interval' => ['required', Rule::in(['trial', 'monthly', 'yearly'])],
            'branch_limit' => ['required', 'integer', 'min:1', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'features' => ['present', 'array'],
            'features.*' => ['string', 'exists:features,code'],
            'modules' => ['present', 'array'],
            'modules.*' => ['string', 'exists:modules,code'],
        ];
    }
}
