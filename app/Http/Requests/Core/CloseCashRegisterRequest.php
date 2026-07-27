<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;

class CloseCashRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cash-register.manage');
    }

    public function rules(): array
    {
        return [
            'actual_closing' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
