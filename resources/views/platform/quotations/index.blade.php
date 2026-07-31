<x-platform-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <span>Quotations</span>
            <a href="{{ route('platform.quotations.create') }}" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                + New Quotation
            </a>
        </div>
    </x-slot>

    @if (session('status'))
        <div class="mb-4 flex items-center gap-2 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
            {{ session('status') }}
        </div>
    @endif

    @php
        $statusStyles = [
            'pending' => 'bg-amber-500/10 text-amber-600',
            'paid' => 'bg-green-500/10 text-green-600',
            'cancelled' => 'bg-gray-500/10 text-gray-500',
        ];
    @endphp

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-100">
                    <tr>
                        <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Quotation #</th>
                        <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Tenant</th>
                        <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Plan</th>
                        <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Amount</th>
                        <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Status</th>
                        <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Created</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($quotations as $quotation)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3">
                                <a href="{{ route('platform.quotations.show', $quotation) }}" class="font-medium text-gray-900 hover:text-indigo-600">
                                    {{ $quotation->quotation_number }}
                                </a>
                            </td>
                            <td class="px-5 py-3 text-gray-600">{{ $quotation->tenant->name }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $quotation->plan->name }}</td>
                            <td class="px-5 py-3 text-gray-600">₹{{ number_format($quotation->amount, 2) }}</td>
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full text-xs font-medium capitalize {{ $statusStyles[$quotation->status] ?? $statusStyles['cancelled'] }}">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                                    {{ $quotation->status }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-gray-500">{{ $quotation->created_at->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-10 text-center text-gray-400">No quotations yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-5 py-4 border-t border-gray-100">{{ $quotations->links() }}</div>
    </div>
</x-platform-layout>
