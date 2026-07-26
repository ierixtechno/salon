<?php

namespace App\Http\Requests\Core;

use App\Domain\Core\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('refunds.create');
    }

    public function rules(): array
    {
        return [
            'method' => ['required', Rule::in(Payment::METHODS)],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
