<x-platform-layout>
    <x-slot name="header">New tenant</x-slot>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 max-w-2xl"
        x-data="{ planId: '{{ old('subscription_plan_id', '') }}' }">
        <form method="POST" action="{{ route('platform.tenants.store') }}">
            @csrf

            <div>
                <x-input-label for="business_name" value="Business name" />
                <x-text-input id="business_name" class="block mt-1 w-full" type="text" name="business_name" :value="old('business_name')" required autofocus />
                <x-input-error :messages="$errors->get('business_name')" class="mt-2" />
            </div>

            <div class="mt-4">
                <x-input-label for="subscription_plan_id" value="Subscription plan (optional)" />
                <select id="subscription_plan_id" name="subscription_plan_id" x-model="planId" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                    <option value="">No plan yet &mdash; I'll create a quotation later</option>
                    @foreach ($plans as $plan)
                        <option value="{{ $plan->id }}" @selected((string) old('subscription_plan_id') === (string) $plan->id)>
                            {{ $plan->name }} &mdash; &#8377;{{ number_format($plan->price, 0) }}/{{ $plan->billing_interval }}
                        </option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500 mt-1">
                    Pick a plan to skip the separate "create a quotation" step — this creates the tenant and its first quotation together, ready to record payment on.
                </p>
                <x-input-error :messages="$errors->get('subscription_plan_id')" class="mt-2" />
            </div>

            <div class="mt-4" x-show="!planId" x-cloak>
                <x-input-label value="Modules" />
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-1">
                    @foreach ($modules as $module)
                        <label class="flex items-center gap-2 border border-gray-200 rounded-lg px-3 py-2.5 cursor-pointer hover:bg-gray-50 has-[:checked]:border-indigo-400 has-[:checked]:bg-indigo-50 has-[:checked]:ring-1 has-[:checked]:ring-indigo-400 transition">
                            <input type="checkbox" name="modules[]" value="{{ $module->code }}"
                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                @checked(collect(old('modules', []))->contains($module->code))>
                            <span class="text-sm text-gray-800">{{ $module->name }}</span>
                        </label>
                    @endforeach
                </div>
                <p class="text-xs text-gray-500 mt-1">What to quote this lead for — not enforced until a plan is paid.</p>
                <x-input-error :messages="$errors->get('modules')" class="mt-2" />
            </div>

            <div class="mt-4 rounded-lg border border-indigo-100 bg-indigo-50/50 p-4" x-show="planId" x-cloak>
                <p class="text-sm font-medium text-gray-800">Quotation details</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-3">
                    <div>
                        <x-input-label for="quotation_amount" value="Amount override (optional)" />
                        <x-text-input id="quotation_amount" class="block mt-1 w-full" type="number" step="0.01" min="0" name="quotation_amount" :value="old('quotation_amount')" placeholder="Defaults to the plan's price" />
                        <x-input-error :messages="$errors->get('quotation_amount')" class="mt-2" />
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
                    <select id="billing_state" name="billing_state" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm" :required="planId">
                        <option value="">Select&hellip;</option>
                        @foreach (config('india.states') as $state)
                            <option value="{{ $state }}" @selected(old('billing_state') === $state)>{{ $state }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-500 mt-1">
                        Determines CGST+SGST vs IGST. <span x-show="planId" x-cloak>Required to generate the quotation.</span><span x-show="!planId" x-cloak>Can be set later.</span>
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
                    <span x-show="!planId" x-cloak>Create tenant</span>
                    <span x-show="planId" x-cloak>Create tenant &amp; generate quotation</span>
                </button>
            </div>
        </form>
    </div>
</x-platform-layout>
