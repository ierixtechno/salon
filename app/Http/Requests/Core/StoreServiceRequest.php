<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('services.create');
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'service_category_id' => [
                'required',
                Rule::exists('service_categories', 'id')->where('tenant_id', $tenantId),
            ],
            'name' => ['required', 'string', 'max:255', Rule::unique('services', 'name')->where('tenant_id', $tenantId)],
            'description' => ['nullable', 'string', 'max:2000'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'buffer_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
            'base_price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'tax_rate_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'sac_code' => ['nullable', 'string', 'max:20'],
        ];
    }
}
