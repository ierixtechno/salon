<?php

namespace App\Http\Requests\Core;

use App\Domain\Core\Models\ServiceCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('services.update');
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;
        /** @var ServiceCategory $category */
        $category = $this->route('service_category');

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('service_categories', 'name')
                    ->where('tenant_id', $tenantId)
                    ->where('module_id', $category->module_id)
                    ->ignore($category->id),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
