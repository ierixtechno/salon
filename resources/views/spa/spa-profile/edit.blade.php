<x-app-layout>
    <x-slot name="header">Spa Profile — {{ $customer->name }}</x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            <div class="flex gap-4 text-sm">
                <a href="{{ route('customers.edit', $customer) }}" class="text-indigo-600 hover:text-indigo-800">&larr; Back to customer</a>
                <a href="{{ route('spa.consultations.index', $customer) }}" class="text-indigo-600 hover:text-indigo-800">Consultation history &rarr;</a>
            </div>

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-medium text-gray-900 mb-4">Spa Profile</h3>
                <p class="text-xs text-gray-500 mb-4">Persistent health/preference details used to screen contraindications before a therapy session.</p>

                <form method="POST" action="{{ route('spa.profile.update', $customer) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="health_conditions" value="Health conditions" />
                        <textarea id="health_conditions" name="health_conditions" rows="2"
                            class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('health_conditions', $profile->health_conditions) }}</textarea>
                        <x-input-error :messages="$errors->get('health_conditions')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="allergies" value="Allergies" />
                        <textarea id="allergies" name="allergies" rows="2"
                            class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('allergies', $profile->allergies) }}</textarea>
                        <x-input-error :messages="$errors->get('allergies')" class="mt-2" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="pressure_preference" value="Pressure preference" />
                            <x-text-input id="pressure_preference" class="block mt-1 w-full" type="text" name="pressure_preference" :value="old('pressure_preference', $profile->pressure_preference)" />
                            <x-input-error :messages="$errors->get('pressure_preference')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="areas_to_avoid" value="Areas to avoid" />
                            <x-text-input id="areas_to_avoid" class="block mt-1 w-full" type="text" name="areas_to_avoid" :value="old('areas_to_avoid', $profile->areas_to_avoid)" />
                            <x-input-error :messages="$errors->get('areas_to_avoid')" class="mt-2" />
                        </div>
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
