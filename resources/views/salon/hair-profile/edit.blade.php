<x-app-layout>
    <x-slot name="header">Hair Profile — {{ $customer->name }}</x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            <div class="flex gap-4 text-sm">
                <a href="{{ route('customers.edit', $customer) }}" class="text-indigo-600 hover:text-indigo-800">&larr; Back to customer</a>
                <a href="{{ route('salon.consultations.index', $customer) }}" class="text-indigo-600 hover:text-indigo-800">Consultation history &rarr;</a>
            </div>

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-medium text-gray-900 mb-4">Hair &amp; Scalp Profile</h3>
                <p class="text-xs text-gray-500 mb-4">Persistent characteristics — kept up to date across visits. Per-visit assessments are recorded as consultations.</p>

                <form method="POST" action="{{ route('salon.profile.update', $customer) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="hair_type" value="Hair type" />
                            <x-text-input id="hair_type" class="block mt-1 w-full" type="text" name="hair_type" :value="old('hair_type', $profile->hair_type)" />
                            <x-input-error :messages="$errors->get('hair_type')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="scalp_type" value="Scalp type" />
                            <x-text-input id="scalp_type" class="block mt-1 w-full" type="text" name="scalp_type" :value="old('scalp_type', $profile->scalp_type)" />
                            <x-input-error :messages="$errors->get('scalp_type')" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="chemical_history" value="Chemical treatment history" />
                        <textarea id="chemical_history" name="chemical_history" rows="2"
                            class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('chemical_history', $profile->chemical_history) }}</textarea>
                        <x-input-error :messages="$errors->get('chemical_history')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="allergies" value="Allergies" />
                        <textarea id="allergies" name="allergies" rows="2"
                            class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('allergies', $profile->allergies) }}</textarea>
                        <x-input-error :messages="$errors->get('allergies')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="notes" value="Notes" />
                        <textarea id="notes" name="notes" rows="2"
                            class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('notes', $profile->notes) }}</textarea>
                        <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                    </div>

                    <div class="flex justify-end">
                        <x-primary-button>Save profile</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
