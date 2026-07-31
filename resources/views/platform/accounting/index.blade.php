<x-platform-layout>
    <x-slot name="header">Accounting</x-slot>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
        <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-xl shadow-sm p-5 text-white">
            <span class="text-sm font-medium text-emerald-100">Total amount generated (all time)</span>
            <div class="mt-2 text-3xl font-bold">₹{{ number_format($totalIncome, 2) }}</div>
        </div>
        <div class="bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-xl shadow-sm p-5 text-white">
            <span class="text-sm font-medium text-indigo-100">Current month billing</span>
            <div class="mt-2 text-3xl font-bold">₹{{ number_format($currentMonthTotal, 2) }}</div>
        </div>
        <div class="bg-gradient-to-br from-amber-500 to-amber-600 rounded-xl shadow-sm p-5 text-white">
            <span class="text-sm font-medium text-amber-100">Next month expected billing</span>
            <div class="mt-2 text-3xl font-bold">₹{{ number_format($expectedNextMonthTotal, 2) }}</div>
        </div>
    </div>

    <div class="mb-8">
        <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3">Current month invoicing — {{ now()->format('F Y') }}</h3>
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
                        @forelse ($currentMonthInvoices as $invoice)
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
                                <td colspan="5" class="px-5 py-10 text-center text-gray-400">No invoices yet this month.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($currentMonthInvoices->isNotEmpty())
                        <tfoot class="bg-slate-50">
                            <tr>
                                <td colspan="3" class="px-5 py-3 text-right font-semibold text-slate-600">Total</td>
                                <td colspan="2" class="px-5 py-3 font-semibold text-slate-900">₹{{ number_format($currentMonthTotal, 2) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

    <div class="mb-8">
        <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3">Renewing next month</h3>
        <p class="text-xs text-gray-500 mb-3">
            Tenants whose current active subscription ends next month, at their current plan's price. This is an estimate —
            billing is manual, so a new quotation still needs to be created and paid for the amount to actually be collected.
        </p>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-100">
                        <tr>
                            <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Tenant</th>
                            <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Plan</th>
                            <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Renews</th>
                            <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Expected amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($renewingNextMonth as $subscription)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3 font-medium text-gray-900">{{ $subscription->tenant->name }}</td>
                                <td class="px-5 py-3 text-gray-600">{{ $subscription->plan->name }}</td>
                                <td class="px-5 py-3 text-gray-500">{{ $subscription->ends_at->format('d M Y') }}</td>
                                <td class="px-5 py-3 text-gray-600">₹{{ number_format($subscription->plan->price * (1 + config('platform.gst_rate_percent') / 100), 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-10 text-center text-gray-400">No subscriptions renewing next month.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div>
        <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3">Previous months</h3>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-100">
                        <tr>
                            <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Month</th>
                            <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Invoices</th>
                            <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($previousMonths as $row)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3 font-medium text-gray-900">{{ \Carbon\Carbon::createFromFormat('Y-m', $row->month)->format('F Y') }}</td>
                                <td class="px-5 py-3 text-gray-600">{{ $row->invoice_count }}</td>
                                <td class="px-5 py-3 text-gray-600">₹{{ number_format($row->total, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-5 py-10 text-center text-gray-400">No prior months recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-platform-layout>
