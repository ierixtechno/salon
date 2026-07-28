<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `website` (the honeypot) is deliberately not validated as "must be
 * empty" here — a bot that fails validation learns it was detected. It's
 * checked separately in the controller after validation succeeds, and a
 * filled honeypot silently no-ops behind a normal-looking success response
 * (CLAUDE.md §75 bot/spam protection).
 */
class StorePublicBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->attributes->get('tenant')->id;

        return [
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'service_variant_id' => ['nullable', 'integer', Rule::exists('service_variants', 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'starts_at' => ['required', 'date', 'after:now', 'before:'.now()->addDays(30)->toIso8601String()],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'marketing_consent' => ['nullable', 'boolean'],
            'website' => ['nullable', 'string'],
        ];
    }
}
