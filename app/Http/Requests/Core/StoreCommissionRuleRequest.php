<?php

namespace App\Http\Requests\Core;

use App\Domain\Core\Models\CommissionRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommissionRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('commission.manage');
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(CommissionRule::TYPES)],
            'rate' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
