<?php

namespace App\Http\Requests\Core;

use App\Domain\Core\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('purchase-orders.create');
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->where('tenant_id', $tenantId)],
            'expected_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('tenant_id', $tenantId)],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.001', 'max:999999.999'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:999999.99'],
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
