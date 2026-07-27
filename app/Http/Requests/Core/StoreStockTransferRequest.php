<?php

namespace App\Http\Requests\Core;

use App\Domain\Core\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inventory.adjust');
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'from_branch_id' => ['required', 'integer', 'different:to_branch_id', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'to_branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('tenant_id', $tenantId)],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.001', 'max:999999.999'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $fromBranch = Branch::find($this->input('from_branch_id'));
            $toBranch = Branch::find($this->input('to_branch_id'));

            if ($fromBranch && ! $this->user()->canAccessBranch($fromBranch)) {
                $validator->errors()->add('from_branch_id', 'You do not have access to the source branch.');
            }
            if ($toBranch && ! $this->user()->canAccessBranch($toBranch)) {
                $validator->errors()->add('to_branch_id', 'You do not have access to the destination branch.');
            }
        });
    }
}
