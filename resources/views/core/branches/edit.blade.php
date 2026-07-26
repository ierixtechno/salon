<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $branch->name }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-medium text-gray-900 mb-4">Details</h3>
                <form method="POST" action="{{ route('branches.update', $branch) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="name" value="Branch name" />
                            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name', $branch->name)" required />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="code" value="Short code" />
                            <x-text-input id="code" class="block mt-1 w-full" type="text" name="code" :value="old('code', $branch->code)" required />
                            <x-input-error :messages="$errors->get('code')" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="timezone" value="Timezone (optional)" />
                        <x-text-input id="timezone" class="block mt-1 w-full" type="text" name="timezone" :value="old('timezone', $branch->timezone)" />
                        <x-input-error :messages="$errors->get('timezone')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="address" value="Address" />
                        <textarea id="address" name="address" rows="2"
                            class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('address', $branch->address) }}</textarea>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="state" value="State (for GST)" />
                            <x-text-input id="state" class="block mt-1 w-full" type="text" name="state" :value="old('state', $branch->state)" placeholder="e.g. Karnataka" />
                            <x-input-error :messages="$errors->get('state')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="gstin" value="GSTIN" />
                            <x-text-input id="gstin" class="block mt-1 w-full uppercase" type="text" name="gstin" :value="old('gstin', $branch->gstin)" placeholder="15-character GSTIN" />
                            <x-input-error :messages="$errors->get('gstin')" class="mt-2" />
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <x-primary-button>Save</x-primary-button>
                    </div>
                </form>
            </div>

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-medium text-gray-900 mb-1">Modules</h3>
                <p class="text-xs text-gray-500 mb-4">Bounded by what's enabled for your whole account.</p>

                <form method="POST" action="{{ route('branches.modules', $branch) }}">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        @forelse ($tenantModules as $tm)
                            <label class="flex items-center gap-2 border rounded-md px-3 py-2 cursor-pointer hover:bg-gray-50 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50">
                                <input type="checkbox" name="modules[]" value="{{ $tm->module->code }}"
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                    @checked($enabledModuleCodes->contains($tm->module->code))>
                                <span class="text-sm text-gray-800">{{ $tm->module->name }}</span>
                            </label>
                        @empty
                            <p class="text-sm text-gray-500 col-span-3">No modules are enabled for your account yet.</p>
                        @endforelse
                    </div>

                    <div class="flex justify-end mt-4">
                        <x-primary-button>Save modules</x-primary-button>
                    </div>
                </form>
            </div>

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-medium text-gray-900 mb-1">Working Hours</h3>
                <p class="text-xs text-gray-500 mb-4">Overrides the organization default for this branch only.</p>

                <form method="POST" action="{{ route('branches.hours', $branch) }}">
                    @csrf
                    @method('PUT')

                    <div class="space-y-2">
                        @foreach ($hours as $i => $hour)
                            @php $day = \Carbon\Carbon::create()->startOfWeek(\Carbon\Carbon::SUNDAY)->addDays($hour->day_of_week)->format('l'); @endphp
                            <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4 py-2 border-b border-gray-100 last:border-0">
                                <input type="hidden" name="hours[{{ $i }}][day_of_week]" value="{{ $hour->day_of_week }}">
                                <div class="w-full sm:w-28 text-sm font-medium text-gray-700">{{ $day }}</div>

                                <label class="flex items-center gap-2 text-sm text-gray-600">
                                    <input type="checkbox" name="hours[{{ $i }}][is_closed]" value="1"
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                        @checked($hour->is_closed) onchange="this.closest('div').querySelectorAll('input[type=time]').forEach(el => el.disabled = this.checked)">
                                    Closed
                                </label>

                                <div class="flex items-center gap-2">
                                    <input type="time" name="hours[{{ $i }}][opens_at]"
                                        value="{{ optional($hour->opens_at)->format('H:i') ?? $hour->opens_at }}"
                                        @disabled($hour->is_closed)
                                        class="border-gray-300 rounded-md shadow-sm text-sm disabled:bg-gray-100">
                                    <span class="text-gray-400 text-sm">to</span>
                                    <input type="time" name="hours[{{ $i }}][closes_at]"
                                        value="{{ optional($hour->closes_at)->format('H:i') ?? $hour->closes_at }}"
                                        @disabled($hour->is_closed)
                                        class="border-gray-300 rounded-md shadow-sm text-sm disabled:bg-gray-100">
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex justify-end mt-4">
                        <x-primary-button>Save hours</x-primary-button>
                    </div>
                </form>
            </div>

            <div class="bg-white shadow-sm rounded-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="font-medium text-gray-900">Resources</h3>
                        <p class="text-xs text-gray-500">Chairs, rooms, and stations at this branch.</p>
                    </div>
                    <a href="{{ route('branches.resources.index', $branch) }}" class="text-sm text-indigo-600 hover:text-indigo-800">Manage resources</a>
                </div>
            </div>

            @can('delete', $branch)
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-medium text-gray-900 mb-1">Deactivate branch</h3>
                    <p class="text-xs text-gray-500 mb-4">Historical data is kept — this only stops new activity at this branch.</p>
                    <form method="POST" action="{{ route('branches.destroy', $branch) }}" onsubmit="return confirm('Deactivate this branch?')">
                        @csrf
                        @method('DELETE')
                        <x-danger-button>Deactivate</x-danger-button>
                    </form>
                </div>
            @endcan
        </div>
    </div>
</x-app-layout>
