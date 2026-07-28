<x-app-layout>
    <x-slot name="header">New Service</x-slot>

    <div class="py-8">
        <div class="px-4 sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                @if ($categories->isEmpty())
                    <p class="text-sm text-gray-500">
                        You need at least one active service category first.
                        <a href="{{ route('service-categories.create') }}" class="text-indigo-600 hover:text-indigo-800">Create one &rarr;</a>
                    </p>
                @else
                    <form method="POST" action="{{ route('services.store') }}" class="space-y-4">
                        @csrf

                        <div>
                            <x-input-label for="service_category_id" value="Category" />
                            <select id="service_category_id" name="service_category_id" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" @selected(old('service_category_id') == $category->id)>
                                        {{ $category->name }} ({{ $category->module->name }})
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('service_category_id')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="name" value="Service name" />
                            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="description" value="Description (optional)" />
                            <textarea id="description" name="description" rows="2"
                                class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('description') }}</textarea>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="duration_minutes" value="Duration (min)" />
                                <x-text-input id="duration_minutes" class="block mt-1 w-full" type="number" name="duration_minutes" min="1" :value="old('duration_minutes')" required />
                                <x-input-error :messages="$errors->get('duration_minutes')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="buffer_minutes" value="Buffer after (min, optional)" />
                                <x-text-input id="buffer_minutes" class="block mt-1 w-full" type="number" name="buffer_minutes" min="0" :value="old('buffer_minutes', 0)" />
                                <p class="text-xs text-gray-500 mt-1">Prep/cleanup time the stylist needs before their next appointment.</p>
                                <x-input-error :messages="$errors->get('buffer_minutes')" class="mt-2" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="base_price" value="Base price" />
                                <x-text-input id="base_price" class="block mt-1 w-full" type="number" step="0.01" min="0" name="base_price" :value="old('base_price')" required />
                                <x-input-error :messages="$errors->get('base_price')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="tax_rate_percent" value="Tax % (GST, optional)" />
                                <x-text-input id="tax_rate_percent" class="block mt-1 w-full" type="number" step="0.01" min="0" max="100" name="tax_rate_percent" :value="old('tax_rate_percent')" />
                                <p class="text-xs text-gray-500 mt-1">Split evenly into CGST+SGST on invoices.</p>
                            </div>
                        </div>

                        <div>
                            <x-input-label for="sac_code" value="SAC code (optional)" />
                            <x-text-input id="sac_code" class="block mt-1 w-full sm:w-48" type="text" name="sac_code" :value="old('sac_code')" placeholder="e.g. 999721" />
                            <p class="text-xs text-gray-500 mt-1">Services Accounting Code, printed on GST invoices.</p>
                            <x-input-error :messages="$errors->get('sac_code')" class="mt-2" />
                        </div>

                        <div class="flex justify-end">
                            <x-primary-button>Create service</x-primary-button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
