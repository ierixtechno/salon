<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Invoices</h2>
    </x-slot>

    <div class="py-8">
        <div class="px-4 sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-indigo-100 text-indigo-800">
                            <tr>
                                <th class="text-left px-4 py-2 font-semibold text-base">Invoice #</th>
                                <th class="text-left px-4 py-2 font-semibold text-base">Plan</th>
                                <th class="text-left px-4 py-2 font-semibold text-base">Amount</th>
                                <th class="text-left px-4 py-2 font-semibold text-base">Paid</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($invoices as $invoice)
                                <tr>
                                    <td class="px-4 py-2">
                                        <a href="{{ route('billing.invoices.show', $invoice) }}" class="font-medium text-indigo-600 hover:text-indigo-800">
                                            {{ $invoice->invoice_number }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-2 text-gray-600">{{ $invoice->plan->name }}</td>
                                    <td class="px-4 py-2 text-gray-600">₹{{ number_format($invoice->amount, 2) }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $invoice->paid_at->format('d M Y, H:i') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-gray-500">No invoices yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="p-4">{{ $invoices->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
