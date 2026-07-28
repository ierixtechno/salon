<?php

namespace App\Http\Requests\Core;

use App\Rules\IndianMobileNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('suppliers.create');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('suppliers', 'name')->where('tenant_id', $this->user()->tenant_id)],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', new IndianMobileNumber],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
