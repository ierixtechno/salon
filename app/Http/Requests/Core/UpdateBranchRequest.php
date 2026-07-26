<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('branches.update');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required', 'string', 'max:50', 'alpha_dash',
                Rule::unique('branches', 'code')
                    ->where('tenant_id', $this->user()->tenant_id)
                    ->ignore($this->route('branch')),
            ],
            'timezone' => ['nullable', 'string', 'timezone'],
            'address' => ['nullable', 'string', 'max:2000'],
            'state' => ['nullable', 'string', 'max:100'],
            'gstin' => ['nullable', 'string', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/'],
        ];
    }
}
