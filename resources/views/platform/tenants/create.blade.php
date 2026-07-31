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
                <x-input-error :messages="$errors->get('modules')" class="mt-2" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                <div>
                    <x-input-label for="billing_state" value="Billing state (for GST, optional)" />
                    <select id="billing_state" name="billing_state" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                        <option value="">Select&hellip;</option>
                        @foreach (config('india.states') as $state)
                            <option value="{{ $state }}" @selected(old('billing_state') === $state)>{{ $state }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Determines CGST+SGST vs IGST. Can be set later.</p>
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
                    Create tenant
                </button>
            </div>
        </form>
    </div>
</x-platform-layout>
