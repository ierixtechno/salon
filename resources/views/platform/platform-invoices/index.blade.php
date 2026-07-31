<x-platform-layout>
    <x-slot name="header">Invoices</x-slot>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-100">
                    <tr>
                        <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Invoice #</th>
                        <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Tenant</th>
                        <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Plan</th>
                        <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Amount</th>
                        <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Paid</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($invoices as $invoice)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3">
                                <a href="{{ route('platform.invoices.show', $invoice) }}" class="font-medium text-gray-900 hover:text-indigo-600">
                                    {{ $invoice->invoice_number }}
                                </a>
                            </td>
                            <td class="px-5 py-3 text-gray-600">{{ $invoice->tenant->name }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $invoice->plan->name }}</td>
                            <td class="px-5 py-3 text-gray-600">₹{{ number_format($invoice->amount, 2) }}</td>
                            <td class="px-5 py-3 text-gray-500">{{ $invoice->paid_at->format('d M Y, H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-10 text-center text-gray-400">No invoices yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-5 py-4 border-t border-gray-100">{{ $invoices->links() }}</div>
    </div>
</x-platform-layout>
