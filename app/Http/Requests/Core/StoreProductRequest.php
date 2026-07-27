<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('products.create');
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'product_category_id' => ['required', Rule::exists('product_categories', 'id')->where('tenant_id', $tenantId)],
            'name' => ['required', 'string', 'max:255', Rule::unique('products', 'name')->where('tenant_id', $tenantId)],
            'sku' => ['nullable', 'string', 'max:100', Rule::unique('products', 'sku')->where('tenant_id', $tenantId)],
            'brand' => ['nullable', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:50'],
            'cost_price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'selling_price' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'reorder_level' => ['nullable', 'numeric', 'min:0', 'max:999999.999'],
        ];
    }
}
