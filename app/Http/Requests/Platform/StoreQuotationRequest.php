<?php

namespace App\Http\Requests\Platform;

use App\Rules\BranchCountWithinPlan;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'integer', Rule::exists('tenants', 'id')],
            'subscription_plan_id' => ['required', 'integer', Rule::exists('subscription_plans', 'id')->where('is_active', true)],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100', function (string $attribute, mixed $value, Closure $fail) {
                if ((float) $value > 0 && filled($this->input('amount'))) {
                    $fail('Use either a discount percentage or a custom amount, not both.');
                }
            }],
            'branch_count' => ['nullable', 'integer', 'min:1', new BranchCountWithinPlan($this->input('subscription_plan_id'))],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
