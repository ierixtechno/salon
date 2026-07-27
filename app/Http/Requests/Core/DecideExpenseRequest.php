<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;

class DecideExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('expenses.approve');
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
