<?php

namespace App\Http\Requests\Core;

use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('expenses.create');
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'expense_category_id' => ['required', 'integer', Rule::exists('expense_categories', 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'vendor_name' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999.99'],
            'tax_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'payment_method' => ['required', Rule::in(Expense::PAYMENT_METHODS)],
            'expense_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
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
