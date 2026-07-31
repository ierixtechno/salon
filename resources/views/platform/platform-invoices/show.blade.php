<x-platform-layout>
    <x-slot name="header">Invoice {{ $invoice->invoice_number }}</x-slot>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 max-w-xl space-y-4">
        <div>
            <h3 class="font-semibold text-gray-900">{{ $invoice->tenant->name }}</h3>
            <p class="text-sm text-gray-500">{{ $invoice->plan->name }}</p>
            @if ($invoice->tenant->gstin)
                <p class="text-xs text-gray-400 mt-0.5">Recipient GSTIN: {{ $invoice->tenant->gstin }}</p>
            @endif
        </div>

        <div class="grid grid-cols-2 gap-4 text-sm">
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

        <dl class="text-sm text-gray-600 pt-4 border-t border-gray-100 space-y-1">
            <div class="flex justify-between"><dt>Subtotal</dt><dd>₹{{ number_format($invoice->subtotal, 2) }}</dd></div>
            @if ($invoice->igst_amount > 0)
                <div class="flex justify-between"><dt>IGST ({{ number_format($invoice->gst_rate_percent, 2) }}%)</dt><dd>₹{{ number_format($invoice->igst_amount, 2) }}</dd></div>
            @else
                <div class="flex justify-between"><dt>CGST ({{ number_format($invoice->gst_rate_percent / 2, 2) }}%)</dt><dd>₹{{ number_format($invoice->cgst_amount, 2) }}</dd></div>
                <div class="flex justify-between"><dt>SGST ({{ number_format($invoice->gst_rate_percent / 2, 2) }}%)</dt><dd>₹{{ number_format($invoice->sgst_amount, 2) }}</dd></div>
            @endif
            <div class="flex justify-between font-semibold text-gray-900 text-base"><dt>Total paid</dt><dd>₹{{ number_format($invoice->amount, 2) }}</dd></div>
        </dl>

        @if (config('platform.gstin'))
            <p class="text-xs text-gray-400">
                Supplier GSTIN: {{ config('platform.gstin') }} · SAC {{ config('platform.gst_sac_code') }}
            </p>
        @endif

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
