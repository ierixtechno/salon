<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeReductionRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('tenant.billing.manage');
    }

    public function rules(): array
    {
        return [
            'requested_extra_user_count' => ['required', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
