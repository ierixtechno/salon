<?php

namespace App\Http\Requests\Core;

use App\Domain\Core\Models\BusinessProfile;
use App\Rules\IndianMobileNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBusinessProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('tenant.settings.manage');
    }

    public function rules(): array
    {
        return [
            'display_name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'business_type' => ['nullable', Rule::in(BusinessProfile::BUSINESS_TYPES)],
            'contact_email' => ['nullable', 'string', 'email', 'max:255'],
            'contact_phone' => ['nullable', new IndianMobileNumber],
            'address' => ['nullable', 'string', 'max:2000'],
            'cancellation_policy' => ['nullable', 'string', 'max:5000'],
            'loyalty_points_per_100' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'loyalty_redemption_value' => ['nullable', 'numeric', 'min:0', 'max:9999.9999'],
            'loyalty_points_expiry_days' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
