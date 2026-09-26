<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMembershipPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('membership_plan'));
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('membership_plans', 'name')->where('tenant_id', $this->user()->tenant_id)->ignore($this->route('membership_plan')),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'validity_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'tax_rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'discount_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
