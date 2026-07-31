<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmQuotationPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('tenant.billing.manage');
    }

    public function rules(): array
    {
        return [
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_signature' => ['required', 'string'],
        ];
    }
}
