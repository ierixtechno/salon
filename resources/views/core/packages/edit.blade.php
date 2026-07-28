<x-app-layout>
    <x-slot name="header">Edit Package</x-slot>

    <div class="py-8">
        <div class="px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('packages.update', $package) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="name" value="Package name" />
                        <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name', $package->name)" required autofocus />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="description" value="Description (optional)" />
                        <textarea id="description" name="description" rows="2" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">{{ old('description', $package->description) }}</textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="validity_days" value="Validity (days)" />
                            <x-text-input id="validity_days" class="block mt-1 w-full" type="number" min="1" name="validity_days" :value="old('validity_days', $package->validity_days)" required />
                            <x-input-error :messages="$errors->get('validity_days')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="price" value="Price" />
                            <x-text-input id="price" class="block mt-1 w-full" type="number" step="0.01" min="0" name="price" :value="old('price', $package->price)" required />
                            <x-input-error :messages="$errors->get('price')" class="mt-2" />
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $package->is_active))>
                        Active
                    </label>

                    <div class="flex justify-end">
                        <x-primary-button>Save changes</x-primary-button>
                    </div>
                </form>
            </div>

            @if ($services->isNotEmpty())
                <div class="bg-white shadow-sm rounded-lg p-6"
                    x-data="{
                        items: {{ \Illuminate\Support\Js::from($items->map(fn ($s) => ['service_id' => $s->id, 'quantity' => $s->pivot->quantity])) }}
                    }">
                    <h3 class="font-medium text-gray-900 mb-1">Included services</h3>
                    <p class="text-xs text-gray-500 mb-4">What a purchased instance of this package entitles the customer to redeem.</p>

                    <form method="POST" action="{{ route('packages.services', $package) }}">
                        @csrf
                        @method('PUT')

                        <div class="space-y-3">
                            <template x-for="(row, index) in items" :key="index">
                                <div class="flex flex-col sm:flex-row gap-2 sm:items-center border border-gray-100 rounded-md p-3">
                                    <select :name="`items[${index}][service_id]`" x-model="row.service_id" required
                                        class="flex-1 border-gray-300 rounded-md shadow-sm text-sm">
                                        <option value="">Select service&hellip;</option>
                                        @foreach ($services as $service)
                                            <option value="{{ $service->id }}">{{ $service->name }}</option>
                                        @endforeach
                                    </select>
                                    <input type="number" min="1" :name="`items[${index}][quantity]`" x-model="row.quantity" placeholder="Quantity" required
                                        class="w-full sm:w-32 border-gray-300 rounded-md shadow-sm text-sm">
                                    <button type="button" @click="items.splice(index, 1)" class="text-red-500 hover:text-red-700 text-sm shrink-0">Remove</button>
                                </div>
                            </template>
                        </div>

                        <button type="button" @click="items.push({ service_id: '', quantity: 1 })"
                            class="mt-3 text-sm text-indigo-600 hover:text-indigo-800">+ Add service</button>

                        <div class="flex justify-end mt-4">
                            <x-primary-button>Save contents</x-primary-button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
