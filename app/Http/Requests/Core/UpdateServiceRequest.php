<?php

namespace App\Http\Requests\Core;

use App\Domain\Core\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('services.update');
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;
        /** @var Service $service */
        $service = $this->route('service');

        return [
            'service_category_id' => [
                'required',
                Rule::exists('service_categories', 'id')->where('tenant_id', $tenantId),
            ],
            'name' => ['required', 'string', 'max:255', Rule::unique('services', 'name')->where('tenant_id', $tenantId)->ignore($service->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'base_price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'tax_rate_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
