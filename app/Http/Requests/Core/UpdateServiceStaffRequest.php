<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('services.update');
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'user_ids' => ['present', 'array'],
            'user_ids.*' => ['integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
        ];
    }
}
