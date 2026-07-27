<x-app-layout>
    <x-slot name="header">New Product</x-slot>

    <div class="py-8">
        <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                @if ($categories->isEmpty())
                    <p class="text-sm text-gray-500">
                        You need at least one active product category first.
                        <a href="{{ route('product-categories.create') }}" class="text-indigo-600 hover:text-indigo-800">Create one &rarr;</a>
                    </p>
                @else
                    <form method="POST" action="{{ route('products.store') }}" class="space-y-4">
                        @csrf

                        <div>
                            <x-input-label for="product_category_id" value="Category" />
                            <select id="product_category_id" name="product_category_id" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" @selected(old('product_category_id') == $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('product_category_id')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="name" value="Product name" />
                            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="sku" value="SKU (optional)" />
                                <x-text-input id="sku" class="block mt-1 w-full" type="text" name="sku" :value="old('sku')" />
                                <x-input-error :messages="$errors->get('sku')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="brand" value="Brand (optional)" />
                                <x-text-input id="brand" class="block mt-1 w-full" type="text" name="brand" :value="old('brand')" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <x-input-label for="unit" value="Unit" />
                                <x-text-input id="unit" class="block mt-1 w-full" type="text" name="unit" :value="old('unit', 'unit')" placeholder="ml, g, unit..." required />
                                <x-input-error :messages="$errors->get('unit')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="cost_price" value="Cost price" />
                                <x-text-input id="cost_price" class="block mt-1 w-full" type="number" step="0.01" min="0" name="cost_price" :value="old('cost_price')" required />
                                <x-input-error :messages="$errors->get('cost_price')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="selling_price" value="Selling price" />
                                <x-text-input id="selling_price" class="block mt-1 w-full" type="number" step="0.01" min="0" name="selling_price" :value="old('selling_price')" />
                            </div>
                        </div>

                        <div>
                            <x-input-label for="reorder_level" value="Reorder level (optional)" />
                            <x-text-input id="reorder_level" class="block mt-1 w-full sm:w-48" type="number" step="0.001" min="0" name="reorder_level" :value="old('reorder_level', 0)" />
                            <p class="text-xs text-gray-500 mt-1">Flag as low stock at any branch when quantity falls to this level or below.</p>
                            <x-input-error :messages="$errors->get('reorder_level')" class="mt-2" />
                        </div>

                        <div class="flex justify-end">
                            <x-primary-button>Create product</x-primary-button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
