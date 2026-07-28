<x-app-layout>
    <x-slot name="header">New Purchase Order</x-slot>

    <div class="py-8">
        <div class="px-4 sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                @if ($suppliers->isEmpty() || $products->isEmpty())
                    <p class="text-sm text-gray-500">
                        You need at least one active supplier and one active product first.
                        <a href="{{ route('suppliers.create') }}" class="text-indigo-600 hover:text-indigo-800">Add a supplier &rarr;</a>
                        &middot;
                        <a href="{{ route('products.create') }}" class="text-indigo-600 hover:text-indigo-800">Add a product &rarr;</a>
                    </p>
                @else
                    <form method="POST" action="{{ route('purchase-orders.store') }}" class="space-y-4"
                        x-data="{
                            products: {{ \Illuminate\Support\Js::from($products->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'cost_price' => $p->cost_price])) }},
                            lines: [{ product_id: '', quantity: '', unit_cost: '' }],
                            costFor(id) { return this.products.find(p => p.id == id)?.cost_price ?? ''; },
                        }">
                        @csrf

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
                                <x-input-label for="supplier_id" value="Supplier" />
                                <select id="supplier_id" name="supplier_id" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}" @selected(old('supplier_id') == $supplier->id)>{{ $supplier->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('supplier_id')" class="mt-2" />
                            </div>
                        </div>

                        <div>
                            <x-input-label for="expected_date" value="Expected date (optional)" />
                            <x-text-input id="expected_date" class="block mt-1 w-full sm:w-48" type="date" name="expected_date" :value="old('expected_date')" />
                        </div>

                        <div>
                            <x-input-label value="Items" />
                            <div class="space-y-3 mt-2">
                                <template x-for="(line, index) in lines" :key="index">
                                    <div class="flex flex-col sm:flex-row gap-2 sm:items-center border border-gray-100 rounded-md p-3">
                                        <select :name="`lines[${index}][product_id]`" x-model="line.product_id"
                                            @change="line.unit_cost = costFor(line.product_id)"
                                            class="flex-1 border-gray-300 rounded-md shadow-sm text-sm" required>
                                            <option value="">Select product&hellip;</option>
                                            <template x-for="p in products" :key="p.id">
                                                <option :value="p.id" x-text="p.name"></option>
                                            </template>
                                        </select>
                                        <input type="number" step="0.001" min="0.001" :name="`lines[${index}][quantity]`" x-model="line.quantity" placeholder="Quantity" required
                                            class="w-full sm:w-32 border-gray-300 rounded-md shadow-sm text-sm">
                                        <input type="number" step="0.01" min="0" :name="`lines[${index}][unit_cost]`" x-model="line.unit_cost" placeholder="Unit cost" required
                                            class="w-full sm:w-32 border-gray-300 rounded-md shadow-sm text-sm">
                                        <button type="button" @click="lines.splice(index, 1)" class="text-red-500 hover:text-red-700 text-sm shrink-0">Remove</button>
                                    </div>
                                </template>
                            </div>
                            <button type="button" @click="lines.push({ product_id: '', quantity: '', unit_cost: '' })"
                                class="mt-3 text-sm text-indigo-600 hover:text-indigo-800">+ Add item</button>
                            <x-input-error :messages="$errors->get('lines')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="notes" value="Notes (optional)" />
                            <textarea id="notes" name="notes" rows="2"
                                class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('notes') }}</textarea>
                        </div>

                        <div class="flex justify-end">
                            <x-primary-button>Create purchase order</x-primary-button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
