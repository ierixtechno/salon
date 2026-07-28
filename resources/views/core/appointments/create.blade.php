<x-app-layout>
    <x-slot name="header">New Appointment</x-slot>

    <div class="py-8">
        <div class="px-4 sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                @if ($branches->isEmpty())
                    <p class="text-sm text-gray-500">You don't have access to any branch yet.</p>
                @elseif ($categories->flatMap->services->isEmpty())
                    <p class="text-sm text-gray-500">No active services yet. <a href="{{ route('services.create') }}" class="text-indigo-600 hover:text-indigo-800">Create one &rarr;</a></p>
                @else
                    <form method="POST" action="{{ route('appointments.store') }}" class="space-y-4"
                        x-data="{
                            categories: {{ \Illuminate\Support\Js::from($categories->map(fn ($c) => [
                                'id' => $c->id,
                                'name' => $c->name,
                                'services' => $c->services->map(fn ($s) => [
                                    'id' => $s->id,
                                    'name' => $s->name,
                                    'duration_minutes' => $s->duration_minutes,
                                    'base_price' => $s->base_price,
                                    'variants' => $s->variants->map(fn ($v) => ['id' => $v->id, 'name' => $v->name]),
                                    'employees' => $s->capableEmployees->map(fn ($e) => ['id' => $e->id, 'name' => $e->name]),
                                ]),
                            ])) }},
                            categoryId: '', serviceId: '{{ old('service_id', $prefill['service_id'] ?? '') }}', isWalkIn: false,
                            get services() { return this.categories.find(c => c.id == this.categoryId)?.services ?? [] },
                            get service() { return this.services.find(s => s.id == this.serviceId) },
                            init() {
                                if (this.serviceId) {
                                    const owner = this.categories.find(c => c.services.some(s => s.id == this.serviceId));
                                    if (owner) this.categoryId = owner.id;
                                }
                            },
                        }">
                        @csrf
                        @if (! empty($prefill['waitlist_entry_id']))
                            <input type="hidden" name="waitlist_entry_id" value="{{ $prefill['waitlist_entry_id'] }}">
                        @endif

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
                            <x-input-label for="customer_id" value="Customer" />
                            <select id="customer_id" name="customer_id" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                <option value="">Select a customer&hellip;</option>
                                @foreach ($customers as $customer)
                                    <option value="{{ $customer->id }}" @selected(old('customer_id', $prefill['customer_id'] ?? null) == $customer->id)>{{ $customer->name }} @if($customer->phone) ({{ $customer->phone }}) @endif</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('customer_id')" class="mt-2" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="category_id" value="Category" />
                                <select id="category_id" x-model="categoryId" @change="serviceId = ''" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                    <option value="">Select&hellip;</option>
                                    <template x-for="c in categories" :key="c.id">
                                        <option :value="c.id" x-text="c.name"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <x-input-label for="service_id" value="Service" />
                                <select id="service_id" name="service_id" x-model="serviceId" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                    <option value="">Select&hellip;</option>
                                    <template x-for="s in services" :key="s.id">
                                        <option :value="s.id" x-text="s.name + ' (' + s.duration_minutes + ' min)'"></option>
                                    </template>
                                </select>
                                <x-input-error :messages="$errors->get('service_id')" class="mt-2" />
                            </div>
                        </div>

                        <div x-show="service?.variants?.length">
                            <x-input-label for="service_variant_id" value="Variant (optional)" />
                            <select id="service_variant_id" name="service_variant_id" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                <option value="">Standard</option>
                                <template x-for="v in (service?.variants ?? [])" :key="v.id">
                                    <option :value="v.id" x-text="v.name"></option>
                                </template>
                            </select>
                            <x-input-error :messages="$errors->get('service_variant_id')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="user_id" value="Staff" />
                            <select id="user_id" name="user_id" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                <option value="">Select&hellip;</option>
                                <template x-for="e in (service?.employees ?? [])" :key="e.id">
                                    <option :value="e.id" x-text="e.name"></option>
                                </template>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Only staff capable of the selected service are shown.</p>
                            <x-input-error :messages="$errors->get('user_id')" class="mt-2" />
                        </div>

                        @if ($resources->isNotEmpty())
                            <div>
                                <x-input-label for="resource_id" value="Room/Chair/Station (optional)" />
                                <select id="resource_id" name="resource_id" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                    <option value="">None</option>
                                    @foreach ($resources as $resource)
                                        <option value="{{ $resource->id }}" @selected(old('resource_id') == $resource->id)>{{ $resource->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('resource_id')" class="mt-2" />
                            </div>
                        @endif

                        <div>
                            <x-input-label for="starts_at" value="Date & time" />
                            <x-text-input id="starts_at" class="block mt-1 w-full" type="datetime-local" name="starts_at" :value="old('starts_at')" required />
                            <x-input-error :messages="$errors->get('starts_at')" class="mt-2" />
                        </div>

                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="source" value="walk_in" x-model="isWalkIn" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                            <span class="text-sm text-gray-700">Walk-in (customer is here now)</span>
                        </label>

                        <div>
                            <x-input-label for="notes" value="Notes" />
                            <textarea id="notes" name="notes" rows="2"
                                class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('notes') }}</textarea>
                            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                        </div>

                        <div class="flex justify-end">
                            <x-primary-button>Book appointment</x-primary-button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
