<?php

namespace App\Http\Requests\Tattoo;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTattooProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('tattoo-consultations.update');
    }

    public function rules(): array
    {
        return [
            'skin_conditions' => ['nullable', 'string', 'max:2000'],
            'allergies' => ['nullable', 'string', 'max:2000'],
            'previous_tattoos' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
