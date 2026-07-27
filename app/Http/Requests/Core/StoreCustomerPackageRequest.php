<?php

namespace App\Http\Requests\Core;

use App\Domain\Core\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('packages.sell');
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'package_id' => ['required', 'integer', Rule::exists('packages', 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'price_paid' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'purchase_method' => ['required', Rule::in(['cash', 'card', 'upi', 'bank_transfer'])],
            'purchase_reference' => ['nullable', 'string', 'max:255'],
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
