<x-platform-layout>
    <x-slot name="header">New Quotation</x-slot>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 max-w-xl">
        <form method="POST" action="{{ route('platform.quotations.store') }}" class="space-y-4"
            x-data="{
                plans: {{ \Illuminate\Support\Js::from($plans->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'price' => $p->price])) }},
                planId: '',
                amount: '',
                get plan() { return this.plans.find(p => p.id == this.planId) },
            }"
            >
            @csrf

            <div>
                <x-input-label for="tenant_id" value="Tenant" />
                <select id="tenant_id" name="tenant_id" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                    <option value="">Select&hellip;</option>
                    @foreach ($tenants as $tenant)
                        <option value="{{ $tenant->id }}" @selected(old('tenant_id') == $tenant->id)>{{ $tenant->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('tenant_id')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="subscription_plan_id" value="Subscription plan" />
                <select id="subscription_plan_id" name="subscription_plan_id" x-model="planId" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                    <option value="">Select&hellip;</option>
                    <template x-for="p in plans" :key="p.id">
                        <option :value="p.id" x-text="p.name + ' — ₹' + p.price"></option>
                    </template>
                </select>
                <x-input-error :messages="$errors->get('subscription_plan_id')" class="mt-2" />

                <x-branch-count-picker :plans="$plans" />
            </div>

            <div>
                <x-input-label for="amount" value="Amount" />
                <x-text-input id="amount" class="block mt-1 w-full" type="number" step="0.01" min="0" name="amount" x-model="amount" placeholder="Auto" />
                <p class="text-xs text-gray-500 mt-1">Leave blank to charge the plan's price for the chosen number of branches — fill in only for a negotiated discount or custom deal.</p>
                <x-input-error :messages="$errors->get('amount')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="discount_percent" value="Discount % (optional)" />
                <x-text-input id="discount_percent" class="block mt-1 w-full" type="number" step="0.01" min="0" max="100" name="discount_percent" :value="old('discount_percent')" placeholder="e.g. 10" />
                <p class="text-xs text-gray-500 mt-1">Comes off the plan price before GST. Use it instead of a custom amount.</p>
                <x-input-error :messages="$errors->get('discount_percent')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="notes" value="Notes (optional)" />
                <textarea id="notes" name="notes" rows="2"
                    class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('notes') }}</textarea>
                <x-input-error :messages="$errors->get('notes')" class="mt-2" />
            </div>

            <div class="flex items-center justify-between mt-6 pt-5 border-t border-gray-100">
                <a href="{{ route('platform.quotations.index') }}" class="text-sm text-gray-600 hover:text-gray-900">
                    &larr; Back to quotations
                </a>
                <button type="submit" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                    Create quotation
                </button>
            </div>
        </form>
    </div>
</x-platform-layout>
