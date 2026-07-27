<?php

namespace App\Http\Requests\Core;

use App\Domain\Core\Models\SupplierPayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('supplier-payments.create');
    }

    public function rules(): array
    {
        return [
            'purchase_order_id' => [
                'nullable', 'integer',
                Rule::exists('purchase_orders', 'id')->where('tenant_id', $this->user()->tenant_id)->where('supplier_id', $this->route('supplier')->id),
            ],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'method' => ['required', Rule::in(SupplierPayment::METHODS)],
            'reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
