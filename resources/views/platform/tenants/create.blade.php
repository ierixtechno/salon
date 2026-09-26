<x-platform-layout>
    <x-slot name="header">New tenant</x-slot>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 max-w-2xl">
        <form method="POST" action="{{ route('platform.tenants.store') }}">
            @csrf

            <div>
                <x-input-label for="business_name" value="Business name" />
                <x-text-input id="business_name" class="block mt-1 w-full" type="text" name="business_name" :value="old('business_name')" required autofocus />
                <x-input-error :messages="$errors->get('business_name')" class="mt-2" />
            </div>

            <div class="mt-4">
                <x-input-label for="subscription_plan_id" value="Package" />
                <select id="subscription_plan_id" name="subscription_plan_id" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                    <option value="">Choose a package&hellip;</option>
                    @foreach ($plans as $plan)
                        <option value="{{ $plan->id }}" @selected((string) old('subscription_plan_id') === (string) $plan->id)>
                            {{ $plan->name }} &mdash; &#8377;{{ number_format($plan->price, 0) }}/{{ $plan->billing_interval }}@if ($plan->modules->isNotEmpty()) ({{ $plan->modules->pluck('name')->implode(', ') }})@endif &middot; {{ $plan->branch_limit }} {{ \Illuminate\Support\Str::plural('branch', $plan->branch_limit) }}@if ($plan->sellsExtraBranches()) (+&#8377;{{ number_format($plan->additional_branch_price, 0) }}/extra)@endif
                        </option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500 mt-1">
                    The tenant gets this package's modules, and its quotation is created and emailed to the owner straight away. The owner can log in immediately but sees only that quotation until it's paid.
                </p>
                <x-input-error :messages="$errors->get('subscription_plan_id')" class="mt-2" />

                <x-branch-count-picker :plans="$plans" />
            </div>

            <div class="mt-4 rounded-lg border border-indigo-100 bg-indigo-50/50 p-4">
                <p class="text-sm font-medium text-gray-800">Quotation details</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-3">
                    <div>
                        <x-input-label for="quotation_amount" value="Amount override (optional)" />
                        <x-text-input id="quotation_amount" class="block mt-1 w-full" type="number" step="0.01" min="0" name="quotation_amount" :value="old('quotation_amount')" placeholder="Auto: package price + extra branches" />
                        <x-input-error :messages="$errors->get('quotation_amount')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="discount_percent" value="Discount % (optional)" />
                        <x-text-input id="discount_percent" class="block mt-1 w-full" type="number" step="0.01" min="0" max="100" name="discount_percent" :value="old('discount_percent')" placeholder="e.g. 10" />
                        <p class="text-xs text-gray-500 mt-1">Comes off the plan price before GST. Only Super Admin can grant this; use it instead of a custom amount.</p>
                        <x-input-error :messages="$errors->get('discount_percent')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="quotation_notes" value="Notes (optional)" />
                        <x-text-input id="quotation_notes" class="block mt-1 w-full" type="text" name="quotation_notes" :value="old('quotation_notes')" />
                        <x-input-error :messages="$errors->get('quotation_notes')" class="mt-2" />
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                <div>
                    <x-input-label for="billing_state" value="Billing state (for GST)" />
                    <select id="billing_state" name="billing_state" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm" required>
                        <option value="">Select&hellip;</option>
                        @foreach (config('india.states') as $state)
                            <option value="{{ $state }}" @selected(old('billing_state') === $state)>{{ $state }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-500 mt-1">
                        Determines CGST+SGST vs IGST on the quotation.
                    </p>
                    <x-input-error :messages="$errors->get('billing_state')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="gstin" value="Tenant GSTIN (optional)" />
                    <x-text-input id="gstin" class="block mt-1 w-full uppercase" type="text" name="gstin" :value="old('gstin')" maxlength="15" placeholder="e.g. 06ABCDE1234F1Z5" />
                    <p class="text-xs text-gray-500 mt-1">Shown on this tenant's invoices as the recipient GSTIN, so they can claim GST.</p>
                    <x-input-error :messages="$errors->get('gstin')" class="mt-2" />
                </div>
            </div>

            <div class="mt-6 pt-5 border-t border-gray-100">
                <div>
                    <x-input-label for="owner_name" value="Owner name" />
                    <x-text-input id="owner_name" class="block mt-1 w-full" type="text" name="owner_name" :value="old('owner_name')" required />
                    <x-input-error :messages="$errors->get('owner_name')" class="mt-2" />
                </div>

                <div class="mt-4">
                    <x-input-label for="owner_email" value="Owner email" />
                    <x-text-input id="owner_email" class="block mt-1 w-full" type="email" name="owner_email" :value="old('owner_email')" required />
                    <x-input-error :messages="$errors->get('owner_email')" class="mt-2" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                    <div>
                        <x-input-label for="owner_password" value="Temporary password" />
                        <x-text-input id="owner_password" class="block mt-1 w-full" type="password" name="owner_password" required />
                        <x-input-error :messages="$errors->get('owner_password')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="owner_password_confirmation" value="Confirm password" />
                        <x-text-input id="owner_password_confirmation" class="block mt-1 w-full" type="password" name="owner_password_confirmation" required />
                    </div>
                </div>
            </div>

            <div class="flex justify-end mt-6">
                <button type="submit" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                    Create tenant &amp; send quotation
                </button>
            </div>
        </form>
    </div>
</x-platform-layout>
