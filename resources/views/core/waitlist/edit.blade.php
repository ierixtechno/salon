<x-app-layout>
    <x-slot name="header">Waitlist — {{ $entry->customer->name }}</x-slot>

    <div class="py-8">
        <div class="px-4 sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('waitlist.update', $entry) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <p class="text-sm text-gray-600">{{ $entry->branch->name }} — {{ $entry->service->name }}</p>

                    <div>
                        <x-input-label for="preferred_date" value="Preferred date" />
                        <x-text-input id="preferred_date" class="block mt-1 w-full" type="date" name="preferred_date" :value="old('preferred_date', optional($entry->preferred_date)->format('Y-m-d'))" />
                        <x-input-error :messages="$errors->get('preferred_date')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="status" value="Status" />
                        <select id="status" name="status" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                            @foreach (\App\Domain\Core\Models\WaitlistEntry::STATUSES as $status)
                                <option value="{{ $status }}" @selected(old('status', $entry->status) === $status)>{{ str($status)->headline() }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('status')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="notes" value="Notes" />
                        <textarea id="notes" name="notes" rows="2"
                            class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('notes', $entry->notes) }}</textarea>
                        <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                    </div>

                    <div class="flex items-center justify-between pt-2">
                        <a href="{{ route('waitlist.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Back</a>
                        <x-primary-button>Save</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
