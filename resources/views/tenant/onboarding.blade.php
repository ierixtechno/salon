<x-onboarding-layout>
    <h1 class="text-xl font-semibold text-gray-900 mb-1">Set up your business</h1>
    <p class="text-sm text-gray-600 mb-6">Choose your package and create your account. We'll email you a quotation straight away — log in any time to view it and pay, and your account unlocks as soon as the payment is received.</p>

    <form method="POST" action="{{ route('onboarding.store') }}">
        @csrf

        <fieldset class="mb-6">
            <legend class="text-sm font-semibold text-gray-700 mb-3">Business</legend>

            <div>
                <x-input-label for="business_name" value="Business name" />
                <x-text-input id="business_name" class="block mt-1 w-full" type="text" name="business_name" :value="old('business_name')" required autofocus />
                <x-input-error :messages="$errors->get('business_name')" class="mt-2" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                <div>
                    <x-input-label for="billing_state" value="State (for GST)" />
                    <select id="billing_state" name="billing_state" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm" required>
                        <option value="">Select&hellip;</option>
                        @foreach (config('india.states') as $state)
                            <option value="{{ $state }}" @selected(old('billing_state') === $state)>{{ $state }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('billing_state')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="gstin" value="GSTIN (optional)" />
                    <x-text-input id="gstin" class="block mt-1 w-full uppercase" type="text" name="gstin" :value="old('gstin')" maxlength="15" placeholder="e.g. 06ABCDE1234F1Z5" />
                    <p class="text-xs text-gray-500 mt-1">If you're GST-registered, we'll show this on your invoices so you can claim GST.</p>
                    <x-input-error :messages="$errors->get('gstin')" class="mt-2" />
                </div>
            </div>
        </fieldset>

        <fieldset class="mb-6">
            <legend class="text-sm font-semibold text-gray-700 mb-1">Choose your package</legend>
            <p class="text-xs text-gray-500 mb-3">Prices are per billing period, plus GST. You can move to a different package later.</p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @forelse ($plans as $plan)
                    <label class="relative flex flex-col gap-1 rounded-lg border border-gray-200 px-4 py-3 cursor-pointer hover:bg-gray-50 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50 has-[:checked]:ring-1 has-[:checked]:ring-indigo-500 transition">
                        <span class="flex items-start justify-between gap-3">
                            <span class="flex items-center gap-2">
                                <input type="radio" name="subscription_plan_id" value="{{ $plan->id }}" required
                                    class="border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    @checked((string) old('subscription_plan_id') === (string) $plan->id)>
                                <span class="text-sm font-semibold text-gray-900">{{ $plan->name }}</span>
                            </span>
                            <span class="text-right shrink-0">
                                @if ($plan->hasPromo())
                                    <span class="block text-xs text-gray-400 line-through">&#8377;{{ number_format($plan->compare_at_price, 0) }}</span>
                                @endif
                                <span class="block text-sm font-semibold text-gray-900">&#8377;{{ number_format($plan->price, 0) }}@if ($plan->hasPromo()) <span class="ml-1 rounded bg-green-100 px-1.5 py-0.5 text-[10px] font-semibold text-green-700">{{ $plan->promoPercent() }}% OFF</span>@endif</span>
                                <span class="block text-xs text-gray-500">/{{ $plan->billing_interval === 'yearly' ? 'year' : 'month' }} + GST</span>
                            </span>
                        </span>
                        @if ($plan->sellsExtraBranches())
                            <span class="pl-6 text-xs text-indigo-700">+ &#8377;{{ number_format($plan->additional_branch_price, 0) }} per additional branch</span>
                        @endif
                        @if ($plan->modules->isNotEmpty())
                            <span class="pl-6 text-xs text-gray-600">Includes: {{ $plan->modules->pluck('name')->implode(', ') }} &middot; {{ $plan->branch_limit }} {{ \Illuminate\Support\Str::plural('branch', $plan->branch_limit) }}</span>
                        @endif
                    </label>
                @empty
                    <p class="text-sm text-gray-500 sm:col-span-2">No packages are available right now — please contact us.</p>
                @endforelse
            </div>
            <x-input-error :messages="$errors->get('subscription_plan_id')" class="mt-2" />

            <x-branch-count-picker :plans="$plans" />
        </fieldset>

        <fieldset class="mb-2">
            <legend class="text-sm font-semibold text-gray-700 mb-3">Your account</legend>

            <div>
                <x-input-label for="owner_name" value="Your name" />
                <x-text-input id="owner_name" class="block mt-1 w-full" type="text" name="owner_name" :value="old('owner_name')" required />
                <x-input-error :messages="$errors->get('owner_name')" class="mt-2" />
            </div>

            <div class="mt-4">
                <x-input-label for="owner_email" value="Email" />
                <x-text-input id="owner_email" class="block mt-1 w-full" type="email" name="owner_email" :value="old('owner_email')" required autocomplete="username" />
                <x-input-error :messages="$errors->get('owner_email')" class="mt-2" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                <div>
                    <x-input-label for="owner_password" value="Password" />
                    <x-text-input id="owner_password" class="block mt-1 w-full" type="password" name="owner_password" required autocomplete="new-password" />
                    <x-input-error :messages="$errors->get('owner_password')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="owner_password_confirmation" value="Confirm password" />
                    <x-text-input id="owner_password_confirmation" class="block mt-1 w-full" type="password" name="owner_password_confirmation" required autocomplete="new-password" />
                </div>
            </div>
        </fieldset>

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mt-6">
            <a class="underline text-sm text-gray-600 hover:text-gray-900" href="{{ route('login') }}">
                Already have an account?
            </a>

            <x-primary-button class="w-full sm:w-auto justify-center">
                Create account
            </x-primary-button>
        </div>
    </form>
</x-onboarding-layout>
