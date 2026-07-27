<?php

namespace App\Http\Requests\Core;

use App\Domain\Core\Models\CustomerSegment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerSegmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('marketing.segments.manage');
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('customer_segments', 'name')->where('tenant_id', $this->user()->tenant_id)->ignore($this->route('customer_segment')),
            ],
            'type' => ['required', Rule::in(CustomerSegment::TYPES)],
            'tag' => ['required_if:type,tag', 'nullable', 'string', 'max:255'],
            'days' => ['required_if:type,inactive_days', 'nullable', 'integer', 'min:1', 'max:3650'],
        ];
    }
}
