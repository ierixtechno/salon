<x-app-layout>
    <x-slot name="header">Edit Membership Plan</x-slot>

    <div class="py-8">
        <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('membership-plans.update', $plan) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="name" value="Plan name" />
                        <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name', $plan->name)" required autofocus />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="description" value="Description (optional)" />
                        <textarea id="description" name="description" rows="2" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">{{ old('description', $plan->description) }}</textarea>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="validity_days" value="Validity (days)" />
                            <x-text-input id="validity_days" class="block mt-1 w-full" type="number" min="1" name="validity_days" :value="old('validity_days', $plan->validity_days)" required />
                            <x-input-error :messages="$errors->get('validity_days')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="price" value="Price" />
                            <x-text-input id="price" class="block mt-1 w-full" type="number" step="0.01" min="0" name="price" :value="old('price', $plan->price)" required />
                            <x-input-error :messages="$errors->get('price')" class="mt-2" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="discount_percent" value="Discount %" />
                            <x-text-input id="discount_percent" class="block mt-1 w-full" type="number" step="0.01" min="0" max="100" name="discount_percent" :value="old('discount_percent', $plan->discount_percent)" required />
                            <x-input-error :messages="$errors->get('discount_percent')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="usage_limit" value="Usage limit (optional)" />
                            <x-text-input id="usage_limit" class="block mt-1 w-full" type="number" min="1" name="usage_limit" :value="old('usage_limit', $plan->usage_limit)" placeholder="Unlimited" />
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $plan->is_active))>
                        Active
                    </label>

                    <div class="flex justify-end">
                        <x-primary-button>Save changes</x-primary-button>
                    </div>
                </form>
            </div>

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-medium text-gray-900 mb-1">Applicability</h3>
                <p class="text-xs text-gray-500 mb-4">Leave a group entirely unchecked to apply to all of that dimension. Each dimension narrows independently.</p>

                <form method="POST" action="{{ route('membership-plans.applicability', $plan) }}" class="space-y-5">
                    @csrf
                    @method('PUT')

                    <div>
                        <p class="text-sm font-medium text-gray-700 mb-2">Modules</p>
                        <div class="flex flex-wrap gap-3">
                            @foreach ($modules as $module)
                                <label class="flex items-center gap-1.5 text-sm text-gray-700">
                                    <input type="checkbox" name="module_ids[]" value="{{ $module->id }}" @checked(in_array($module->id, $selectedModuleIds))>
                                    {{ $module->name }}
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <p class="text-sm font-medium text-gray-700 mb-2">Branches</p>
                        <div class="flex flex-wrap gap-3">
                            @foreach ($branches as $branch)
                                <label class="flex items-center gap-1.5 text-sm text-gray-700">
                                    <input type="checkbox" name="branch_ids[]" value="{{ $branch->id }}" @checked(in_array($branch->id, $selectedBranchIds))>
                                    {{ $branch->name }}
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <p class="text-sm font-medium text-gray-700 mb-2">Services</p>
                        <div class="flex flex-wrap gap-3 max-h-48 overflow-y-auto">
                            @foreach ($services as $service)
                                <label class="flex items-center gap-1.5 text-sm text-gray-700">
                                    <input type="checkbox" name="service_ids[]" value="{{ $service->id }}" @checked(in_array($service->id, $selectedServiceIds))>
                                    {{ $service->name }}
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <x-primary-button>Save applicability</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
