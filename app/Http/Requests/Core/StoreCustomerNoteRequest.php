<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('customers.update');
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
        ];
    }
}
