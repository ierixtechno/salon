<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('services.create');
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            // Only modules actually enabled for this tenant — never a
            // module the tenant hasn't been granted (CLAUDE.md §7).
            'module_id' => [
                'required',
                Rule::exists('tenant_modules', 'module_id')->where('tenant_id', $tenantId)->where('enabled', true),
            ],
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('service_categories', 'name')->where('tenant_id', $tenantId)->where('module_id', $this->input('module_id')),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
