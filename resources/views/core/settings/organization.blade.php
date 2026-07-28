<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Organization Settings</h2>
    </x-slot>

    <div class="py-8">
        <div class="px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-medium text-gray-900 mb-4">Business Profile</h3>

                <form method="POST" action="{{ route('settings.organization.update') }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="display_name" value="Display name" />
                        <x-text-input id="display_name" class="block mt-1 w-full" type="text" name="display_name"
                            :value="old('display_name', $profile->display_name)" required />
                        <x-input-error :messages="$errors->get('display_name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="business_type" value="Business type (optional)" />
                        <select id="business_type" name="business_type" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                            <option value="">General (Salon / Beauty / Spa)</option>
                            @foreach (\App\Domain\Core\Models\BusinessProfile::BUSINESS_TYPES as $type)
                                <option value="{{ $type }}" @selected(old('business_type', $profile->business_type) === $type)>{{ str($type)->replace('_', ' ')->headline() }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-500 mt-1">A label for how you present your business — doesn't change which services or features are available.</p>
                        <x-input-error :messages="$errors->get('business_type')" class="mt-2" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="legal_name" value="Legal name (optional)" />
                            <x-text-input id="legal_name" class="block mt-1 w-full" type="text" name="legal_name"
                                :value="old('legal_name', $profile->legal_name)" />
                        </div>
                        <div>
                            <x-input-label for="contact_phone" value="Contact phone" />
                            <x-phone-input id="contact_phone" name="contact_phone" class="block w-full" :value="old('contact_phone', $profile->contact_phone)" />
                            <x-input-error :messages="$errors->get('contact_phone')" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="contact_email" value="Contact email" />
                        <x-text-input id="contact_email" class="block mt-1 w-full" type="email" name="contact_email"
                            :value="old('contact_email', $profile->contact_email)" />
                    </div>

                    <div>
                        <x-input-label for="address" value="Address" />
                        <textarea id="address" name="address" rows="2"
                            class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('address', $profile->address) }}</textarea>
                    </div>

                    <div>
                        <x-input-label for="cancellation_policy" value="Cancellation policy" />
                        <textarea id="cancellation_policy" name="cancellation_policy" rows="4"
                            class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('cancellation_policy', $profile->cancellation_policy) }}</textarea>
                        <p class="text-xs text-gray-500 mt-1">Reference text shown to customers — not enforced by the system yet.</p>
                    </div>

                    <div class="pt-4 border-t border-gray-100">
                        <h4 class="text-sm font-medium text-gray-700 mb-1">Loyalty program</h4>
                        <p class="text-xs text-gray-500 mb-3">Leave at 0 to keep loyalty off — points are then never earned or redeemable.</p>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <x-input-label for="loyalty_points_per_100" value="Points per ₹100 spent" />
                                <x-text-input id="loyalty_points_per_100" class="block mt-1 w-full" type="number" step="0.01" min="0" name="loyalty_points_per_100" :value="old('loyalty_points_per_100', $profile->loyalty_points_per_100)" />
                                <x-input-error :messages="$errors->get('loyalty_points_per_100')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="loyalty_redemption_value" value="₹ value per point" />
                                <x-text-input id="loyalty_redemption_value" class="block mt-1 w-full" type="number" step="0.0001" min="0" name="loyalty_redemption_value" :value="old('loyalty_redemption_value', $profile->loyalty_redemption_value)" />
                                <x-input-error :messages="$errors->get('loyalty_redemption_value')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="loyalty_points_expiry_days" value="Points expiry (days, optional)" />
                                <x-text-input id="loyalty_points_expiry_days" class="block mt-1 w-full" type="number" min="1" name="loyalty_points_expiry_days" :value="old('loyalty_points_expiry_days', $profile->loyalty_points_expiry_days)" />
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <x-primary-button>Save profile</x-primary-button>
                    </div>
                </form>
            </div>

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-medium text-gray-900 mb-1">Default Business Hours</h3>
                <p class="text-xs text-gray-500 mb-4">Applies to every branch unless a branch sets its own hours.</p>

                <form method="POST" action="{{ route('settings.organization.hours') }}">
                    @csrf
                    @method('PUT')

                    <div class="space-y-2">
                        @foreach ($hours as $i => $hour)
                            @php $day = \Carbon\Carbon::create()->startOfWeek(\Carbon\Carbon::SUNDAY)->addDays($hour->day_of_week)->format('l'); @endphp
                            <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4 py-2 border-b border-gray-100 last:border-0">
                                <input type="hidden" name="hours[{{ $i }}][day_of_week]" value="{{ $hour->day_of_week }}">
                                <div class="w-full sm:w-28 text-sm font-medium text-gray-700">{{ $day }}</div>

                                <label class="flex items-center gap-2 text-sm text-gray-600">
                                    <input type="checkbox" name="hours[{{ $i }}][is_closed]" value="1"
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                        @checked($hour->is_closed) onchange="this.closest('div').querySelectorAll('input[type=time]').forEach(el => el.disabled = this.checked)">
                                    Closed
                                </label>

                                <div class="flex items-center gap-2">
                                    <input type="time" name="hours[{{ $i }}][opens_at]"
                                        value="{{ optional($hour->opens_at)->format('H:i') ?? $hour->opens_at }}"
                                        @disabled($hour->is_closed)
                                        class="border-gray-300 rounded-md shadow-sm text-sm disabled:bg-gray-100">
                                    <span class="text-gray-400 text-sm">to</span>
                                    <input type="time" name="hours[{{ $i }}][closes_at]"
                                        value="{{ optional($hour->closes_at)->format('H:i') ?? $hour->closes_at }}"
                                        @disabled($hour->is_closed)
                                        class="border-gray-300 rounded-md shadow-sm text-sm disabled:bg-gray-100">
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <x-input-error :messages="$errors->get('hours.*')" class="mt-2" />

                    <div class="flex justify-end mt-4">
                        <x-primary-button>Save hours</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
