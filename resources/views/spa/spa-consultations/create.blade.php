<x-app-layout>
    <x-slot name="header">New Spa Consultation — {{ $customer->name }}</x-slot>

    <div class="py-8">
        <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                @if ($branches->isEmpty())
                    <p class="text-sm text-gray-500">No branch has the Spa module enabled yet.</p>
                @else
                    <form method="POST" action="{{ route('spa.consultations.store', $customer) }}" class="space-y-4">
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
                            <x-input-label for="concerns" value="Concerns / contraindications" />
                            <textarea id="concerns" name="concerns" rows="2"
                                class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('concerns') }}</textarea>
                            <x-input-error :messages="$errors->get('concerns')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="recommendation" value="Recommendation" />
                            <textarea id="recommendation" name="recommendation" rows="2"
                                class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('recommendation') }}</textarea>
                            <x-input-error :messages="$errors->get('recommendation')" class="mt-2" />
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
