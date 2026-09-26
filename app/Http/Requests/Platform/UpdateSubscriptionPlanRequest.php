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

    protected function prepareForValidation(): void
    {
        // A blank price box means "not offered" (0), not a missing value.
        if (blank($this->input('additional_branch_price'))) {
            $this->merge(['additional_branch_price' => 0]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'billing_interval' => ['required', Rule::in(['trial', 'monthly', 'yearly'])],
            'compare_at_price' => ['nullable', 'numeric', 'min:0', 'max:999999.99', 'gt:price'],
            'branch_limit' => ['required', 'integer', 'min:1', 'max:1000'],
            'additional_branch_price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'max_branches' => ['nullable', 'integer', 'min:1', 'max:1000', 'gte:branch_limit'],
            'is_active' => ['nullable', 'boolean'],
            'features' => ['present', 'array'],
            'features.*' => ['string', 'exists:features,code'],
            'modules' => ['present', 'array'],
            'modules.*' => ['string', 'exists:modules,code'],
        ];
    }
}
