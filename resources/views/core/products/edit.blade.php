<x-app-layout>
    <x-slot name="header">{{ $product->name }}</x-slot>

    <div class="py-8">
        <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('products.update', $product) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="product_category_id" value="Category" />
                        <select id="product_category_id" name="product_category_id" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected(old('product_category_id', $product->product_category_id) == $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('product_category_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="name" value="Product name" />
                        <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name', $product->name)" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="sku" value="SKU" />
                            <x-text-input id="sku" class="block mt-1 w-full" type="text" name="sku" :value="old('sku', $product->sku)" />
                            <x-input-error :messages="$errors->get('sku')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="brand" value="Brand" />
                            <x-text-input id="brand" class="block mt-1 w-full" type="text" name="brand" :value="old('brand', $product->brand)" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <x-input-label for="unit" value="Unit" />
                            <x-text-input id="unit" class="block mt-1 w-full" type="text" name="unit" :value="old('unit', $product->unit)" required />
                            <x-input-error :messages="$errors->get('unit')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="cost_price" value="Cost price" />
                            <x-text-input id="cost_price" class="block mt-1 w-full" type="number" step="0.01" min="0" name="cost_price" :value="old('cost_price', $product->cost_price)" required />
                            <x-input-error :messages="$errors->get('cost_price')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="selling_price" value="Selling price" />
                            <x-text-input id="selling_price" class="block mt-1 w-full" type="number" step="0.01" min="0" name="selling_price" :value="old('selling_price', $product->selling_price)" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="reorder_level" value="Reorder level" />
                        <x-text-input id="reorder_level" class="block mt-1 w-full sm:w-48" type="number" step="0.001" min="0" name="reorder_level" :value="old('reorder_level', $product->reorder_level)" />
                        <x-input-error :messages="$errors->get('reorder_level')" class="mt-2" />
                    </div>

                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" @checked(old('is_active', $product->is_active))>
                        <span class="text-sm text-gray-700">Active</span>
                    </label>

                    <div class="flex justify-end">
                        <x-primary-button>Save</x-primary-button>
                    </div>
                </form>
            </div>

            @can('delete', $product)
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-medium text-gray-900 mb-1">Deactivate product</h3>
                    <p class="text-xs text-gray-500 mb-4">Historical stock movements are kept — this only stops new purchasing/consumption of this product.</p>
                    <form method="POST" action="{{ route('products.destroy', $product) }}" onsubmit="return confirm('Deactivate this product?')">
                        @csrf
                        @method('DELETE')
                        <x-danger-button>Deactivate</x-danger-button>
                    </form>
                </div>
            @endcan
        </div>
    </div>
</x-app-layout>
