<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePackageRedemptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('packages.redeem');
    }

    public function rules(): array
    {
        return [
            'customer_package_item_id' => [
                'required', 'integer',
                Rule::exists('customer_package_items', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
        ];
    }
}
