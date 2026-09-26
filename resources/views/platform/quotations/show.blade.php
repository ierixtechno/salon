<x-platform-layout>
    <x-slot name="header">Quotation {{ $quotation->quotation_number }}</x-slot>

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

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 max-w-xl space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="font-semibold text-gray-900">{{ $quotation->tenant->name }}</h3>
                <p class="text-sm text-gray-500">{{ $quotation->plan->name }}</p>
                @if ($quotation->tenant->gstin)
                    <p class="text-xs text-gray-400 mt-0.5">Recipient GSTIN: {{ $quotation->tenant->gstin }}</p>
                @endif
            </div>
            <span class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full text-xs font-medium capitalize {{ $statusStyles[$quotation->status] ?? $statusStyles['cancelled'] }}">
                <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                {{ $quotation->status }}
            </span>
        </div>

        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <p class="text-gray-500">Modules included</p>
                <p class="font-medium text-gray-900">{{ $quotation->plan->modules->pluck('name')->implode(', ') ?: '—' }}</p>
            </div>
            <div>
                <p class="text-gray-500">Created by</p>
                <p class="font-medium text-gray-900">{{ $quotation->createdBy?->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-gray-500">Created</p>
                <p class="font-medium text-gray-900">{{ $quotation->created_at->format('d M Y, H:i') }}</p>
            </div>
        </div>

        <dl class="text-sm text-gray-600 pt-4 border-t border-gray-100 space-y-1">
            @if ((float) $quotation->discount_amount > 0)
                <div class="flex justify-between"><dt>Plan price</dt><dd>₹{{ number_format($quotation->amount + $quotation->discount_amount, 2) }}</dd></div>
                <div class="flex justify-between text-green-700"><dt>Discount ({{ rtrim(rtrim(number_format($quotation->discount_percent, 2), '0'), '.') }}%)</dt><dd>&minus;₹{{ number_format($quotation->discount_amount, 2) }}</dd></div>
            @endif
            <div class="flex justify-between"><dt>Subtotal</dt><dd>₹{{ number_format($quotation->amount, 2) }}</dd></div>
            @if ($quotation->igst_amount > 0)
                <div class="flex justify-between"><dt>IGST ({{ number_format($quotation->gst_rate_percent, 2) }}%)</dt><dd>₹{{ number_format($quotation->igst_amount, 2) }}</dd></div>
            @else
                <div class="flex justify-between"><dt>CGST ({{ number_format($quotation->gst_rate_percent / 2, 2) }}%)</dt><dd>₹{{ number_format($quotation->cgst_amount, 2) }}</dd></div>
                <div class="flex justify-between"><dt>SGST ({{ number_format($quotation->gst_rate_percent / 2, 2) }}%)</dt><dd>₹{{ number_format($quotation->sgst_amount, 2) }}</dd></div>
            @endif
            <div class="flex justify-between font-semibold text-gray-900 text-base"><dt>Total</dt><dd>₹{{ number_format($quotation->total_amount, 2) }}</dd></div>
        </dl>

        @if ($quotation->notes)
            <div class="text-sm">
                <p class="text-gray-500">Notes</p>
                <p class="text-gray-800">{{ $quotation->notes }}</p>
            </div>
        @endif

        <div class="text-sm border-t border-gray-100 pt-4">
            <a href="{{ route('platform.quotations.pdf', $quotation) }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Download PDF</a>
        </div>

        @if ($quotation->status === 'paid' && $quotation->invoice)
            <div class="text-sm border-t border-gray-100 pt-4">
                <a href="{{ route('platform.invoices.show', $quotation->invoice) }}" class="text-indigo-600 hover:text-indigo-800 font-medium">
                    View invoice {{ $quotation->invoice->invoice_number }} &rarr;
                </a>
            </div>
        @endif

        @if ($quotation->status === 'pending')
            <div class="border-t border-gray-100 pt-5 mt-6">
                <x-upi-qr-code :quotation="$quotation" />
            </div>

            <div class="border-t border-gray-100 pt-5 mt-6">
                <h4 class="font-semibold text-gray-900 text-sm">Record payment</h4>
                <p class="text-xs text-gray-500 mt-1">
                    Use this once you've received payment outside the app (bank transfer, UPI, cash, cheque).
                    The tenant can also pay this quotation online themselves after logging in — either way, their account unlocks the moment it's paid.
                </p>
                <form method="POST" action="{{ route('platform.quotations.record-payment', $quotation) }}" class="mt-3 space-y-3" onsubmit="return confirm('Record this payment and activate the tenant?')">
                    @csrf
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="payment_method" class="block text-xs font-medium text-gray-600 mb-1">Payment method</label>
                            <select id="payment_method" name="payment_method" required class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="bank_transfer">Bank transfer</option>
                                <option value="upi">UPI</option>
                                <option value="cash">Cash</option>
                                <option value="cheque">Cheque</option>
                                <option value="other">Other</option>
                            </select>
                            @error('payment_method')
                                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="payment_reference" class="block text-xs font-medium text-gray-600 mb-1">Reference (optional)</label>
                            <input type="text" id="payment_reference" name="payment_reference" placeholder="e.g. UTR / transaction ID" class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('payment_reference')
                                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                    <button type="submit" class="inline-flex items-center rounded-lg bg-green-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-green-500 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition">
                        Record payment &amp; activate tenant
                    </button>
                </form>
            </div>

            <div class="flex items-center justify-between pt-5 border-t border-gray-100">
                <a href="{{ route('platform.quotations.index') }}" class="text-sm text-gray-600 hover:text-gray-900">
                    &larr; Back to quotations
                </a>
                <form method="POST" action="{{ route('platform.quotations.cancel', $quotation) }}" onsubmit="return confirm('Cancel this quotation?')">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="inline-flex items-center rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition">
                        Cancel quotation
                    </button>
                </form>
            </div>
        @else
            <div class="pt-5 border-t border-gray-100">
                <a href="{{ route('platform.quotations.index') }}" class="text-sm text-gray-600 hover:text-gray-900">
                    &larr; Back to quotations
                </a>
            </div>
        @endif
    </div>
</x-platform-layout>
