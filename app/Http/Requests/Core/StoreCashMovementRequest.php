<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;

class StoreCashMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cash-register.manage');
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999.99'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
