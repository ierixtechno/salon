<?php

namespace App\Http\Requests\Salon;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHairProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('salon-consultations.update');
    }

    public function rules(): array
    {
        return [
            'hair_type' => ['nullable', 'string', 'max:100'],
            'scalp_type' => ['nullable', 'string', 'max:100'],
            'chemical_history' => ['nullable', 'string', 'max:2000'],
            'allergies' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
