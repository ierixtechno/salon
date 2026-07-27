<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('marketing.campaigns.create');
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'segment_id' => ['required', 'integer', Rule::exists('customer_segments', 'id')->where('tenant_id', $tenantId)],
            'template_id' => [
                'required', 'integer',
                Rule::exists('notification_templates', 'id')->where('tenant_id', $tenantId)->where('is_active', true),
            ],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
