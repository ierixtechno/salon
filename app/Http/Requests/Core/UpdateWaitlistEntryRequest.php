<?php

namespace App\Http\Requests\Core;

use App\Domain\Core\Models\WaitlistEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWaitlistEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('waitlist.update');
    }

    public function rules(): array
    {
        return [
            'preferred_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(WaitlistEntry::STATUSES)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
