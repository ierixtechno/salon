<?php

namespace App\Http\Requests\BeautyParlour;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSkinProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('beauty-consultations.update');
    }

    public function rules(): array
    {
        return [
            'skin_type' => ['nullable', 'string', 'max:100'],
            'known_conditions' => ['nullable', 'string', 'max:2000'],
            'allergies' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
