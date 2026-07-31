<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Invoice {{ $invoice->invoice_number }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="px-4 sm:px-6 lg:px-8 max-w-xl">
            <div class="bg-white shadow-sm rounded-lg p-6 space-y-4">
                <h3 class="font-semibold text-gray-900">{{ $invoice->plan->name }}</h3>

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
                    <div class="flex justify-between"><dt>CGST ({{ number_format($invoice->gst_rate_percent / 2, 2) }}%)</dt><dd>₹{{ number_format($invoice->cgst_amount, 2) }}</dd></div>
                    <div class="flex justify-between"><dt>SGST ({{ number_format($invoice->gst_rate_percent / 2, 2) }}%)</dt><dd>₹{{ number_format($invoice->sgst_amount, 2) }}</dd></div>
                    <div class="flex justify-between font-semibold text-gray-900 text-base"><dt>Total paid</dt><dd>₹{{ number_format($invoice->amount, 2) }}</dd></div>
                </dl>

                @if (config('platform.gstin'))
                    <p class="text-xs text-gray-400 pt-2">
                        Supplier GSTIN: {{ config('platform.gstin') }} · SAC {{ config('platform.gst_sac_code') }}
                    </p>
                @endif

                <div class="pt-4 border-t border-gray-100">
                    <a href="{{ route('billing.invoices.index') }}" class="text-sm text-gray-600 hover:text-gray-900">
                        &larr; Back to invoices
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
