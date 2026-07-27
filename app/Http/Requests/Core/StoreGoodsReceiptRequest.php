<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGoodsReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('purchase_order'));
    }

    public function rules(): array
    {
        $purchaseOrderId = $this->route('purchase_order')->id;

        return [
            'supplier_invoice_number' => ['nullable', 'string', 'max:255'],
            'supplier_invoice_amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.purchase_order_line_id' => [
                'required', 'integer',
                Rule::exists('purchase_order_lines', 'id')->where('purchase_order_id', $purchaseOrderId),
            ],
            'lines.*.quantity' => ['required', 'numeric', 'min:0', 'max:999999.999'],
        ];
    }
}
