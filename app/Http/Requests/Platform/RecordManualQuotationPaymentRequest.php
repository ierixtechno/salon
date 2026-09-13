<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Guarded only by the `auth:platform` route middleware group (see
 * routes/platform.php) — there is a single Super Admin role today, same
 * pattern as every other Platform\* request in this app (e.g.
 * TopUpWhatsappCreditsRequest).
 */
class RecordManualQuotationPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_method' => ['required', 'string', Rule::in(['bank_transfer', 'upi', 'cash', 'cheque', 'other'])],
            'payment_reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
