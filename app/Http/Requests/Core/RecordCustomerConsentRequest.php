<?php

namespace App\Http\Requests\Core;

use App\Domain\Core\Models\CustomerConsent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordCustomerConsentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('customers.update');
    }

    public function rules(): array
    {
        return [
            'purpose' => ['required', Rule::in(CustomerConsent::PURPOSES)],
            'granted' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
