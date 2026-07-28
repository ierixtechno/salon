<x-app-layout>
    <x-slot name="header">New Tattoo Consultation — {{ $customer->name }}</x-slot>

    <div class="py-8">
        <div class="px-4 sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                @if ($branches->isEmpty())
                    <p class="text-sm text-gray-500">No branch has the Tattoo Studio module enabled yet.</p>
                @else
                    <form method="POST" action="{{ route('tattoo.consultations.store', $customer) }}" class="space-y-4">
                        @csrf

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="branch_id" value="Branch" />
                                <select id="branch_id" name="branch_id" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                    @foreach ($branches as $branch)
                                        <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('branch_id')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="consultation_date" value="Date" />
                                <x-text-input id="consultation_date" class="block mt-1 w-full" type="date" name="consultation_date" :value="old('consultation_date', now()->toDateString())" required />
                                <x-input-error :messages="$errors->get('consultation_date')" class="mt-2" />
                            </div>
                        </div>

                        <div>
                            <x-input-label for="design_description" value="Design description" />
                            <textarea id="design_description" name="design_description" rows="2"
                                class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('design_description') }}</textarea>
                            <x-input-error :messages="$errors->get('design_description')" class="mt-2" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="placement" value="Placement" />
                                <x-text-input id="placement" class="block mt-1 w-full" type="text" name="placement" :value="old('placement')" placeholder="e.g. Left forearm" />
                                <x-input-error :messages="$errors->get('placement')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="size_estimate" value="Size estimate" />
                                <x-text-input id="size_estimate" class="block mt-1 w-full" type="text" name="size_estimate" :value="old('size_estimate')" placeholder="e.g. 4x6 in" />
                                <x-input-error :messages="$errors->get('size_estimate')" class="mt-2" />
                            </div>
                        </div>

                        <div>
                            <x-input-label for="aftercare_instructions" value="Aftercare instructions" />
                            <textarea id="aftercare_instructions" name="aftercare_instructions" rows="2"
                                class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('aftercare_instructions') }}</textarea>
                            <x-input-error :messages="$errors->get('aftercare_instructions')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="notes" value="Notes" />
                            <textarea id="notes" name="notes" rows="2"
                                class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('notes') }}</textarea>
                            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                        </div>

                        <div class="flex justify-end">
                            <x-primary-button>Save consultation</x-primary-button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
