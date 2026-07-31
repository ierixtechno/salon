<x-platform-layout>
    <x-slot name="header">Month-wise Income</x-slot>

    <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-xl shadow-sm p-5 text-white max-w-sm mb-6">
        <span class="text-sm font-medium text-emerald-100">Total subscription income (all time)</span>
        <div class="mt-2 text-3xl font-bold">₹{{ number_format($totalIncome, 2) }}</div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-100">
                    <tr>
                        <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Month</th>
                        <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Invoices</th>
                        <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Income</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($monthly as $row)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3 font-medium text-gray-900">{{ \Carbon\Carbon::createFromFormat('Y-m', $row->month)->format('F Y') }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $row->invoice_count }}</td>
                            <td class="px-5 py-3 text-gray-600">₹{{ number_format($row->total, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-5 py-10 text-center text-gray-400">No income recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-platform-layout>
