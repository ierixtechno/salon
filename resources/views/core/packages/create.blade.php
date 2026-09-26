<x-app-layout>
    <x-slot name="header">New Package</x-slot>

    <div class="py-8">
        <div class="px-4 sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('packages.store') }}" class="space-y-4">
                    @csrf

                    <div>
                        <x-input-label for="name" value="Package name" />
                        <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="description" value="Description (optional)" />
                        <textarea id="description" name="description" rows="2" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">{{ old('description') }}</textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="validity_days" value="Validity (days)" />
                            <x-text-input id="validity_days" class="block mt-1 w-full" type="number" min="1" name="validity_days" :value="old('validity_days', 90)" required />
                            <x-input-error :messages="$errors->get('validity_days')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="price" value="Price (before GST)" />
                            <x-text-input id="price" class="block mt-1 w-full" type="number" step="0.01" min="0" name="price" :value="old('price')" required />
                            <x-input-error :messages="$errors->get('price')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="tax_rate_percent" value="GST %" />
                            <x-text-input id="tax_rate_percent" class="block mt-1 w-full" type="number" step="0.01" min="0" max="100" name="tax_rate_percent" :value="old('tax_rate_percent', 18)" required />
                            <x-input-error :messages="$errors->get('tax_rate_percent')" class="mt-2" />
                        </div>
                    </div>

                    <p class="text-xs text-gray-500">You can add the included services once the package is created.</p>

                    <div class="flex justify-end">
                        <x-primary-button>Create package</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
