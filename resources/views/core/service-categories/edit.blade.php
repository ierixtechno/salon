<x-app-layout>
    <x-slot name="header">{{ $category->name }}</x-slot>

    <div class="py-8">
        <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('service-categories.update', $category) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label value="Module" />
                        <x-text-input class="block mt-1 w-full bg-gray-50" type="text" value="{{ $category->module->name }}" disabled />
                        <p class="text-xs text-gray-500 mt-1">Module can't be changed after creation — create a new category instead.</p>
                    </div>

                    <div>
                        <x-input-label for="name" value="Category name" />
                        <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name', $category->name)" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="sort_order" value="Sort order" />
                        <x-text-input id="sort_order" class="block mt-1 w-24" type="number" name="sort_order" min="0" :value="old('sort_order', $category->sort_order)" />
                    </div>

                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" @checked(old('is_active', $category->is_active))>
                        <span class="text-sm text-gray-700">Active</span>
                    </label>

                    <div class="flex justify-end">
                        <x-primary-button>Save</x-primary-button>
                    </div>
                </form>
            </div>

            @can('delete', $category)
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-medium text-gray-900 mb-1">Deactivate category</h3>
                    <p class="text-xs text-gray-500 mb-4">Existing services keep working — this just hides it from new-service pickers.</p>
                    <form method="POST" action="{{ route('service-categories.destroy', $category) }}" onsubmit="return confirm('Deactivate this category?')">
                        @csrf
                        @method('DELETE')
                        <x-danger-button>Deactivate</x-danger-button>
                    </form>
                </div>
            @endcan
        </div>
    </div>
</x-app-layout>
