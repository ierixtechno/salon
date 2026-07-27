<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceConsumablesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('services.update');
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'consumables' => ['present', 'array'],
            'consumables.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('tenant_id', $tenantId)],
            'consumables.*.quantity_per_use' => ['required', 'numeric', 'min:0.001', 'max:999999.999'],
        ];
    }
}
