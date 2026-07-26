<?php

namespace App\Http\Requests\Spa;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSpaProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('spa-consultations.update');
    }

    public function rules(): array
    {
        return [
            'health_conditions' => ['nullable', 'string', 'max:2000'],
            'allergies' => ['nullable', 'string', 'max:2000'],
            'pressure_preference' => ['nullable', 'string', 'max:100'],
            'areas_to_avoid' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
