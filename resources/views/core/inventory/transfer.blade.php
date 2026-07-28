<x-app-layout>
    <x-slot name="header">Transfer Stock</x-slot>

    <div class="py-8">
        <div class="px-4 sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                @if ($branches->count() < 2)
                    <p class="text-sm text-gray-500">You need at least two branches to transfer stock between them.</p>
                @else
                    <form method="POST" action="{{ route('inventory.transfer') }}" class="space-y-4"
                        x-data="{ lines: [{ product_id: '', quantity: '' }] }">
                        @csrf

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="from_branch_id" value="From branch" />
                                <select id="from_branch_id" name="from_branch_id" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                    @foreach ($branches as $b)
                                        <option value="{{ $b->id }}" @selected(old('from_branch_id', $branch?->id) == $b->id)>{{ $b->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('from_branch_id')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="to_branch_id" value="To branch" />
                                <select id="to_branch_id" name="to_branch_id" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                    @foreach ($branches as $b)
                                        <option value="{{ $b->id }}" @selected(old('to_branch_id') == $b->id)>{{ $b->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('to_branch_id')" class="mt-2" />
                            </div>
                        </div>

                        <div>
                            <x-input-label value="Items" />
                            <div class="space-y-3 mt-2">
                                <template x-for="(line, index) in lines" :key="index">
                                    <div class="flex flex-col sm:flex-row gap-2 sm:items-center border border-gray-100 rounded-md p-3">
                                        <select :name="`lines[${index}][product_id]`" x-model="line.product_id"
                                            class="flex-1 border-gray-300 rounded-md shadow-sm text-sm" required>
                                            <option value="">Select product&hellip;</option>
                                            @foreach ($products as $product)
                                                <option value="{{ $product->id }}">{{ $product->name }}</option>
                                            @endforeach
                                        </select>
                                        <input type="number" step="0.001" min="0.001" :name="`lines[${index}][quantity]`" x-model="line.quantity" placeholder="Quantity" required
                                            class="w-full sm:w-32 border-gray-300 rounded-md shadow-sm text-sm">
                                        <button type="button" @click="lines.splice(index, 1)" class="text-red-500 hover:text-red-700 text-sm shrink-0">Remove</button>
                                    </div>
                                </template>
                            </div>
                            <button type="button" @click="lines.push({ product_id: '', quantity: '' })"
                                class="mt-3 text-sm text-indigo-600 hover:text-indigo-800">+ Add item</button>
                            <x-input-error :messages="$errors->get('lines')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="notes" value="Notes (optional)" />
                            <textarea id="notes" name="notes" rows="2"
                                class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('notes') }}</textarea>
                        </div>

                        <div class="flex justify-end">
                            <x-primary-button>Transfer stock</x-primary-button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
