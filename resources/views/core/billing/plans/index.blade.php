<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Plans</h2>
    </x-slot>

    <div class="py-8">
        <div class="px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-6 rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            @php
                $currentPlan = $currentSubscription?->plan;
                $isActive = $currentSubscription && $currentSubscription->ends_at && now()->lte($currentSubscription->ends_at);
            @endphp

            @if (! $isActive)
                <div class="mb-6 rounded-md bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
                    You don't have an active subscription to upgrade from right now.
                    @can('tenant.billing.manage')
                        Check your <a href="{{ route('billing.quotations.index') }}" class="font-medium underline">Quotations</a> for a pending invoice.
                    @endcan
                </div>
            @endif

            @if ($isActive && $currentPlan?->sellsExtraBranches())
                @php
                    $ownedBranches = $currentSubscription->currentBranchCount();
                    $canAdd = $currentPlan->maxBranches() - $ownedBranches;
                @endphp
                <div class="mb-6 bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-gray-900">Need more branches?</h3>
                    <p class="text-sm text-gray-600 mt-1">
                        You have {{ $ownedBranches }} {{ \Illuminate\Support\Str::plural('branch', $ownedBranches) }} on {{ $currentPlan->name }}.
                        Each additional branch is &#8377;{{ number_format($currentPlan->additional_branch_price, 0) }} per {{ $currentPlan->billing_interval === 'yearly' ? 'year' : 'month' }} + GST,
                        charged pro rata for the rest of your current billing period.
                    </p>
                    @if ($canAdd > 0)
                        <form method="POST" action="{{ route('billing.branches.add') }}" class="mt-4 flex items-end gap-3">
                            @csrf
                            <div>
                                <label for="additional" class="block text-xs text-gray-500 mb-1">Branches to add</label>
                                <input id="additional" name="additional" type="number" min="1" max="{{ $canAdd }}" value="1" required class="w-28 border-gray-300 rounded-md shadow-sm text-sm">
                            </div>
                            <button type="submit" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Get quotation</button>
                        </form>
                        @error('additional')<p class="text-sm text-red-600 mt-2">{{ $message }}</p>@enderror
                    @else
                        <p class="text-sm text-gray-500 mt-3">You are at the maximum for this plan.</p>
                    @endif
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach ($plans as $plan)
                    @php
                        $isCurrent = $currentPlan && $currentPlan->id === $plan->id;
                        $canUpgrade = $isActive && ! $isCurrent
                            && $plan->billing_interval === $currentPlan?->billing_interval
                            && (float) $plan->price > (float) $currentPlan?->price;
                    @endphp
                    <div class="bg-white shadow-sm rounded-lg p-6 flex flex-col {{ $isCurrent ? 'ring-2 ring-indigo-500' : '' }}">
                        <div class="flex items-start justify-between mb-4">
                            <h3 class="font-semibold text-gray-900">{{ $plan->name }}</h3>
                            @if ($isCurrent)
                                <span class="inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700">
                                    Current Plan
                                </span>
                            @endif
                        </div>

                        <div class="mb-5">
                            <x-plan-price :plan="$plan" />
                        </div>

                        <p class="text-sm text-gray-700 mb-4"><span class="font-medium">{{ $plan->branch_limit }}</span> {{ \Illuminate\Support\Str::plural('branch', $plan->branch_limit) }} included @if ($plan->sellsExtraBranches()) &middot; <span class="font-medium">&#8377;{{ number_format($plan->additional_branch_price, 0) }}</span> per additional branch @if ($plan->max_branches) (max {{ $plan->max_branches }}) @endif @endif &middot; {{ $plan->usersLabel() }}</p>

                        <div class="mb-5">
                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-2">Modules included</p>
                            @if ($plan->modules->isEmpty())
                                <p class="text-sm text-gray-400">None</p>
                            @else
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($plan->modules as $module)
                                        <span class="inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700">
                                            {{ $module->name }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div class="mb-6 flex-1">
                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-2">Features included</p>
                            @if ($plan->features->isEmpty())
                                <p class="text-sm text-gray-400">None</p>
                            @else
                                <ul class="space-y-1.5">
                                    @foreach ($plan->features as $feature)
                                        <li class="flex items-start gap-2 text-sm text-gray-700">
                                            <svg class="h-4 w-4 shrink-0 text-green-600 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                            </svg>
                                            {{ $feature->name }}
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>

                        <div class="pt-4 border-t border-gray-100">
                            @if ($isCurrent)
                                <span class="block text-center text-sm text-gray-400">You're on this plan</span>
                            @elseif ($canUpgrade)
                                <form method="POST" action="{{ route('billing.plans.upgrade', $plan) }}" onsubmit="return confirm('Upgrade to {{ $plan->name }}? You\'ll be charged a prorated amount for the rest of your current billing cycle, and your renewal date stays the same.');">
                                    @csrf
                                    <button type="submit" class="w-full inline-flex items-center justify-center rounded-md bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                                        Upgrade
                                    </button>
                                </form>
                            @else
                                <span class="block text-center text-sm text-gray-400">Not available for upgrade</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
