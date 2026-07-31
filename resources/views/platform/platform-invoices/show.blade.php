<x-platform-layout>
    <x-slot name="header">Invoice {{ $invoice->invoice_number }}</x-slot>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 max-w-xl space-y-4">
        <div>
            <h3 class="font-semibold text-gray-900">{{ $invoice->tenant->name }}</h3>
            <p class="text-sm text-gray-500">{{ $invoice->plan->name }}</p>
        </div>

        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <p class="text-gray-500">Amount</p>
                <p class="font-medium text-gray-900">₹{{ number_format($invoice->amount, 2) }}</p>
            </div>
            <div>
                <p class="text-gray-500">Payment method</p>
                <p class="font-medium text-gray-900 capitalize">{{ $invoice->payment_method }}</p>
            </div>
            <div>
                <p class="text-gray-500">Payment reference</p>
                <p class="font-medium text-gray-900">{{ $invoice->payment_reference ?? '—' }}</p>
            </div>
            <div>
                <p class="text-gray-500">Paid at</p>
                <p class="font-medium text-gray-900">{{ $invoice->paid_at->format('d M Y, H:i') }}</p>
            </div>
        </div>

        <div class="text-sm border-t border-gray-100 pt-4">
            <a href="{{ route('platform.quotations.show', $invoice->quotation) }}" class="text-indigo-600 hover:text-indigo-800 font-medium">
                View source quotation {{ $invoice->quotation->quotation_number }} &rarr;
            </a>
        </div>

        <div class="pt-5 border-t border-gray-100">
            <a href="{{ route('platform.invoices.index') }}" class="text-sm text-gray-600 hover:text-gray-900">
                &larr; Back to invoices
            </a>
        </div>
    </div>
</x-platform-layout>
