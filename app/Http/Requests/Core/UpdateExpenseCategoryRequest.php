<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('expense-categories.manage');
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('expense_categories', 'name')->where('tenant_id', $this->user()->tenant_id)->ignore($this->route('expense_category')),
            ],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
