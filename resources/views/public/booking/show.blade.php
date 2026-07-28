<x-public-layout :tenant="$tenant">
    @if (session('status'))
        <div class="mb-6 rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
            {{ session('status') }}
        </div>
    @endif

    @if ($branches->isEmpty())
        <div class="bg-white shadow-sm rounded-lg p-6 text-sm text-gray-500">
            Online booking isn't available right now — please contact us directly.
        </div>
    @else
        <div class="bg-white shadow-sm rounded-lg p-6"
            x-data="{
                branches: {{ \Illuminate\Support\Js::from($branchTree) }},
                branchId: '',
                serviceId: '',
                variantId: '',
                date: '{{ $minDate }}',
                minDate: '{{ $minDate }}',
                maxDate: '{{ $maxDate }}',
                slots: [],
                selectedSlot: '',
                loadingSlots: false,
                get branch() { return this.branches.find(b => b.id == this.branchId) },
                get services() { return this.branch?.services ?? [] },
                get service() { return this.services.find(s => s.id == this.serviceId) },
                get variant() { return this.service?.variants?.find(v => v.id == this.variantId) },
                get displayPrice() { return this.variant?.price ?? this.service?.price },
                get displayDuration() { return this.variant?.duration_minutes ?? this.service?.duration_minutes },
                resetFromBranch() { this.serviceId = ''; this.variantId = ''; this.slots = []; this.selectedSlot = ''; },
                resetFromService() { this.variantId = ''; this.slots = []; this.selectedSlot = ''; },
                async fetchSlots() {
                    this.selectedSlot = '';
                    this.slots = [];
                    if (! this.branchId || ! this.serviceId || ! this.date) return;
                    this.loadingSlots = true;
                    const params = new URLSearchParams({ branch_id: this.branchId, service_id: this.serviceId, date: this.date });
                    if (this.variantId) params.set('service_variant_id', this.variantId);
                    try {
                        const res = await fetch('{{ route('public.booking.slots', $tenant->slug) }}?' + params.toString());
                        const data = await res.json();
                        this.slots = data.slots ?? [];
                    } finally {
                        this.loadingSlots = false;
                    }
                },
            }"
            x-init="$watch('date', () => fetchSlots())">

            <h2 class="text-base font-semibold text-gray-900 mb-4">1. Choose a branch</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-6">
                @foreach ($branches as $b)
                    <label class="flex items-center gap-2 border rounded-md px-3 py-2 cursor-pointer hover:bg-gray-50" :class="branchId == {{ $b->id }} ? 'border-indigo-500 ring-1 ring-indigo-500' : 'border-gray-200'">
                        <input type="radio" name="branch_id" value="{{ $b->id }}" x-model="branchId" @change="resetFromBranch()" class="text-indigo-600">
                        <span class="text-sm text-gray-800">{{ $b->name }}</span>
                    </label>
                @endforeach
            </div>

            <template x-if="branchId && services.length === 0">
                <p class="text-sm text-gray-500 mb-6">No services are currently bookable at this branch.</p>
            </template>

            <template x-if="branchId && services.length > 0">
                <div>
                    <h2 class="text-base font-semibold text-gray-900 mb-4">2. Choose a service</h2>
                    <select x-model="serviceId" @change="resetFromService(); fetchSlots()" class="block w-full border-gray-300 rounded-md shadow-sm mb-2">
                        <option value="">Select&hellip;</option>
                        <template x-for="s in services" :key="s.id">
                            <option :value="s.id" x-text="s.category + ' — ' + s.name + ' (' + s.duration_minutes + ' min)'"></option>
                        </template>
                    </select>

                    <template x-if="service?.variants?.length">
                        <select x-model="variantId" @change="fetchSlots()" class="block w-full border-gray-300 rounded-md shadow-sm mb-2">
                            <option value="">Standard</option>
                            <template x-for="v in service.variants" :key="v.id">
                                <option :value="v.id" x-text="v.name"></option>
                            </template>
                        </select>
                    </template>

                    <p class="text-sm text-gray-600 mb-6" x-show="service">
                        Duration: <span x-text="displayDuration"></span> min &middot; Price: {{ current_tenant()->currency }} <span x-text="displayPrice"></span>
                    </p>
                </div>
            </template>

            <template x-if="serviceId">
                <div>
                    <h2 class="text-base font-semibold text-gray-900 mb-4">3. Choose a date &amp; time</h2>
                    <input type="date" x-model="date" :min="minDate" :max="maxDate" class="border-gray-300 rounded-md shadow-sm mb-4">

                    <div x-show="loadingSlots" class="text-sm text-gray-500">Checking availability&hellip;</div>
                    <div x-show="! loadingSlots && slots.length === 0" class="text-sm text-gray-500 mb-6">No times available on this date — try another day.</div>

                    <div class="grid grid-cols-3 sm:grid-cols-4 gap-2 mb-6" x-show="! loadingSlots && slots.length > 0">
                        <template x-for="slot in slots" :key="slot.iso">
                            <button type="button" @click="selectedSlot = slot.iso"
                                class="px-3 py-2 rounded-md text-sm border"
                                :class="selectedSlot === slot.iso ? 'bg-gray-800 text-white border-gray-800' : 'border-gray-200 text-gray-700 hover:bg-gray-50'"
                                x-text="slot.label"></button>
                        </template>
                    </div>
                </div>
            </template>

            <template x-if="selectedSlot">
                <form method="POST" action="{{ route('public.booking.store', $tenant->slug) }}" class="space-y-4 border-t border-gray-100 pt-6">
                    @csrf
                    <h2 class="text-base font-semibold text-gray-900">4. Your details</h2>

                    <input type="hidden" name="branch_id" :value="branchId">
                    <input type="hidden" name="service_id" :value="serviceId">
                    <input type="hidden" name="service_variant_id" :value="variantId">
                    <input type="hidden" name="starts_at" :value="selectedSlot">

                    <!-- Honeypot: real visitors never see or fill this. -->
                    <div class="absolute -left-[9999px]" aria-hidden="true">
                        <label for="website">Website</label>
                        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <div>
                        <x-input-label for="name" value="Full name" />
                        <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="phone" value="Phone" />
                            <x-text-input id="phone" class="block mt-1 w-full" type="tel" name="phone" :value="old('phone')" required />
                            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="email" value="Email (optional)" />
                            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" />
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>
                    </div>

                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="marketing_consent" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        <span class="text-sm text-gray-700">Send me offers and updates</span>
                    </label>

                    <x-input-error :messages="$errors->get('starts_at')" class="mt-2" />

                    <div class="flex justify-end">
                        <x-primary-button>Confirm booking</x-primary-button>
                    </div>
                </form>
            </template>
        </div>
    @endif
</x-public-layout>
