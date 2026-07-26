<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('employees.update');
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $this->user()->tenant_id)],
            'shifts' => ['required', 'array', 'size:7'],
            'shifts.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'shifts.*.is_off' => ['nullable', 'boolean'],
            'shifts.*.starts_at' => ['nullable', 'required_if:shifts.*.is_off,false', 'date_format:H:i'],
            'shifts.*.ends_at' => ['nullable', 'required_if:shifts.*.is_off,false', 'date_format:H:i', 'after:shifts.*.starts_at'],
        ];
    }
}
