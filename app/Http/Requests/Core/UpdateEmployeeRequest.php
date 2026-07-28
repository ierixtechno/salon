<?php

namespace App\Http\Requests\Core;

use App\Domain\Core\Models\EmployeeProfile;
use App\Rules\IndianMobileNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('employees.update');
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'employment_type' => ['required', Rule::in(EmployeeProfile::EMPLOYMENT_TYPES)],
            'hire_date' => ['nullable', 'date'],
            'phone' => ['nullable', new IndianMobileNumber],
            'is_active' => ['nullable', 'boolean'],
            'role' => ['required', Rule::in(Role::where('tenant_id', $tenantId)->pluck('name'))],
            'all_branches' => ['nullable', 'boolean'],
            'branches' => ['required_if:all_branches,false', 'array'],
            'branches.*' => ['integer', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
        ];
    }
}
