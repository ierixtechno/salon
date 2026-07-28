<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServiceDeliveryModesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('services.update');
    }

    public function rules(): array
    {
        return [
            'home_service_enabled' => ['boolean'],
            'home_service_fee' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'venue_service_enabled' => ['boolean'],
            'venue_service_fee' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'travel_buffer_minutes' => ['nullable', 'integer', 'min:0', 'max:600'],
        ];
    }
}
