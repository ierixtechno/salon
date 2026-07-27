<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeaveTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('leave-types.manage');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('leave_types', 'name')->where('tenant_id', $this->user()->tenant_id)],
            'annual_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'is_paid' => ['nullable', 'boolean'],
        ];
    }
}
