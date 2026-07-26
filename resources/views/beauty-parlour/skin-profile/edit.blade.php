<x-app-layout>
    <x-slot name="header">Skin Profile — {{ $customer->name }}</x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            <div class="flex gap-4 text-sm">
                <a href="{{ route('customers.edit', $customer) }}" class="text-indigo-600 hover:text-indigo-800">&larr; Back to customer</a>
                <a href="{{ route('beauty.consultations.index', $customer) }}" class="text-indigo-600 hover:text-indigo-800">Consultation history &rarr;</a>
            </div>

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-medium text-gray-900 mb-4">Skin Profile</h3>
                <p class="text-xs text-gray-500 mb-4">Persistent characteristics — kept up to date across visits. Per-visit assessments are recorded as consultations.</p>

                <form method="POST" action="{{ route('beauty.profile.update', $customer) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="skin_type" value="Skin type" />
                        <x-text-input id="skin_type" class="block mt-1 w-full" type="text" name="skin_type" :value="old('skin_type', $profile->skin_type)" />
                        <x-input-error :messages="$errors->get('skin_type')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="known_conditions" value="Known conditions" />
                        <textarea id="known_conditions" name="known_conditions" rows="2"
                            class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('known_conditions', $profile->known_conditions) }}</textarea>
                        <x-input-error :messages="$errors->get('known_conditions')" class="mt-2" />
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
