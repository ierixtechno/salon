<x-app-layout>
    <x-slot name="header">{{ $service->name }}</x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-medium text-gray-900 mb-4">Details</h3>

                <form method="POST" action="{{ route('services.update', $service) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="service_category_id" value="Category" />
                        <select id="service_category_id" name="service_category_id" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected(old('service_category_id', $service->service_category_id) == $category->id)>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Only {{ $service->module->name }} categories are shown — a service's module is fixed by its category.</p>
                        <x-input-error :messages="$errors->get('service_category_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="name" value="Service name" />
                        <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name', $service->name)" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="description" value="Description" />
                        <textarea id="description" name="description" rows="2"
                            class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('description', $service->description) }}</textarea>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="duration_minutes" value="Duration (min)" />
                            <x-text-input id="duration_minutes" class="block mt-1 w-full" type="number" name="duration_minutes" min="1" :value="old('duration_minutes', $service->duration_minutes)" required />
                            <x-input-error :messages="$errors->get('duration_minutes')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="buffer_minutes" value="Buffer after (min)" />
                            <x-text-input id="buffer_minutes" class="block mt-1 w-full" type="number" name="buffer_minutes" min="0" :value="old('buffer_minutes', $service->buffer_minutes)" />
                            <p class="text-xs text-gray-500 mt-1">Prep/cleanup time the stylist needs before their next appointment.</p>
                            <x-input-error :messages="$errors->get('buffer_minutes')" class="mt-2" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="base_price" value="Base price" />
                            <x-text-input id="base_price" class="block mt-1 w-full" type="number" step="0.01" min="0" name="base_price" :value="old('base_price', $service->base_price)" required />
                            <x-input-error :messages="$errors->get('base_price')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="tax_rate_percent" value="Tax %" />
                            <x-text-input id="tax_rate_percent" class="block mt-1 w-full" type="number" step="0.01" min="0" max="100" name="tax_rate_percent" :value="old('tax_rate_percent', $service->tax_rate_percent)" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="sac_code" value="SAC code (optional)" />
                        <x-text-input id="sac_code" class="block mt-1 w-full sm:w-48" type="text" name="sac_code" :value="old('sac_code', $service->sac_code)" placeholder="e.g. 999721" />
                        <p class="text-xs text-gray-500 mt-1">Services Accounting Code, printed on GST invoices.</p>
                        <x-input-error :messages="$errors->get('sac_code')" class="mt-2" />
                    </div>

                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" @checked(old('is_active', $service->is_active))>
                        <span class="text-sm text-gray-700">Active</span>
                    </label>

                    <div class="flex justify-end">
                        <x-primary-button>Save</x-primary-button>
                    </div>
                </form>
            </div>

            <div class="bg-white shadow-sm rounded-lg p-6"
                x-data="{
                    variants: {{ \Illuminate\Support\Js::from($service->variants->map(fn ($v) => ['id' => $v->id, 'name' => $v->name, 'price' => $v->price, 'duration_minutes' => $v->duration_minutes])) }}
                }">
                <h3 class="font-medium text-gray-900 mb-1">Variants</h3>
                <p class="text-xs text-gray-500 mb-4">Optional — e.g. different pricing for short vs. long hair. Leave price/duration blank to use the service's own.</p>

                <form method="POST" action="{{ route('services.variants', $service) }}">
                    @csrf
                    @method('PUT')

                    <div class="space-y-3">
                        <template x-for="(variant, index) in variants" :key="index">
                            <div class="flex flex-col sm:flex-row gap-2 sm:items-center border border-gray-100 rounded-md p-3">
                                <input type="hidden" :name="`variants[${index}][id]`" x-model="variant.id">
                                <input type="text" :name="`variants[${index}][name]`" x-model="variant.name" placeholder="Variant name" required
                                    class="flex-1 border-gray-300 rounded-md shadow-sm text-sm">
                                <input type="number" step="0.01" min="0" :name="`variants[${index}][price]`" x-model="variant.price" placeholder="Price override"
                                    class="w-full sm:w-36 border-gray-300 rounded-md shadow-sm text-sm">
                                <input type="number" min="1" :name="`variants[${index}][duration_minutes]`" x-model="variant.duration_minutes" placeholder="Duration override"
                                    class="w-full sm:w-40 border-gray-300 rounded-md shadow-sm text-sm">
                                <button type="button" @click="variants.splice(index, 1)" class="text-red-500 hover:text-red-700 text-sm shrink-0">Remove</button>
                            </div>
                        </template>
                    </div>

                    <button type="button" @click="variants.push({ id: null, name: '', price: '', duration_minutes: '' })"
                        class="mt-3 text-sm text-indigo-600 hover:text-indigo-800">+ Add variant</button>

                    <div class="flex justify-end mt-4">
                        <x-primary-button>Save variants</x-primary-button>
                    </div>
                </form>
            </div>

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-medium text-gray-900 mb-1">Branch availability</h3>
                <p class="text-xs text-gray-500 mb-4">Only branches with the {{ $service->module->name }} module enabled are shown.</p>

                <form method="POST" action="{{ route('services.branches', $service) }}">
                    @csrf
                    @method('PUT')

                    <div class="space-y-2">
                        @foreach ($branches as $branch)
                            @continue(! $branch->hasModuleEnabled($service->module->code))
                            @php $pivot = $branchPivots->get($branch->id)?->pivot; @endphp
                            <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4 py-2 border-b border-gray-100 last:border-0">
                                <input type="hidden" name="branches[{{ $loop->index }}][branch_id]" value="{{ $branch->id }}">
                                <div class="w-full sm:w-40 text-sm font-medium text-gray-700">{{ $branch->name }}</div>

                                <label class="flex items-center gap-2 text-sm text-gray-600">
                                    <input type="checkbox" name="branches[{{ $loop->index }}][is_available]" value="1"
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                        @checked($pivot?->is_available)>
                                    Available
                                </label>

                                <input type="number" step="0.01" min="0" name="branches[{{ $loop->index }}][price_override]"
                                    value="{{ $pivot?->price_override }}" placeholder="Price override"
                                    class="w-full sm:w-40 border-gray-300 rounded-md shadow-sm text-sm">
                            </div>
                        @endforeach
                    </div>

                    <x-input-error :messages="$errors->get('branches.*')" class="mt-2" />

                    <div class="flex justify-end mt-4">
                        <x-primary-button>Save availability</x-primary-button>
                    </div>
                </form>
            </div>

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-medium text-gray-900 mb-1">Staff capability</h3>
                <p class="text-xs text-gray-500 mb-4">Who can perform this service.</p>

                <form method="POST" action="{{ route('services.staff', $service) }}">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        @forelse ($employees as $employee)
                            <label class="flex items-center gap-2 border rounded-md px-3 py-2 cursor-pointer hover:bg-gray-50">
                                <input type="checkbox" name="user_ids[]" value="{{ $employee->user_id }}"
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                    @checked($capableEmployeeIds->contains($employee->user_id))>
                                <span class="text-sm text-gray-800">{{ $employee->user->name }}</span>
                            </label>
                        @empty
                            <p class="text-sm text-gray-500 col-span-2">No employees yet.</p>
                        @endforelse
                    </div>

                    <div class="flex justify-end mt-4">
                        <x-primary-button>Save staff</x-primary-button>
                    </div>
                </form>
            </div>

            @if ($products->isNotEmpty())
                <div class="bg-white shadow-sm rounded-lg p-6"
                    x-data="{
                        consumables: {{ \Illuminate\Support\Js::from($service->consumables->map(fn ($c) => ['product_id' => $c->product_id, 'quantity_per_use' => $c->quantity_per_use])) }}
                    }">
                    <h3 class="font-medium text-gray-900 mb-1">Product consumption</h3>
                    <p class="text-xs text-gray-500 mb-4">Products this service typically uses (e.g. 10ml shampoo per haircut) — the "record usage" button on a completed appointment deducts these from the branch's stock.</p>

                    <form method="POST" action="{{ route('services.consumables', $service) }}">
                        @csrf
                        @method('PUT')

                        <div class="space-y-3">
                            <template x-for="(row, index) in consumables" :key="index">
                                <div class="flex flex-col sm:flex-row gap-2 sm:items-center border border-gray-100 rounded-md p-3">
                                    <select :name="`consumables[${index}][product_id]`" x-model="row.product_id" required
                                        class="flex-1 border-gray-300 rounded-md shadow-sm text-sm">
                                        <option value="">Select product&hellip;</option>
                                        @foreach ($products as $product)
                                            <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->unit }})</option>
                                        @endforeach
                                    </select>
                                    <input type="number" step="0.001" min="0.001" :name="`consumables[${index}][quantity_per_use]`" x-model="row.quantity_per_use" placeholder="Qty per use" required
                                        class="w-full sm:w-40 border-gray-300 rounded-md shadow-sm text-sm">
                                    <button type="button" @click="consumables.splice(index, 1)" class="text-red-500 hover:text-red-700 text-sm shrink-0">Remove</button>
                                </div>
                            </template>
                        </div>

                        <button type="button" @click="consumables.push({ product_id: '', quantity_per_use: '' })"
                            class="mt-3 text-sm text-indigo-600 hover:text-indigo-800">+ Add product</button>

                        <div class="flex justify-end mt-4">
                            <x-primary-button>Save consumption</x-primary-button>
                        </div>
                    </form>
                </div>
            @endif

            @can('delete', $service)
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-medium text-gray-900 mb-1">Deactivate service</h3>
                    <p class="text-xs text-gray-500 mb-4">Historical data is kept — this only stops new bookings/sales of this service.</p>
                    <form method="POST" action="{{ route('services.destroy', $service) }}" onsubmit="return confirm('Deactivate this service?')">
                        @csrf
                        @method('DELETE')
                        <x-danger-button>Deactivate</x-danger-button>
                    </form>
                </div>
            @endcan
        </div>
    </div>
</x-app-layout>
