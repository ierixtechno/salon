<x-app-layout>
    <x-slot name="header">Marketing Automations</x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            <p class="text-sm text-gray-500">
                These run automatically once a day. Each stays off until you enable it and pick a message template.
            </p>

            @php
                $labels = [
                    'birthday' => ['Birthday Wishes', 'Sent on the customer\'s birthday.'],
                    'membership_expiry' => ['Membership Expiring Soon', 'Sent a set number of days before a membership expires.'],
                    'package_expiry' => ['Package Expiring Soon', 'Sent a set number of days before a package expires.'],
                    're_engagement' => ['We Miss You', 'Sent to customers with no recent appointment.'],
                    'feedback_request' => ['How Was Your Visit', 'Sent the day after a completed appointment.'],
                ];
                $needsThreshold = ['membership_expiry', 'package_expiry', 're_engagement'];
            @endphp

            @foreach ($automations as $automation)
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <form method="POST" action="{{ route('campaign-automations.update', $automation->type) }}" class="space-y-4">
                        @csrf
                        @method('PUT')

                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="font-medium text-gray-900">{{ $labels[$automation->type][0] }}</h3>
                                <p class="text-xs text-gray-500">{{ $labels[$automation->type][1] }}</p>
                            </div>
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="is_enabled" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" @checked($automation->is_enabled)>
                                <span class="text-sm text-gray-700">Enabled</span>
                            </label>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label value="Template" />
                                <select name="template_id" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm text-sm">
                                    <option value="">None selected</option>
                                    @foreach ($templates as $template)
                                        <option value="{{ $template->id }}" @selected($automation->template_id === $template->id)>{{ $template->name }} ({{ ucfirst($template->channel) }})</option>
                                    @endforeach
                                </select>
                            </div>
                            @if (in_array($automation->type, $needsThreshold))
                                <div>
                                    <x-input-label value="Days" />
                                    <x-text-input class="block mt-1 w-full text-sm" type="number" min="1" name="threshold_days" :value="$automation->threshold_days ?? 7" />
                                </div>
                            @endif
                        </div>

                        <div class="flex justify-end">
                            <x-primary-button>Save</x-primary-button>
                        </div>
                    </form>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
