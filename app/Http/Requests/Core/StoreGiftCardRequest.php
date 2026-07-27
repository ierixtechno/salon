<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGiftCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('gift-cards.create');
    }

    public function rules(): array
    {
        return [
            'initial_value' => ['required', 'numeric', 'min:1', 'max:999999.99'],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('tenant_id', $this->user()->tenant_id)],
            'purchase_method' => ['required', Rule::in(['cash', 'card', 'upi', 'bank_transfer'])],
            'purchase_reference' => ['nullable', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ];
    }
}
