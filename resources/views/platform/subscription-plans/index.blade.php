<x-platform-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <span>Subscription Plans</span>
            <a href="{{ route('platform.subscription-plans.create') }}" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                + New Plan
            </a>
        </div>
    </x-slot>

    @if ($plans->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-5 py-10 text-center text-gray-400">
            No subscription plans yet.
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach ($plans as $plan)
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex flex-col">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <h3 class="font-semibold text-gray-900">{{ $plan->name }}</h3>
                            <p class="text-xs text-gray-400">{{ $plan->code }}</p>
                        </div>
                        <x-platform.status-badge :status="$plan->is_active ? 'active' : 'cancelled'" />
                    </div>

                    <div class="mb-5">
                        @if ($plan->price == 0)
                            <span class="text-3xl font-bold text-gray-900">Free</span>
                        @else
                            <span class="text-3xl font-bold text-gray-900">₹{{ number_format($plan->price, 2) }}</span>
                            <span class="text-sm text-gray-500">/ {{ $plan->billing_interval === 'yearly' ? 'year' : 'month' }} + GST</span>
                        @endif
                    </div>

                    <p class="text-sm text-gray-700 mb-4"><span class="font-medium">{{ $plan->branch_limit }}</span> {{ \Illuminate\Support\Str::plural('branch', $plan->branch_limit) }} included @if ($plan->sellsExtraBranches()) &middot; <span class="font-medium">&#8377;{{ number_format($plan->additional_branch_price, 0) }}</span> per additional branch @if ($plan->max_branches) (max {{ $plan->max_branches }}) @endif @endif</p>

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

                    <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                        <span class="text-xs text-gray-400">{{ $plan->tenant_subscriptions_count }} subscribed {{ Str::plural('tenant', $plan->tenant_subscriptions_count) }}</span>
                        <a href="{{ route('platform.subscription-plans.edit', $plan) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                            Edit →
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-platform-layout>
