<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;

class StoreGiftCardRedemptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('payments.create');
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:32'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
