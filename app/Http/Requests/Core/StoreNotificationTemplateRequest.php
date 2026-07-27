<?php

namespace App\Http\Requests\Core;

use App\Domain\Core\Models\NotificationTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNotificationTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('marketing.templates.manage');
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('notification_templates', 'name')->where('tenant_id', $this->user()->tenant_id)->where('channel', $this->input('channel')),
            ],
            'channel' => ['required', Rule::in(NotificationTemplate::CHANNELS)],
            'subject' => ['nullable', 'string', 'max:255', 'required_if:channel,email'],
            'body' => ['required', 'string', 'max:5000'],
        ];
    }
}
