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

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            // Unique within the tenant (matches users' tenant_id+email unique
            // index), ignoring this employee's own row.
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->where('tenant_id', $tenantId)->ignore($this->route('employee')?->user_id)],
            'job_title' => ['nullable', 'string', 'max:255'],
            'employment_type' => ['required', Rule::in(EmployeeProfile::EMPLOYMENT_TYPES)],
            'hire_date' => ['nullable', 'date'],
            'phone' => ['nullable', new IndianMobileNumber],
            'is_active' => ['nullable', 'boolean'],
            'role' => ['required', Rule::in(Role::where('tenant_id', $tenantId)->pluck('name')), $this->user()->isOwner() ? 'string' : Rule::notIn(['Owner'])],
            'all_branches' => ['nullable', 'boolean'],
            'branches' => ['required_if:all_branches,false', 'array'],
            'branches.*' => ['integer', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
        ];
    }
}
