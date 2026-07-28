<?php

namespace App\Http\Requests\Core;

use App\Domain\Core\Models\Customer;
use App\Rules\IndianMobileNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('customers.update');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', new IndianMobileNumber],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(Customer::GENDERS)],
            'tags' => ['nullable', 'string', 'max:500'],
            'source' => ['nullable', 'string', 'max:100'],
        ];
    }
}
