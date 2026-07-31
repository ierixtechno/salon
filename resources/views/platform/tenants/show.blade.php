<x-platform-layout>
    <x-slot name="header">{{ $tenant->name }}</x-slot>

    <div class="flex items-center gap-3 mb-6">
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-indigo-700 text-sm font-semibold">
            {{ strtoupper(substr($tenant->name, 0, 2)) }}
        </span>
        <div>
            <div class="font-semibold text-gray-900">{{ $tenant->name }}</div>
            <x-platform.status-badge :status="$tenant->status" />
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="font-semibold text-gray-900 mb-4">Status</h2>
            <form method="POST" action="{{ route('platform.tenants.status', $tenant) }}" class="flex flex-wrap items-center gap-3">
                @csrf
                @method('PATCH')
                <select name="status" class="border-gray-300 rounded-lg shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach (['pending_payment', 'trial', 'active', 'suspended', 'cancelled'] as $status)
                        <option value="{{ $status }}" @selected($tenant->status === $status)>{{ ucwords(str_replace('_', ' ', $status)) }}</option>
                    @endforeach
                </select>
                <button type="submit" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-500 transition">
                    Update
                </button>
            </form>

            <dl class="mt-6 space-y-3 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">Timezone</dt><dd class="font-medium text-gray-900">{{ $tenant->timezone }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Currency</dt><dd class="font-medium text-gray-900">{{ $tenant->currency }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Trial ends</dt><dd class="font-medium text-gray-900">{{ $tenant->trial_ends_at?->toFormattedDateString() ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Plan</dt><dd class="font-medium text-gray-900">{{ $subscription?->plan?->name ?? '—' }}</dd></div>
            </dl>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="font-semibold text-gray-900 mb-4">Modules</h2>
            <form method="POST" action="{{ route('platform.tenants.modules', $tenant) }}">
                @csrf
                @method('PATCH')

                <div class="space-y-3">
                    @foreach ($modules as $module)
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="modules[]" value="{{ $module->code }}"
                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                @checked($enabledModuleCodes->contains($module->code))>
                            <span class="text-sm text-gray-800">{{ $module->name }}</span>
                        </label>
                    @endforeach
                </div>

                <p class="text-xs text-gray-500 mt-3">Disabling a module never deletes historical data — it just turns off new activity in it.</p>

                <div class="flex justify-end mt-4">
                    <button type="submit" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-500 transition">
                        Save modules
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-platform-layout>
