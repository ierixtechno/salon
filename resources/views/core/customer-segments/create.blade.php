<x-app-layout>
    <x-slot name="header">New Customer Segment</x-slot>

    <div class="py-8">
        <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('customer-segments.store') }}" class="space-y-4" x-data="{ type: '{{ old('type', 'all') }}' }">
                    @csrf

                    <div>
                        <x-input-label for="name" value="Name" />
                        <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" placeholder="e.g. VIP Customers" required autofocus />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="type" value="Type" />
                        <select id="type" name="type" x-model="type" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                            @foreach (\App\Domain\Core\Models\CustomerSegment::TYPES as $type)
                                <option value="{{ $type }}" @selected(old('type') === $type)>{{ str($type)->replace('_', ' ')->headline() }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('type')" class="mt-2" />
                    </div>

                    <div x-show="type === 'tag'">
                        <x-input-label for="tag" value="Tag" />
                        <x-text-input id="tag" class="block mt-1 w-full" type="text" name="tag" :value="old('tag')" placeholder="e.g. vip" />
                        <x-input-error :messages="$errors->get('tag')" class="mt-2" />
                    </div>

                    <div x-show="type === 'inactive_days'">
                        <x-input-label for="days" value="No appointment in the last (days)" />
                        <x-text-input id="days" class="block mt-1 w-full" type="number" min="1" name="days" :value="old('days', 90)" />
                        <x-input-error :messages="$errors->get('days')" class="mt-2" />
                    </div>

                    <div class="flex justify-end">
                        <x-primary-button>Create segment</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
