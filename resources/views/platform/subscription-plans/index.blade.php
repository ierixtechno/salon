<x-platform-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <span>Subscription Plans</span>
            <a href="{{ route('platform.subscription-plans.create') }}" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                + New Plan
            </a>
        </div>
    </x-slot>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-100">
                    <tr>
                        <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Plan</th>
                        <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Price</th>
                        <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Billing</th>
                        <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Modules</th>
                        <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Features</th>
                        <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Subscribed tenants</th>
                        <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Status</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($plans as $plan)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3">
                                <a href="{{ route('platform.subscription-plans.edit', $plan) }}" class="font-medium text-gray-900 hover:text-indigo-600">
                                    {{ $plan->name }}
                                </a>
                                <div class="text-xs text-gray-400">{{ $plan->code }}</div>
                            </td>
                            <td class="px-5 py-3 text-gray-600">{{ $plan->price == 0 ? 'Free' : '₹'.number_format($plan->price, 2) }}</td>
                            <td class="px-5 py-3 text-gray-600 capitalize">{{ $plan->billing_interval }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $plan->modules_count }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $plan->features_count }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $plan->tenant_subscriptions_count }}</td>
                            <td class="px-5 py-3">
                                <x-platform.status-badge :status="$plan->is_active ? 'active' : 'cancelled'" />
                            </td>
                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('platform.subscription-plans.edit', $plan) }}" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                                    Edit
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-10 text-center text-gray-400">No subscription plans yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-platform-layout>
