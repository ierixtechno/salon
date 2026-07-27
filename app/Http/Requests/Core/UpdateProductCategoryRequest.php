<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('products.update');
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('product_categories', 'name')
                    ->where('tenant_id', $this->user()->tenant_id)
                    ->ignore($this->route('product_category')),
            ],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
