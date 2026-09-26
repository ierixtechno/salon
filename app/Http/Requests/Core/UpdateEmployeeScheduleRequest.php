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

    /**
     * An unchecked "Off" checkbox is not sent by the browser at all, which made
     * the required_if below silently skip and let empty times through to the
     * database (NOT NULL violation). Make the flag an explicit boolean first.
     */
    protected function prepareForValidation(): void
    {
        $shifts = $this->input('shifts');

        if (is_array($shifts)) {
            foreach ($shifts as $i => $shift) {
                if (is_array($shift)) {
                    $shifts[$i]['is_off'] = filter_var($shift['is_off'] ?? false, FILTER_VALIDATE_BOOLEAN);
                }
            }
            $this->merge(['shifts' => $shifts]);
        }
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
