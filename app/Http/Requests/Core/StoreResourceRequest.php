<?php

namespace App\Http\Requests\Core;

use App\Domain\Core\Models\Resource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreResourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('resources.create');
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(Resource::TYPES)],
            'name' => ['required', 'string', 'max:255'],
            'capacity' => ['required', 'integer', 'min:1', 'max:20'],
            'turnaround_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
        ];
    }
}
