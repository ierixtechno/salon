<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;

class StoreWalletCreditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('wallet.credit');
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
