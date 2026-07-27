<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCampaignAutomationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('marketing.automations.manage');
    }

    public function rules(): array
    {
        return [
            'is_enabled' => ['nullable', 'boolean'],
            'template_id' => [
                'nullable', 'integer',
                Rule::exists('notification_templates', 'id')->where('tenant_id', $this->user()->tenant_id)->where('is_active', true),
            ],
            'threshold_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ];
    }
}
