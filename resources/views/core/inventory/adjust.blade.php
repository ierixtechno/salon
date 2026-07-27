<x-app-layout>
    <x-slot name="header">Adjust Stock</x-slot>

    <div class="py-8">
        <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('inventory.adjust') }}" class="space-y-4" x-data="{ type: 'adjustment' }">
                    @csrf

                    <div>
                        <x-input-label for="branch_id" value="Branch" />
                        <select id="branch_id" name="branch_id" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                            @foreach ($branches as $b)
                                <option value="{{ $b->id }}" @selected(old('branch_id', $branch?->id) == $b->id)>{{ $b->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('branch_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="product_id" value="Product" />
                        <select id="product_id" name="product_id" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                            <option value="">Select&hellip;</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}" @selected(old('product_id') == $product->id)>{{ $product->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('product_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="type" value="Type" />
                        <select id="type" name="type" x-model="type" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                            <option value="adjustment">Adjustment (stock count correction)</option>
                            <option value="damage">Damage</option>
                            <option value="expiry">Expiry</option>
                        </select>
                        <x-input-error :messages="$errors->get('type')" class="mt-2" />
                    </div>

                    <div x-show="type === 'adjustment'">
                        <x-input-label value="Direction" />
                        <div class="flex gap-4 mt-1 text-sm">
                            <label class="flex items-center gap-1.5"><input type="radio" name="direction" value="increase" checked> Increase</label>
                            <label class="flex items-center gap-1.5"><input type="radio" name="direction" value="decrease"> Decrease</label>
                        </div>
                        <x-input-error :messages="$errors->get('direction')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="quantity" value="Quantity" />
                        <x-text-input id="quantity" class="block mt-1 w-full sm:w-48" type="number" step="0.001" min="0.001" name="quantity" :value="old('quantity')" required />
                        <x-input-error :messages="$errors->get('quantity')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="notes" value="Notes (optional)" />
                        <textarea id="notes" name="notes" rows="2"
                            class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('notes') }}</textarea>
                    </div>

                    <div class="flex justify-end">
                        <x-primary-button>Record adjustment</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
