<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMembershipPlanApplicabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('membership_plan'));
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'module_ids' => ['present', 'array'],
            'module_ids.*' => ['integer', Rule::exists('modules', 'id')],
            'branch_ids' => ['present', 'array'],
            'branch_ids.*' => ['integer', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'service_ids' => ['present', 'array'],
            'service_ids.*' => ['integer', Rule::exists('services', 'id')->where('tenant_id', $tenantId)],
        ];
    }
}
