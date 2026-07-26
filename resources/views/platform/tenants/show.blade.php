<x-platform-layout>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-semibold text-gray-900">{{ $tenant->name }}</h1>
        <span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-700 capitalize">{{ $tenant->status }}</span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h2 class="font-medium text-gray-900 mb-4">Status</h2>
            <form method="POST" action="{{ route('platform.tenants.status', $tenant) }}" class="flex flex-wrap items-center gap-3">
                @csrf
                @method('PATCH')
                <select name="status" class="border-gray-300 rounded-md shadow-sm text-sm">
                    @foreach (['trial', 'active', 'suspended', 'cancelled'] as $status)
                        <option value="{{ $status }}" @selected($tenant->status === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
                <x-primary-button>Update</x-primary-button>
            </form>

            <dl class="mt-6 space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">Timezone</dt><dd>{{ $tenant->timezone }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Currency</dt><dd>{{ $tenant->currency }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Trial ends</dt><dd>{{ $tenant->trial_ends_at?->toFormattedDateString() ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Plan</dt><dd>{{ $subscription?->plan?->name ?? '—' }}</dd></div>
            </dl>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6">
            <h2 class="font-medium text-gray-900 mb-4">Modules</h2>
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
                    <x-primary-button>Save modules</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-platform-layout>
