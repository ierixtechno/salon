<x-app-layout>
    <x-slot name="header">New Service Category</x-slot>

    <div class="py-8">
        <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('service-categories.store') }}" class="space-y-4">
                    @csrf

                    <div>
                        <x-input-label for="module_id" value="Module" />
                        <select id="module_id" name="module_id" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                            @foreach ($tenantModules as $tm)
                                <option value="{{ $tm->module->id }}" @selected(old('module_id') == $tm->module->id)>{{ $tm->module->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('module_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="name" value="Category name" />
                        <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" placeholder="e.g. Hair Treatments" required autofocus />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="sort_order" value="Sort order" />
                        <x-text-input id="sort_order" class="block mt-1 w-24" type="number" name="sort_order" min="0" :value="old('sort_order', 0)" />
                        <x-input-error :messages="$errors->get('sort_order')" class="mt-2" />
                    </div>

                    <div class="flex justify-end">
                        <x-primary-button>Create category</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
