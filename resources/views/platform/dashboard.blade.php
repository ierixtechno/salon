<x-platform-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <span>Dashboard</span>
            <a href="{{ route('platform.accounting.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">Accounting &rarr;</a>
        </div>
    </x-slot>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
        <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-xl shadow-sm p-5 text-white">
            <span class="text-sm font-medium text-emerald-100">Total subscription income</span>
            <div class="mt-2 text-3xl font-bold">₹{{ number_format($totalIncome, 2) }}</div>
        </div>
        <div class="bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-xl shadow-sm p-5 text-white">
            <span class="text-sm font-medium text-indigo-100">This month's income</span>
            <div class="mt-2 text-3xl font-bold">₹{{ number_format($currentMonthIncome, 2) }}</div>
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        @php
            $stats = [
                ['label' => 'Pending Payment', 'value' => $counts['pending_payment'], 'accent' => 'text-blue-600 bg-blue-50', 'icon' => 'clock'],
                ['label' => 'Active', 'value' => $counts['active'], 'accent' => 'text-green-600 bg-green-50', 'icon' => 'check'],
                ['label' => 'Suspended', 'value' => $counts['suspended'], 'accent' => 'text-orange-600 bg-orange-50', 'icon' => 'pause'],
                ['label' => 'Cancelled', 'value' => $counts['cancelled'], 'accent' => 'text-gray-500 bg-gray-100', 'icon' => 'x'],
            ];
        @endphp

        @foreach ($stats as $stat)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-500">{{ $stat['label'] }}</span>
                    <span class="flex h-8 w-8 items-center justify-center rounded-full {{ $stat['accent'] }}">
                        @if ($stat['icon'] === 'clock')
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        @elseif ($stat['icon'] === 'check')
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                        @elseif ($stat['icon'] === 'pause')
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        @else
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        @endif
                    </span>
                </div>
                <div class="mt-3 text-3xl font-bold text-gray-900">{{ $stat['value'] }}</div>
            </div>
        @endforeach
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-semibold text-gray-900">Recent tenants</h2>
            <a href="{{ route('platform.tenants.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">View all &rarr;</a>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-100">
                    <tr>
                        <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Name</th>
                        <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Status</th>
                        <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Created</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($recentTenants as $tenant)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3">
                                <a href="{{ route('platform.tenants.show', $tenant) }}" class="flex items-center gap-3 group">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-indigo-700 text-xs font-semibold">
                                        {{ strtoupper(substr($tenant->name, 0, 2)) }}
                                    </span>
                                    <span class="font-medium text-gray-900 group-hover:text-indigo-600">{{ $tenant->name }}</span>
                                </a>
                            </td>
                            <td class="px-5 py-3">
                                <x-platform.status-badge :status="$tenant->status" />
                            </td>
                            <td class="px-5 py-3 text-gray-500">{{ $tenant->created_at->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-5 py-10 text-center text-gray-400">No tenants yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-platform-layout>
