<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">New Branch</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('branches.store') }}" class="space-y-4">
                    @csrf

                    <div>
                        <x-input-label for="name" value="Branch name" />
                        <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="code" value="Short code" />
                        <x-text-input id="code" class="block mt-1 w-full" type="text" name="code" :value="old('code')" required />
                        <p class="text-xs text-gray-500 mt-1">Used internally, e.g. "MAIN", "DOWNTOWN". Letters, numbers, dashes/underscores.</p>
                        <x-input-error :messages="$errors->get('code')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="timezone" value="Timezone (optional — inherits organization default)" />
                        <x-text-input id="timezone" class="block mt-1 w-full" type="text" name="timezone" :value="old('timezone')" placeholder="e.g. Asia/Kolkata" />
                        <x-input-error :messages="$errors->get('timezone')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="address" value="Address" />
                        <textarea id="address" name="address" rows="2"
                            class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('address') }}</textarea>
                        <x-input-error :messages="$errors->get('address')" class="mt-2" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="state" value="State (for GST)" />
                            <x-text-input id="state" class="block mt-1 w-full" type="text" name="state" :value="old('state')" placeholder="e.g. Karnataka" />
                            <x-input-error :messages="$errors->get('state')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="gstin" value="GSTIN (optional)" />
                            <x-text-input id="gstin" class="block mt-1 w-full uppercase" type="text" name="gstin" :value="old('gstin')" placeholder="15-character GSTIN" />
                            <x-input-error :messages="$errors->get('gstin')" class="mt-2" />
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <x-primary-button>Create branch</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
