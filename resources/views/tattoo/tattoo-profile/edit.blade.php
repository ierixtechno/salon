<x-app-layout>
    <x-slot name="header">Tattoo Profile — {{ $customer->name }}</x-slot>

    <div class="py-8">
        <div class="px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            <div class="flex gap-4 text-sm">
                <a href="{{ route('customers.edit', $customer) }}" class="text-indigo-600 hover:text-indigo-800">&larr; Back to customer</a>
                <a href="{{ route('tattoo.consultations.index', $customer) }}" class="text-indigo-600 hover:text-indigo-800">Consultation history &rarr;</a>
            </div>

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-medium text-gray-900 mb-4">Tattoo Profile</h3>
                <p class="text-xs text-gray-500 mb-4">Persistent skin/health details used to screen contraindications before a tattoo session.</p>

                <form method="POST" action="{{ route('tattoo.profile.update', $customer) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="skin_conditions" value="Skin conditions" />
                        <textarea id="skin_conditions" name="skin_conditions" rows="2"
                            class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('skin_conditions', $profile->skin_conditions) }}</textarea>
                        <x-input-error :messages="$errors->get('skin_conditions')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="allergies" value="Allergies" />
                        <textarea id="allergies" name="allergies" rows="2"
                            class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('allergies', $profile->allergies) }}</textarea>
                        <x-input-error :messages="$errors->get('allergies')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="previous_tattoos" value="Previous tattoos" />
                        <textarea id="previous_tattoos" name="previous_tattoos" rows="2"
                            class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('previous_tattoos', $profile->previous_tattoos) }}</textarea>
                        <x-input-error :messages="$errors->get('previous_tattoos')" class="mt-2" />
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
