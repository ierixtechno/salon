<?php

namespace App\Http\Requests\Core;

use App\Domain\Core\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OpenCashRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cash-register.manage');
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $this->user()->tenant_id)],
            'opening_cash' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $branch = Branch::find($this->input('branch_id'));

            if ($branch && ! $this->user()->canAccessBranch($branch)) {
                $validator->errors()->add('branch_id', 'You do not have access to the selected branch.');
            }
        });
    }
}
