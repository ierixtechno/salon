<x-platform-layout>
    <x-slot name="header">Edit Plan — {{ $plan->name }}</x-slot>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 max-w-2xl">
        <form method="POST" action="{{ route('platform.subscription-plans.update', $plan) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <x-input-label for="name" value="Plan name" />
                <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name', $plan->name)" required autofocus />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="price" value="Price" />
                    <x-text-input id="price" class="block mt-1 w-full" type="number" step="0.01" min="0" name="price" :value="old('price', $plan->price)" required />
                    <x-input-error :messages="$errors->get('price')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="billing_interval" value="Billing interval" />
                    <select id="billing_interval" name="billing_interval" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                        @foreach (['trial', 'monthly', 'yearly'] as $interval)
                            <option value="{{ $interval }}" @selected(old('billing_interval', $plan->billing_interval) === $interval)>{{ ucfirst($interval) }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('billing_interval')" class="mt-2" />
                </div>
            </div>
            @include('platform.subscription-plans._branch-pricing')

            <div class="mt-4">
                <x-input-label value="Features included" />
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-1">
                    @foreach ($features as $feature)
                        <label class="flex items-start gap-2 border border-gray-200 rounded-lg px-3 py-2.5 cursor-pointer hover:bg-gray-50 has-[:checked]:border-indigo-400 has-[:checked]:bg-indigo-50">
                            <input type="checkbox" name="features[]" value="{{ $feature->code }}"
                                class="mt-0.5 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                @checked(in_array($feature->code, old('features', $selectedFeatureCodes)))>
                            <span>
                                <span class="block text-sm text-gray-800">{{ $feature->name }}</span>
                                @if ($feature->description)
                                    <span class="block text-xs text-gray-500">{{ $feature->description }}</span>
                                @endif
                            </span>
                        </label>
                    @endforeach
                </div>
                <x-input-error :messages="$errors->get('features')" class="mt-2" />
            </div>

            <div class="mt-4">
                <x-input-label value="Modules included" />
                <p class="text-xs text-gray-500 mb-1">Paying an invoice for this plan sets the tenant's enabled modules to exactly this set.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-1">
                    @foreach ($modules as $module)
                        <label class="flex items-start gap-2 border border-gray-200 rounded-lg px-3 py-2.5 cursor-pointer hover:bg-gray-50 has-[:checked]:border-indigo-400 has-[:checked]:bg-indigo-50">
                            <input type="checkbox" name="modules[]" value="{{ $module->code }}"
                                class="mt-0.5 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                @checked(in_array($module->code, old('modules', $selectedModuleCodes)))>
                            <span class="block text-sm text-gray-800">{{ $module->name }}</span>
                        </label>
                    @endforeach
                </div>
                <x-input-error :messages="$errors->get('modules')" class="mt-2" />
            </div>

            <label class="flex items-center gap-2 text-sm text-gray-700 mt-4">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $plan->is_active))>
                Active — available for new tenant subscriptions
            </label>

            <div class="flex items-center justify-between mt-6 pt-5 border-t border-gray-100">
                <a href="{{ route('platform.subscription-plans.index') }}" class="text-sm text-gray-600 hover:text-gray-900">
                    &larr; Back to plans
                </a>
                <button type="submit" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                    Save changes
                </button>
            </div>
        </form>
    </div>
</x-platform-layout>
